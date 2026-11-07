<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Models\CashShift;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Repositories\OrderRepository;
use App\Repositories\QueueRepository;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CheckoutService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly QueueRepository $queueRepository,
        private readonly CommissionService $commissionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Perform idempotent checkout for a queue ticket, snapshotting prices & commissions.
     */
    public function checkoutQueueTicket(
        QueueTicket $ticket,
        PaymentMethod $method,
        int $discountTotal = 0,
        int $tipAmount = 0,
        ?int $cashReceived = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        ?int $cashierUserId = null,
        bool $markAsPaid = true,
    ): Order {
        return DB::transaction(function () use (
            $ticket,
            $method,
            $discountTotal,
            $cashReceived,
            $reference,
            $idempotencyKey,
            $cashierUserId,
            $markAsPaid,
        ): Order {
            $lockedTicket = $this->queueRepository->lockTicketByIdOrFail($ticket->id);
            $lockedTicket->load(['items', 'branch']);

            // 1. Idempotency check by idempotency_key or existing paid order for ticket
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existingByIdem = $this->orderRepository->findPaidOrderByIdempotencyKey($lockedTicket->tenant_id, $idempotencyKey);

                if ($existingByIdem !== null) {
                    return $existingByIdem->load(['items', 'payments']);
                }
            }

            $existingForTicket = Order::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $lockedTicket->tenant_id)
                ->where('queue_ticket_id', $lockedTicket->id)
                ->lockForUpdate()
                ->first();

            if ($existingForTicket !== null) {
                if ($existingForTicket->payment_status === PaymentStatus::Paid) {
                    return $existingForTicket->load(['items', 'payments']);
                }

                // Jika pesanan sudah ada namun belum lunas (unpaid),
                // dan dipanggil untuk pelunasan ($markAsPaid = true)
                if ($markAsPaid) {
                    $subtotal = (int) $lockedTicket->items->sum('price');
                    $safeDiscount = max(0, min($discountTotal, $subtotal));
                    $grandTotal = $subtotal - $safeDiscount;

                    $received = $cashReceived ?? $grandTotal;
                    if ($method === PaymentMethod::Cash && $received < $grandTotal) {
                        throw new DomainException('Nominal uang tunai yang diterima kurang dari total tagihan.');
                    }
                    $changeAmount = max(0, $received - $grandTotal);

                    $activeShift = CashShift::query()
                        ->withoutGlobalScope('tenant')
                        ->where('tenant_id', $lockedTicket->tenant_id)
                        ->where('branch_id', $lockedTicket->branch_id)
                        ->whereNull('closed_at')
                        ->first();

                    $existingForTicket->update([
                        'discount_total' => $safeDiscount,
                        'tip_amount' => 0,
                        'grand_total' => $grandTotal,
                        'paid_total' => $grandTotal,
                        'payment_status' => PaymentStatus::Paid,
                        'paid_at' => now(),
                        'cashier_user_id' => $cashierUserId ?? $existingForTicket->cashier_user_id,
                        'cash_shift_id' => $activeShift?->id ?? $existingForTicket->cash_shift_id,
                    ]);

                    if ($lockedTicket->status === QueueStatus::Completed && $lockedTicket->completed_at === null) {
                        $lockedTicket->update([
                            'completed_at' => now(),
                            'version' => $lockedTicket->version + 1,
                        ]);
                    }

                    $payment = Payment::query()
                        ->withoutGlobalScope('tenant')
                        ->where('order_id', $existingForTicket->id)
                        ->latest('id')
                        ->first();

                    if ($payment !== null) {
                        $payment->update([
                            'method' => $method,
                            'amount' => $grandTotal,
                            'status' => 'completed',
                            'cash_received' => $method === PaymentMethod::Cash ? $received : null,
                            'change_amount' => $method === PaymentMethod::Cash ? $changeAmount : null,
                            'reference' => $reference ?? $payment->reference,
                            'received_by' => $cashierUserId,
                        ]);
                    } else {
                        Payment::query()
                            ->withoutGlobalScope('tenant')
                            ->create([
                                'tenant_id' => $lockedTicket->tenant_id,
                                'order_id' => $existingForTicket->id,
                                'type' => 'charge',
                                'method' => $method,
                                'amount' => $grandTotal,
                                'cash_received' => $method === PaymentMethod::Cash ? $received : null,
                                'change_amount' => $method === PaymentMethod::Cash ? $changeAmount : null,
                                'reference' => $reference,
                                'status' => 'completed',
                                'received_by' => $cashierUserId,
                                'idempotency_key' => 'pay-'.$existingForTicket->idempotency_key,
                            ]);
                    }

                    if ($activeShift !== null && $method === PaymentMethod::Cash) {
                        $activeShift->increment('expected_cash', $grandTotal);
                    }

                    if ($lockedTicket->customer_profile_id !== null) {
                        $cp = CustomerProfile::query()
                            ->withoutGlobalScope('tenant')
                            ->find($lockedTicket->customer_profile_id);

                        if ($cp !== null) {
                            $cp->update([
                                'visit_count' => $cp->visit_count + 1,
                                'total_spent' => $cp->total_spent + $grandTotal,
                                'last_visit_at' => now(),
                            ]);
                        }
                    }

                    $existingForTicket->load('items');
                    $this->commissionService->recordOrderCommissions($existingForTicket);

                    $this->auditLogger->log(
                        action: 'order.paid',
                        auditable: $existingForTicket,
                        newValues: [
                            'number' => $existingForTicket->number,
                            'grand_total' => $existingForTicket->grand_total,
                            'method' => $method->value,
                        ],
                        userId: $cashierUserId,
                    );

                    return $existingForTicket->load(['items', 'payments']);
                }

                return $existingForTicket->load(['items', 'payments']);
            }

            if ($lockedTicket->items->isEmpty()) {
                throw new DomainException('Tiket antrean tidak memiliki item layanan untuk dibayar.');
            }

            if ($lockedTicket->status === QueueStatus::Completed && $lockedTicket->completed_at === null) {
                $lockedTicket->update([
                    'completed_at' => now(),
                    'version' => $lockedTicket->version + 1,
                ]);
            }

            $subtotal = (int) $lockedTicket->items->sum('price');
            $safeDiscount = max(0, min($discountTotal, $subtotal));
            $grandTotal = $subtotal - $safeDiscount;

            $received = $cashReceived ?? $grandTotal;
            if ($method === PaymentMethod::Cash && $received < $grandTotal) {
                throw new DomainException('Nominal uang tunai yang diterima kurang dari total tagihan.');
            }

            $changeAmount = max(0, $received - $grandTotal);
            $invoiceNumber = sprintf(
                'INV-%s-%s-%s',
                $lockedTicket->branch->code,
                now()->format('Ymd'),
                strtoupper(Str::random(4)),
            );

            $activeShift = CashShift::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $lockedTicket->tenant_id)
                ->where('branch_id', $lockedTicket->branch_id)
                ->whereNull('closed_at')
                ->first();

            try {
                $order = Order::query()
                    ->withoutGlobalScope('tenant')
                    ->create([
                        'tenant_id' => $lockedTicket->tenant_id,
                        'branch_id' => $lockedTicket->branch_id,
                        'queue_ticket_id' => $lockedTicket->id,
                        'customer_profile_id' => $lockedTicket->customer_profile_id,
                        'barber_membership_id' => $lockedTicket->barber_membership_id,
                        'cashier_user_id' => $cashierUserId,
                        'cash_shift_id' => $activeShift?->id,
                        'number' => $invoiceNumber,
                        'subtotal' => $subtotal,
                        'discount_total' => $safeDiscount,
                        'tip_amount' => 0,
                        'grand_total' => $grandTotal,
                        'paid_total' => $markAsPaid ? $grandTotal : 0,
                        'refunded_total' => 0,
                        'payment_status' => $markAsPaid ? PaymentStatus::Paid : PaymentStatus::Unpaid,
                        'idempotency_key' => $idempotencyKey ?: Str::uuid()->toString(),
                        'paid_at' => $markAsPaid ? now() : null,
                    ]);
            } catch (\Illuminate\Database\QueryException $e) {
                $existingOrder = Order::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $lockedTicket->tenant_id)
                    ->where('queue_ticket_id', $lockedTicket->id)
                    ->first();

                if ($existingOrder !== null) {
                    return $existingOrder->load(['items', 'payments']);
                }

                throw $e;
            }

            foreach ($lockedTicket->items as $ticketItem) {
                $service = $ticketItem->service_id
                    ? Service::query()->withoutGlobalScope('tenant')->find($ticketItem->service_id)
                    : null;

                $lineTotal = (int) $ticketItem->price;
                $ruleSnapshot = null;

                if ($service !== null) {
                    $ruleSnapshot = $this->commissionService->resolveRuleSnapshot(
                        tenantId: $lockedTicket->tenant_id,
                        serviceId: $service->id,
                        categoryId: $service->service_category_id,
                        barberMembershipId: $lockedTicket->barber_membership_id,
                    );
                }

                OrderItem::query()
                    ->withoutGlobalScope('tenant')
                    ->create([
                        'order_id' => $order->id,
                        'tenant_id' => $lockedTicket->tenant_id,
                        'service_id' => $ticketItem->service_id,
                        'barber_membership_id' => $lockedTicket->barber_membership_id,
                        'name' => $ticketItem->service_name,
                        'type' => $service?->type ?? 'service',
                        'qty' => 1,
                        'unit_price' => $lineTotal,
                        'discount' => 0,
                        'line_total' => $lineTotal,
                        'commission_rule_snapshot' => $ruleSnapshot,
                    ]);
            }

            Payment::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $lockedTicket->tenant_id,
                    'order_id' => $order->id,
                    'type' => 'charge',
                    'method' => $method,
                    'amount' => $grandTotal,
                    'cash_received' => $markAsPaid ? $received : null,
                    'change_amount' => $markAsPaid ? $changeAmount : null,
                    'reference' => $reference,
                    'status' => $markAsPaid ? 'completed' : 'pending',
                    'received_by' => $markAsPaid ? $cashierUserId : null,
                    'idempotency_key' => 'pay-'.$order->idempotency_key,
                ]);

            if ($markAsPaid) {
                if ($activeShift !== null && $method === PaymentMethod::Cash) {
                    $activeShift->increment('expected_cash', $grandTotal);
                }

                if ($lockedTicket->customer_profile_id !== null) {
                    $cp = CustomerProfile::query()
                        ->withoutGlobalScope('tenant')
                        ->find($lockedTicket->customer_profile_id);

                    if ($cp !== null) {
                        $cp->update([
                            'visit_count' => $cp->visit_count + 1,
                            'total_spent' => $cp->total_spent + $grandTotal,
                            'last_visit_at' => now(),
                        ]);
                    }
                }

                $order->load('items');
                $this->commissionService->recordOrderCommissions($order);

                $this->auditLogger->log(
                    action: 'order.paid',
                    auditable: $order,
                    newValues: [
                        'number' => $order->number,
                        'grand_total' => $order->grand_total,
                        'method' => $method->value,
                    ],
                    userId: $cashierUserId,
                );
            }

            return $order->load(['items', 'payments']);
        });
    }

    /**
     * Konfirmasi pembayaran order (QRIS atau Tunai) oleh kasir, menandai Lunas dan mencatat komisi & shift.
     */
    public function confirmOrderPayment(
        Order $order,
        ?int $cashierUserId = null,
        ?int $cashReceived = null,
    ): Order {
        return DB::transaction(function () use ($order, $cashierUserId, $cashReceived): Order {
            $lockedOrder = Order::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_status === PaymentStatus::Paid) {
                return $lockedOrder->load(['items', 'payments']);
            }

            $lockedOrder->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'paid_total' => $lockedOrder->grand_total,
                'cashier_user_id' => $cashierUserId ?? $lockedOrder->cashier_user_id,
            ]);

            $payment = Payment::query()
                ->withoutGlobalScope('tenant')
                ->where('order_id', $lockedOrder->id)
                ->latest('id')
                ->first();

            if ($payment !== null) {
                $paymentUpdates = [
                    'status' => 'completed',
                    'received_by' => $cashierUserId,
                ];
                if ($cashReceived !== null) {
                    $paymentUpdates['cash_received'] = $cashReceived;
                    $paymentUpdates['change_amount'] = max(0, $cashReceived - $lockedOrder->grand_total);
                }
                $payment->update($paymentUpdates);
            }

            $activeShift = CashShift::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('branch_id', $lockedOrder->branch_id)
                ->whereNull('closed_at')
                ->first();

            if ($activeShift !== null && $payment?->method === PaymentMethod::Cash) {
                $activeShift->increment('expected_cash', $lockedOrder->grand_total);
            }

            if ($lockedOrder->customer_profile_id !== null) {
                $cp = CustomerProfile::query()
                    ->withoutGlobalScope('tenant')
                    ->find($lockedOrder->customer_profile_id);

                if ($cp !== null) {
                    $cp->update([
                        'visit_count' => $cp->visit_count + 1,
                        'total_spent' => $cp->total_spent + $lockedOrder->grand_total,
                        'last_visit_at' => now(),
                    ]);
                }
            }

            $lockedOrder->load('items');
            $this->commissionService->recordOrderCommissions($lockedOrder);

            $this->auditLogger->log(
                action: 'order.payment_confirmed',
                auditable: $lockedOrder,
                newValues: [
                    'number' => $lockedOrder->number,
                    'grand_total' => $lockedOrder->grand_total,
                    'method' => $payment?->method?->value ?? 'qris',
                ],
                userId: $cashierUserId,
            );

            return $lockedOrder->load(['items', 'payments']);
        });
    }

    /**
     * Refund a paid order and create append-only commission reversals.
     */
    public function refundOrder(Order $order, string $reason, ?int $actorUserId = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actorUserId): Order {
            $locked = $this->orderRepository->lockOrderByIdOrFail($order->id);

            if ($locked->payment_status !== PaymentStatus::Paid) {
                throw new DomainException('Hanya order berstatus Lunas (Paid) yang dapat di-refund.');
            }

            $locked->update([
                'payment_status' => PaymentStatus::Refunded,
                'refunded_total' => $locked->grand_total,
                'notes' => $reason,
            ]);

            Payment::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $locked->tenant_id,
                    'order_id' => $locked->id,
                    'type' => 'refund',
                    'method' => PaymentMethod::Cash,
                    'amount' => $locked->grand_total,
                    'status' => 'completed',
                    'received_by' => $actorUserId,
                    'reason' => $reason,
                    'idempotency_key' => 'refund-'.$locked->id,
                ]);

            $this->commissionService->reverseOrderCommissions($locked);

            if ($locked->queue_ticket_id !== null) {
                \App\Models\QueueTicket::query()
                    ->withoutGlobalScope('tenant')
                    ->whereKey($locked->queue_ticket_id)
                    ->update([
                        'refund_status' => 'completed',
                        'refunded_at' => now(),
                        'refunded_by' => $actorUserId,
                        'refund_notes' => $reason,
                    ]);
            }

            $this->auditLogger->log(
                action: 'order.refunded',
                auditable: $locked,
                oldValues: ['payment_status' => PaymentStatus::Paid->value],
                newValues: ['payment_status' => PaymentStatus::Refunded->value, 'reason' => $reason],
                userId: $actorUserId,
            );

            return $locked->fresh(['items', 'payments']);
        });
    }
}
