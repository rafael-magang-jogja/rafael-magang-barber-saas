<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Livewire\CustomerBookingTracker;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CustomerBookingTrackerLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_booking_page_renders_livewire_tracker_with_wire_poll_5s(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::query()->where('phone', '081234567890')->first()
            ?? User::factory()->create(['phone' => '081234567890', 'is_superadmin' => false]);
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->customerProfiles()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $customer->phone],
                ['name' => $customer->name]
            )->id,
            'user_id' => $customer->id,
            'code' => 'BK-SDR-POLL',
            'scheduled_at' => now()->addDay(),
            'estimated_end_at' => now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('wire:poll.5s', false);
        $response->assertSee('Sinkronisasi status pembayaran QRIS');
    }

    public function test_booking_tracker_component_detects_payment_and_triggers_refresh(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customer = User::query()->where('phone', '081234567890')->first()
            ?? User::factory()->create(['phone' => '081234567890', 'is_superadmin' => false]);
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $booking = Booking::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customer->customerProfiles()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $customer->phone],
                ['name' => $customer->name]
            )->id,
            'user_id' => $customer->id,
            'code' => 'BK-SDR-POLL2',
            'scheduled_at' => now()->addDay(),
            'estimated_end_at' => now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
        ]);

        $test = Livewire::test(CustomerBookingTracker::class, [
            'bookingId' => $booking->id,
        ])
            ->assertSeeHtml('wire:poll.5s')
            ->assertSee('Sinkronisasi status pembayaran QRIS')
            ->assertSee('Live 5s');

        // Simulasi pembayaran lunas tiba di database (misal dari webhook)
        $booking->update([
            'status' => BookingStatus::Confirmed,
            'xendit_paid_at' => now(),
        ]);

        // Panggil polling Livewire
        $test->call('checkBookingStatus');

        $this->assertTrue($test->get('isPaid'));
        $this->assertSame(BookingStatus::Confirmed->value, $test->get('status'));
    }
}
