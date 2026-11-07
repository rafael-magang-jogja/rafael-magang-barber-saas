<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\WorkStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\SalaryScheme;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();
        $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();

        $defaultOpeningHours = [
            'mon' => ['open' => '10:00', 'close' => '21:00'],
            'tue' => ['open' => '10:00', 'close' => '21:00'],
            'wed' => ['open' => '10:00', 'close' => '21:00'],
            'thu' => ['open' => '10:00', 'close' => '21:00'],
            'fri' => ['open' => '10:00', 'close' => '22:00'],
            'sat' => ['open' => '09:00', 'close' => '22:00'],
            'sun' => ['open' => '09:00', 'close' => '21:00'],
        ];

        // =====================================================================
        // OWNER 1: Pak Rudi — Barber King (Paket Pro, 2 Cabang: Sudirman & Kemang)
        // =====================================================================
        $ownerRudi = User::query()->updateOrCreate(
            ['email' => 'owner@barberking.id'],
            [
                'name' => 'Pak Rudi Hartono',
                'phone' => '081210000001',
                'password' => Hash::make('password'),
                'is_superadmin' => false,
                'email_verified_at' => now(),
            ]
        );

        $barberKing = Tenant::query()->updateOrCreate(
            ['slug' => 'barber-king'],
            [
                'name' => 'Barber King',
                'owner_user_id' => $ownerRudi->id,
                'brand_color' => '#D97706',
                'phone' => '0215550101',
                'email' => 'hello@barberking.id',
                'address' => 'Jl. Jend. Sudirman No. 45, Jakarta Selatan',
                'timezone' => 'Asia/Jakarta',
                'status' => TenantStatus::Active,
                'settings' => [
                    'queue_mode' => 'merged',
                    'cashier_can_refund' => true,
                    'late_tolerance_minutes' => 15,
                ],
            ]
        );

        Membership::query()->updateOrCreate(
            [
                'tenant_id' => $barberKing->id,
                'user_id' => $ownerRudi->id,
                'role' => Role::Owner,
            ],
            [
                'branch_id' => null,
                'display_name' => 'Pak Rudi',
                'work_status' => WorkStatus::Active,
                'is_active' => true,
                'joined_at' => now()->subYear()->toDateString(),
            ]
        );

        $subKing = Subscription::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id],
            [
                'plan_id' => $proPlan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now()->startOfMonth(),
                'ends_at' => now()->endOfMonth(),
                'price' => $proPlan->price_monthly,
                'auto_renew' => true,
            ]
        );

        SubscriptionInvoice::query()->updateOrCreate(
            ['number' => 'SUB-BK-202610-001'],
            [
                'tenant_id' => $barberKing->id,
                'subscription_id' => $subKing->id,
                'type' => 'activation',
                'amount' => $proPlan->price_monthly,
                'period_start' => '2026-10-01 00:00:00',
                'period_end' => '2026-10-31 23:59:59',
                'status' => 'paid',
                'due_at' => now()->startOfMonth()->addDays(3),
                'paid_at' => now()->startOfMonth()->addDay(),
                'gateway' => 'manual',
                'gateway_ref' => 'TRX-PRO-001',
            ]
        );

        Branch::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'code' => 'SDR'],
            [
                'name' => 'Cabang Sudirman',
                'address' => 'Jl. Jend. Sudirman No. 45, Jakarta Selatan',
                'phone' => '0215550101',
                'lat' => -6.2245830,
                'lng' => 106.8096950,
                'opening_hours' => $defaultOpeningHours,
                'late_tolerance_minutes' => 15,
                'booking_slot_minutes' => 30,
                'allow_customer_choose_barber' => true,
                'qr_token' => 'bk-sudirman-qr-token-001',
                'is_active' => true,
            ]
        );

        Branch::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'code' => 'KMG'],
            [
                'name' => 'Cabang Kemang',
                'address' => 'Jl. Kemang Raya No. 18, Jakarta Selatan',
                'phone' => '0215550102',
                'lat' => -6.2607180,
                'lng' => 106.8133050,
                'opening_hours' => $defaultOpeningHours,
                'late_tolerance_minutes' => 15,
                'booking_slot_minutes' => 30,
                'allow_customer_choose_barber' => true,
                'qr_token' => 'bk-kemang-qr-token-002',
                'is_active' => true,
            ]
        );

        SalaryScheme::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Senior Barber (Komisi)'],
            [
                'type' => 'commission_only',
                'base_salary' => 0,
                'alpha_penalty_nominal' => 0,
                'period' => 'monthly',
                'target_bonus_rules' => [
                    ['min_services' => 250, 'bonus' => 200_000],
                    ['min_services' => 300, 'bonus' => 300_000],
                    ['min_services' => 350, 'bonus' => 500_000],
                ],
                'is_active' => true,
            ]
        );

        SalaryScheme::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Mitra Bagi Hasil 30%'],
            [
                'type' => 'commission_only',
                'base_salary' => 0,
                'alpha_penalty_nominal' => 0,
                'period' => 'monthly',
                'target_bonus_rules' => null,
                'is_active' => true,
            ]
        );

        SalaryScheme::query()->updateOrCreate(
            ['tenant_id' => $barberKing->id, 'name' => 'Staf Admin / Kasir (Gaji Pokok)'],
            [
                'type' => 'fixed',
                'base_salary' => 2_500_000,
                'alpha_penalty_nominal' => 0,
                'period' => 'monthly',
                'target_bonus_rules' => null,
                'is_active' => true,
            ]
        );

        // =====================================================================
        // OWNER 2: Mas Hendra — Gentleman Cut (Paket Starter, 1 Cabang: Tebet)
        // Untuk pengujian isolasi multi-tenant (Owner A tidak bisa lihat data B)
        // =====================================================================
        $ownerHendra = User::query()->updateOrCreate(
            ['email' => 'owner@gentlemancut.id'],
            [
                'name' => 'Hendra Wijaya',
                'phone' => '081210000002',
                'password' => Hash::make('password'),
                'is_superadmin' => false,
                'email_verified_at' => now(),
            ]
        );

        $gentlemanCut = Tenant::query()->updateOrCreate(
            ['slug' => 'gentleman-cut'],
            [
                'name' => 'Gentleman Cut Barbershop',
                'owner_user_id' => $ownerHendra->id,
                'brand_color' => '#0EA5E9',
                'phone' => '0215550202',
                'email' => 'contact@gentlemancut.id',
                'address' => 'Jl. Tebet Utara Dalam No. 12, Jakarta Selatan',
                'timezone' => 'Asia/Jakarta',
                'status' => TenantStatus::Trial,
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'queue_mode' => 'merged',
                    'cashier_can_refund' => false,
                    'late_tolerance_minutes' => 10,
                ],
            ]
        );

        Membership::query()->updateOrCreate(
            [
                'tenant_id' => $gentlemanCut->id,
                'user_id' => $ownerHendra->id,
                'role' => Role::Owner,
            ],
            [
                'branch_id' => null,
                'display_name' => 'Mas Hendra',
                'work_status' => WorkStatus::Active,
                'is_active' => true,
                'joined_at' => now()->subDays(5)->toDateString(),
            ]
        );

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $gentlemanCut->id],
            [
                'plan_id' => $starterPlan->id,
                'status' => SubscriptionStatus::Trialing,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(9),
                'price' => $starterPlan->price_monthly,
                'auto_renew' => false,
            ]
        );

        Branch::query()->updateOrCreate(
            ['tenant_id' => $gentlemanCut->id, 'code' => 'TBT'],
            [
                'name' => 'Cabang Tebet',
                'address' => 'Jl. Tebet Utara Dalam No. 12, Jakarta Selatan',
                'phone' => '0215550202',
                'opening_hours' => $defaultOpeningHours,
                'late_tolerance_minutes' => 10,
                'booking_slot_minutes' => 30,
                'allow_customer_choose_barber' => true,
                'qr_token' => 'gc-tebet-qr-token-001',
                'is_active' => true,
            ]
        );
    }
}
