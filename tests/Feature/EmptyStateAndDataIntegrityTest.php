<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmptyStateAndDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Uji ketahanan seluruh halaman Owner pada Tenant baru yang datanya masih kosong
     * (0 cabang, 0 staf/barber, 0 transaksi, 0 order, 0 ulasan, 0 slip gaji, 0 cuti, 0 absensi).
     */
    public function test_owner_pages_render_without_errors_when_tenant_data_is_empty(): void
    {
        // Buat tenant baru yang kosong tanpa relasi apa pun
        $tenant = Tenant::query()->create([
            'name' => 'Barbershop Baru Kosong',
            'slug' => 'barbershop-baru-kosong',
            'status' => TenantStatus::Active,
        ]);

        $ownerUser = User::factory()->create([
            'name' => 'Owner Toko Baru',
            'email' => 'owner.baru@test.com',
            'is_superadmin' => false,
        ]);

        Membership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $ownerUser->id,
            'role' => Role::Owner,
            'is_active' => true,
        ]);

        // Berikan paket langganan aktif ke tenant kosong
        $plan = Plan::query()->where('code', 'starter')->first() ?? Plan::query()->first();
        if ($plan) {
            \App\Models\Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'price' => $plan->price ?? 0,
                'status' => \App\Enums\SubscriptionStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYear(),
            ]);
        }

        $routesToTest = [
            route('owner.dashboard'),
            route('owner.branches'),
            route('owner.staff'),
            route('owner.services'),
            route('owner.transactions'),
            route('owner.payroll'),
            route('owner.payroll.rules'),
            route('owner.payroll.staff'),
            route('owner.reports'),
            route('owner.attendances'),
            route('owner.time-offs'),
            route('owner.customers'),
            route('owner.subscription'),
            route('owner.settings'),
            route('owner.colors'),
            route('owner.schedules'),
        ];

        foreach ($routesToTest as $url) {
            $response = $this->actingAs($ownerUser)
                ->withSession(['active_tenant_id' => $tenant->id])
                ->get($url);

            if ($response->status() !== 200) {
                $this->fail("Route {$url} returned status {$response->status()} with redirect to " . $response->headers->get('Location'));
            }
            $response->assertSessionHasNoErrors();
        }
    }

    /**
     * Uji ketahanan seluruh halaman Platform Superadmin.
     */
    public function test_superadmin_platform_pages_render_without_errors(): void
    {
        $superadmin = User::query()->where('is_superadmin', true)->firstOrFail();

        $routesToTest = [
            route('platform.dashboard'),
            route('platform.subscriptions'),
            route('platform.transactions'),
            route('platform.plans'),
            route('platform.reports'),
            route('platform.settings'),
        ];

        foreach ($routesToTest as $url) {
            $response = $this->actingAs($superadmin)->get($url);
            $response->assertOk();
            $response->assertSessionHasNoErrors();
        }
    }

    /**
     * Uji ketahanan halaman Kasir & Barber ketika belum ada antrean dan belum ada transaksi hari ini.
     */
    public function test_cashier_and_barber_pages_render_without_errors_on_empty_day(): void
    {
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $barberUser = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        // 1. Kasir
        $cashierRoutes = [
            route('cashier.dashboard'),
            route('cashier.attendance'),
            route('cashier.bookings'),
            route('cashier.earnings'),
            route('cashier.profile'),
            route('cashier.walk-in'),
        ];

        foreach ($cashierRoutes as $url) {
            $response = $this->actingAs($cashierUser)->get($url);
            if ($response->status() !== 200) {
                $this->fail("Cashier route {$url} returned status {$response->status()} with redirect to " . $response->headers->get('Location'));
            }
            $response->assertSessionHasNoErrors();
        }

        // 2. Barber
        $barberRoutes = [
            route('barber.dashboard'),
            route('barber.attendance'),
            route('barber.profile'),
        ];

        foreach ($barberRoutes as $url) {
            $response = $this->actingAs($barberUser)->get($url);
            $response->assertOk();
            $response->assertSessionHasNoErrors();
        }
    }

    /**
     * Uji ketahanan portal pelanggan saat pelanggan baru belum punya riwayat apa pun.
     */
    public function test_customer_portal_renders_without_errors_for_fresh_customer(): void
    {
        $freshCustomer = User::factory()->create();

        $customerRoutes = [
            route('customer.dashboard'),
            route('customer.booking'),
            route('customer.history'),
            route('customer.profile'),
        ];

        foreach ($customerRoutes as $url) {
            $response = $this->actingAs($freshCustomer)->get($url);
            $response->assertOk();
            $response->assertSessionHasNoErrors();
        }
    }
}
