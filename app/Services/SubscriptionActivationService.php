<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubscriptionActivationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Activate or renew a subscription from a paid invoice.
     * Idempotent: If the invoice is already marked as 'paid', returns early without re-extending.
     *
     * @param array<string, mixed> $payload
     */
    public function activateFromInvoice(
        SubscriptionInvoice $invoice,
        array $payload = [],
        ?User $actor = null,
        string $defaultGateway = 'xendit',
        string $defaultGatewayRef = 'XENDIT-WEBHOOK-PAID'
    ): SubscriptionInvoice {
        if ($invoice->status === 'paid') {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $payload, $actor, $defaultGateway, $defaultGatewayRef): SubscriptionInvoice {
            $invoice->loadMissing(['tenant', 'targetPlan', 'subscription.plan']);

            /** @var Tenant|null $tenant */
            $tenant = $invoice->tenant;
            /** @var Subscription|null $sub */
            $sub = $invoice->subscription ?? $tenant?->activeSubscription() ?? $tenant?->subscriptions()->withoutGlobalScope('tenant')->latest('id')->first();

            $targetPlanId = $invoice->target_plan_id ?? $sub?->plan_id;
            $targetPlan = $invoice->targetPlan ?? ($targetPlanId ? Plan::find($targetPlanId) : null);

            // Tentukan masa aktif baru:
            // - Renewal (paket sama): menambah sisa hari jika belum berakhir (+30 hari)
            // - Upgrade / Downgrade / Aktivasi Baru: dihitung 30 hari dari sekarang
            $isRenewal = ($invoice->type === 'renewal')
                || ($sub && $targetPlanId && (int) $sub->plan_id === (int) $targetPlanId && ! in_array($invoice->type, ['upgrade', 'downgrade'], true));

            if ($isRenewal) {
                $baseDate = ($sub && $sub->ends_at && $sub->ends_at->isFuture())
                    ? $sub->ends_at
                    : now();
                $newStartsAt = $baseDate;
                $newEndsAt = $baseDate->copy()->addDays(30);
            } else {
                $newStartsAt = now();
                $newEndsAt = now()->addDays(30);
            }

            $paidAt = ! empty($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now();
            $gateway = $payload['gateway'] ?? $defaultGateway;
            $gatewayRef = $payload['gateway_ref'] ?? $invoice->xendit_invoice_id ?? $defaultGatewayRef;
            $paymentMethod = $payload['payment_method'] ?? $invoice->xendit_payment_method ?? 'QRIS';
            $paymentChannel = $payload['payment_channel'] ?? $invoice->xendit_payment_channel ?? 'QRIS';

            // 1. Update status Invoice menjadi 'paid'
            $invoice->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'period_start' => $newStartsAt,
                'period_end' => $newEndsAt,
                'gateway' => $gateway,
                'gateway_ref' => $gatewayRef,
                'payment_method' => $paymentMethod,
                'xendit_payment_method' => $paymentMethod,
                'xendit_payment_channel' => $paymentChannel,
                'xendit_paid_at' => $paidAt,
            ]);

            // 2. Update atau create subscription
            if ($sub !== null) {
                $sub->update([
                    'plan_id' => $targetPlanId,
                    'status' => SubscriptionStatus::Active,
                    'price' => $invoice->amount,
                    'starts_at' => now(),
                    'ends_at' => $newEndsAt,
                ]);
            } elseif ($tenant !== null && $targetPlanId !== null) {
                /** @var Subscription $sub */
                $sub = Subscription::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $targetPlanId,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'ends_at' => $newEndsAt,
                    'price' => $invoice->amount,
                    'auto_renew' => true,
                ]);
                $invoice->update(['subscription_id' => $sub->id]);
            }

            // 3. Reset kustomisasi jika ada penurunan/perubahan paket
            if ($tenant !== null && $targetPlan !== null) {
                $tenant->resetPlanRestrictedFeatures($targetPlan->code);
            }

            // 4. Jika tenant sempat suspended karena telat bayar, reaktifkan
            if ($tenant !== null && $tenant->status === TenantStatus::Suspended) {
                $tenant->update(['status' => TenantStatus::Active]);
            }

            // 5. Catat ke Audit Logger
            $this->auditLogger->log(
                action: 'subscription.payment_confirmed',
                auditable: $invoice,
                oldValues: ['status' => $invoice->getOriginal('status') ?? 'unpaid'],
                newValues: [
                    'status' => 'paid',
                    'type' => $invoice->type,
                    'target_plan_id' => $targetPlanId,
                    'gateway' => $gateway,
                    'gateway_ref' => $gatewayRef,
                    'new_ends_at' => $newEndsAt->toDateString(),
                    'tenant' => $tenant?->name,
                ],
                userId: $actor?->id,
            );

            return $invoice->fresh(['tenant', 'targetPlan', 'subscription.plan']);
        });
    }

    /**
     * Activate a free (Rp0) plan instantly without payment gateway.
     */
    public function activateFreePlan(Tenant $tenant, Plan $plan, ?User $actor = null): SubscriptionInvoice
    {
        return DB::transaction(function () use ($tenant, $plan, $actor): SubscriptionInvoice {
            $existingSub = $tenant->activeSubscription()
                ?? $tenant->subscriptions()->withoutGlobalScope('tenant')->with('plan')->latest('id')->first();

            $type = ($existingSub !== null && (int) $existingSub->plan_id === (int) $plan->id)
                ? 'renewal'
                : 'downgrade';

            $periodStart = now();
            $periodEnd = now()->addDays(30);

            // Buat atau update invoice gratis
            /** @var SubscriptionInvoice $invoice */
            $invoice = SubscriptionInvoice::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $existingSub?->id,
                'target_plan_id' => $plan->id,
                'type' => $type,
                'number' => 'INV-SUB-FREE-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                'amount' => 0,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'unpaid',
                'due_at' => now(),
                'gateway' => 'free_tier',
                'gateway_ref' => 'FREE-PLAN-AUTO-ACTIVATED',
                'payment_method' => 'free',
            ]);

            // Eksekusi aktivasi
            return $this->activateFromInvoice(
                invoice: $invoice,
                payload: [
                    'gateway' => 'free_tier',
                    'gateway_ref' => 'FREE-PLAN-AUTO-ACTIVATED',
                    'payment_method' => 'free',
                    'payment_channel' => 'FREE',
                    'paid_at' => now(),
                ],
                actor: $actor,
                defaultGateway: 'free_tier',
                defaultGatewayRef: 'FREE-PLAN-AUTO-ACTIVATED'
            );
        });
    }
}
