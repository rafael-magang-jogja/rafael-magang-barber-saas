<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Collection;

final class OrderRepository
{
    public function findPaidOrderByIdempotencyKey(int $tenantId, string $idempotencyKey): ?Order
    {
        return Order::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function findPaidOrderByQueueTicketId(int $tenantId, int $queueTicketId): ?Order
    {
        return Order::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('queue_ticket_id', $queueTicketId)
            ->where('payment_status', PaymentStatus::Paid)
            ->first();
    }

    public function lockOrderByIdOrFail(int $orderId): Order
    {
        return Order::query()
            ->withoutGlobalScope('tenant')
            ->with('items')
            ->whereKey($orderId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function getTodayRevenue(): int
    {
        return (int) Order::query()
            ->whereDate('paid_at', now()->toDateString())
            ->where('payment_status', PaymentStatus::Paid)
            ->sum('grand_total');
    }

    public function getCustomersTodayCount(): int
    {
        return Order::query()
            ->whereDate('paid_at', now()->toDateString())
            ->where('payment_status', PaymentStatus::Paid)
            ->count();
    }

    /**
     * @return Collection<int, Order>
     */
    public function getLatestOrders(int $limit = 6): Collection
    {
        return Order::query()
            ->with(['customerProfile', 'barber.user'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrdersForReport(
        ?int $branchId = null,
        ?int $barberId = null,
        ?string $paymentStatus = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Collection {
        return Order::query()
            ->with(['branch', 'customerProfile', 'barber.user'])
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->when($paymentStatus !== null && $paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($dateFrom !== null && $dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null && $dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getRecentAuditLogs(int $limit = 10): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getDraftPayrollPeriod(): ?Payroll
    {
        return Payroll::query()
            ->withCount('items')
            ->latest('id')
            ->first();
    }

    public function getTodayCommissionForBarber(?int $barberMembershipId): int
    {
        return (int) CommissionEntry::query()
            ->when($barberMembershipId !== null, fn ($q) => $q->where('membership_id', $barberMembershipId))
            ->whereDate('earned_at', now()->toDateString())
            ->sum('amount');
    }

    /**
     * @return Collection<int, Order>
     */
    public function getRecentOrdersForCustomer(int $tenantId, ?int $userId, ?string $ticketNumber = null, int $limit = 5): Collection
    {
        if ($userId === null && ($ticketNumber === null || $ticketNumber === '')) {
            return new Collection;
        }

        return Order::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($userId, $ticketNumber): void {
                if ($userId !== null) {
                    $query->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $userId));
                }
                if ($ticketNumber !== null && $ticketNumber !== '') {
                    $query->orWhereHas('queueTicket', fn ($qt) => $qt->where('number', $ticketNumber));
                }
            })
            ->with(['items', 'payments'])
            ->latest('paid_at')
            ->limit($limit)
            ->get();
    }

    public function findOrderByNumberOrFail(string $orderNumber): Order
    {
        return Order::query()
            ->withoutGlobalScope('tenant')
            ->where('number', $orderNumber)
            ->with(['branch', 'customerProfile', 'barber.user', 'queueTicket', 'items', 'payments', 'cashier'])
            ->firstOrFail();
    }
}
