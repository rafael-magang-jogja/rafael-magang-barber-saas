<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\WorkStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class PlatformTenantService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createTenantWithOwnerAndBranch(array $validated, ?int $actorUserId = null): Tenant
    {
        return DB::transaction(function () use ($validated, $actorUserId): Tenant {
            $owner = User::query()->create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'phone' => $validated['owner_phone'],
                'password' => Hash::make($validated['password'] ?? 'password'),
                'email_verified_at' => now(),
            ]);

            $isTrial = ($validated['status'] ?? 'active') === 'trial';
            $tenantStatus = $isTrial ? TenantStatus::Trial : TenantStatus::Active;
            $trialEndsAt = $isTrial ? now()->addDays(14) : null;

            $tenant = Tenant::query()->create([
                'name' => $validated['tenant_name'],
                'slug' => Str::slug((string) $validated['slug']),
                'owner_user_id' => $owner->id,
                'status' => $tenantStatus,
                'brand_color' => !empty($validated['brand_color']) ? $validated['brand_color'] : '#D97706',
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'timezone' => 'Asia/Jakarta',
                'trial_ends_at' => $trialEndsAt,
            ]);

            $branch = null;
            if (! empty($validated['branch_name']) && ! empty($validated['branch_code'])) {
                $branch = Branch::query()
                    ->withoutGlobalScope('tenant')
                    ->create([
                        'tenant_id' => $tenant->id,
                        'name' => $validated['branch_name'],
                        'code' => strtoupper((string) $validated['branch_code']),
                        'qr_token' => $tenant->slug.'-'.strtolower((string) $validated['branch_code']).'-'.Str::random(10),
                        'late_tolerance_minutes' => 15,
                        'is_active' => true,
                    ]);
            }

            Membership::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $owner->id,
                    'branch_id' => $branch?->id,
                    'role' => Role::Owner,
                    'display_name' => $owner->name,
                    'work_status' => WorkStatus::Active,
                    'is_active' => true,
                    'joined_at' => now()->toDateString(),
                ]);

            // Jika masa trial, paket selalu Starter (tier dasar). Paket Pro/Business hanya untuk langganan berbayar.
            if ($isTrial) {
                $starterPlan = Plan::query()->where('code', 'starter')->first();
                $targetPlanId = $starterPlan ? $starterPlan->id : (int) $validated['plan_id'];
            } else {
                $targetPlanId = (int) $validated['plan_id'];
            }

            $plan = Plan::query()->find($targetPlanId);

            $subscription = Subscription::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $targetPlanId,
                    'status' => $isTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                    'billing_cycle' => 'monthly',
                    'price' => $plan?->price_monthly ?? 0,
                    'starts_at' => now(),
                    'ends_at' => $isTrial ? now()->addDays(14) : now()->addMonth(),
                ]);

            // Terbitkan invoice lunas perdana hanya untuk pendaftaran Aktif berbayar
            if (! $isTrial && $plan && $plan->price_monthly > 0) {
                SubscriptionInvoice::query()
                    ->withoutGlobalScope('tenant')
                    ->create([
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                        'number' => 'INV-SUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                        'amount' => $plan->price_monthly,
                        'period_start' => now(),
                        'period_end' => $subscription->ends_at,
                        'status' => 'paid',
                        'due_at' => now(),
                        'paid_at' => now(),
                        'gateway' => 'manual_transfer',
                    ]);
            }

            $this->auditLogger->log(
                action: 'tenant.created',
                auditable: $tenant,
                newValues: ['name' => $tenant->name, 'slug' => $tenant->slug],
                userId: $actorUserId,
            );

            return $tenant;
        });
    }

    public function toggleTenantStatus(Tenant $tenant, ?int $actorUserId = null): Tenant
    {
        $old = $tenant->status->value;
        $next = $tenant->status === TenantStatus::Suspended ? TenantStatus::Active : TenantStatus::Suspended;

        $tenant->update(['status' => $next]);

        $this->auditLogger->log(
            action: 'tenant.status_toggled',
            auditable: $tenant,
            oldValues: ['status' => $old],
            newValues: ['status' => $next->value],
            userId: $actorUserId,
        );

        return $tenant;
    }
}
