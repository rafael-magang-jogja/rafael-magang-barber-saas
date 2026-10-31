<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\QueueStatus;
use App\Livewire\CashierNavBadge;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\QueueTicket;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CashierNavBadgeLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_nav_badge_renders_queue_count_for_waiting_and_called_tickets(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = CustomerProfile::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Bersihkan tiket lama hari ini
        QueueTicket::query()->where('tenant_id', $tenant->id)->delete();

        // Buat 1 tiket waiting dan 1 tiket called hari ini
        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => now()->toDateString(),
            'number' => 'A-001',
            'sequence' => 1,
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => now()->toDateString(),
            'number' => 'A-002',
            'sequence' => 2,
            'type' => 'walk_in',
            'status' => QueueStatus::Called,
            'priority_at' => now(),
        ]);

        // Buat 1 tiket completed (tidak boleh dihitung)
        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => now()->toDateString(),
            'number' => 'A-003',
            'sequence' => 3,
            'type' => 'walk_in',
            'status' => QueueStatus::Completed,
            'priority_at' => now(),
        ]);

        app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

        Livewire::test(CashierNavBadge::class, ['type' => 'queue', 'context' => 'sidebar'])
            ->assertSeeHtml('>2</span>')
            ->assertSeeHtml('bg-amber-400');
    }

    public function test_cashier_nav_badge_renders_booking_count_for_today_confirmed_bookings(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = CustomerProfile::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Bersihkan booking hari ini
        Booking::query()->where('tenant_id', $tenant->id)->delete();

        // Buat 2 booking terkonfirmasi hari ini
        Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'code' => 'BK-TEST-001',
            'scheduled_at' => now()->startOfDay()->addHours(14),
            'estimated_end_at' => now()->startOfDay()->addHours(15),
            'total_duration_minutes' => 60,
            'status' => BookingStatus::Confirmed,
            'estimated_total' => 50000,
        ]);

        Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'code' => 'BK-TEST-002',
            'scheduled_at' => now()->startOfDay()->addHours(16),
            'estimated_end_at' => now()->startOfDay()->addHours(17),
            'total_duration_minutes' => 60,
            'status' => BookingStatus::Confirmed,
            'estimated_total' => 75000,
        ]);

        // Buat 1 booking selesai/batal (tidak boleh dihitung)
        Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'code' => 'BK-TEST-003',
            'scheduled_at' => now()->startOfDay()->addHours(11),
            'estimated_end_at' => now()->startOfDay()->addHours(12),
            'total_duration_minutes' => 60,
            'status' => BookingStatus::Completed,
            'estimated_total' => 50000,
        ]);

        app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

        Livewire::test(CashierNavBadge::class, ['type' => 'booking', 'context' => 'sidebar'])
            ->assertSeeHtml('>2</span>')
            ->assertSeeHtml('bg-rose-500');
    }

    public function test_cashier_nav_badge_parent_dot_renders_small_circle_dot(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = CustomerProfile::query()->where('tenant_id', $tenant->id)->firstOrFail();

        QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->id,
            'date' => now()->toDateString(),
            'number' => 'A-001',
            'sequence' => 1,
            'type' => 'walk_in',
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

        Livewire::test(CashierNavBadge::class, ['type' => 'parent_dot', 'context' => 'sidebar_dot'])
            ->assertSeeHtml('data-nav-indicator')
            ->assertSeeHtml('bg-amber-500');
    }
}
