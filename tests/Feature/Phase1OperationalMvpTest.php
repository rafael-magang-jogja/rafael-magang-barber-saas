<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommissionEntry;
use App\Models\Membership;
use App\Models\Order;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\QueueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Phase1OperationalMvpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_walk_in_ticket_generation_is_sequential_and_increments_counter(): void
    {
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->forTenant($barberKing)->where('code', 'SDR')->firstOrFail();
        $haircut = Service::query()->forTenant($barberKing)->where('name', 'Haircut')->firstOrFail();

        $queueService = app(QueueService::class);

        $t1 = $queueService->createWalkInTicket($branch, 'Pelanggan Satu', '0819000001', [$haircut->id]);
        $t2 = $queueService->createWalkInTicket($branch, 'Pelanggan Dua', null, [$haircut->id]);

        $this->assertSame('A-015', $t1->number);
        $this->assertSame('A-016', $t2->number);
        $this->assertNull($t2->customerProfile?->phone);
    }

    public function test_walk_in_form_requires_only_customer_name_and_supports_multiple_services(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $services = Service::query()->forTenant($barberKing)->take(2)->pluck('id')->all();

        $this->actingAs($cashier)
            ->post(route('cashier.walk-in.store'), [
                'customer_name' => 'Andika',
                'service_ids' => $services,
            ])
            ->assertRedirect(route('cashier.bookings'))
            ->assertSessionHasNoErrors();

        $ticket = QueueTicket::query()->latest('id')->firstOrFail();
        $this->assertSame('Andika', $ticket->customerProfile?->name);
        $this->assertNull($ticket->customerProfile?->phone);
        $this->assertCount(2, $ticket->items);
    }

    public function test_booking_creates_items_and_prevents_overlapping_slot_for_same_barber(): void
    {
        $barberKing = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->forTenant($barberKing)->where('code', 'SDR')->firstOrFail();
        $haircut = Service::query()->forTenant($barberKing)->where('name', 'Haircut')->firstOrFail();
        $andi = Membership::query()->forTenant($barberKing)->where('role', Role::Barber)->where('display_name', 'Andi')->firstOrFail();

        $scheduledAt = now()->addDay()->setTime(14, 0)->format('Y-m-d H:i:s');

        // Booking pertama sukses
        $this->post("/b/{$barberKing->slug}/booking", [
            'branch_id' => $branch->id,
            'customer_name' => 'Raka',
            'customer_phone' => '0817000111',
            'service_ids' => [$haircut->id],
            'barber_membership_id' => $andi->id,
            'scheduled_at' => $scheduledAt,
        ])->assertRedirect();

        // Booking kedua di jam yang sama & barber yang sama harus ditolak
        $this->post("/b/{$barberKing->slug}/booking", [
            'branch_id' => $branch->id,
            'customer_name' => 'Donny',
            'customer_phone' => '0817000222',
            'service_ids' => [$haircut->id],
            'barber_membership_id' => $andi->id,
            'scheduled_at' => $scheduledAt,
        ])->assertSessionHasErrors('booking');
    }

    public function test_booking_check_in_and_full_queue_lifecycle_to_idempotent_checkout_and_refund(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $booking = Booking::query()->where('code', 'BK-SDR-0001')->firstOrFail();

        // 1. Check-in booking via Kasir
        $this->actingAs($cashier)
            ->post(route('cashier.bookings.check-in', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);

        $ticket = QueueTicket::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('B-017', $ticket->number);
        $this->assertSame(QueueStatus::Waiting, $ticket->status);

        // 2. Panggil -> Mulai -> Selesai dilakukan oleh Barber dari kursinya
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $this->actingAs($barber)->post(route('cashier.tickets.transition', $ticket), ['status' => 'called'])->assertRedirect();
        $this->actingAs($barber)->post(route('cashier.tickets.transition', $ticket), ['status' => 'in_service'])->assertRedirect();
        $this->actingAs($barber)->post(route('cashier.tickets.transition', $ticket), ['status' => 'completed'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(QueueStatus::Completed, $ticket->status);

        // 3. Checkout idempoten (dipanggil 2x dengan idempotency_key sama -> hanya 1 Order & 1 Komisi)
        $checkoutService = app(CheckoutService::class);
        $order1 = $checkoutService->checkoutQueueTicket($ticket, PaymentMethod::Cash, 0, 5_000, 100_000, null, 'idem-test-001', $cashier->id);
        $order2 = $checkoutService->checkoutQueueTicket($ticket, PaymentMethod::Cash, 0, 5_000, 100_000, null, 'idem-test-001', $cashier->id);

        $this->assertSame($order1->id, $order2->id);
        $this->assertSame(PaymentStatus::Paid, $order1->payment_status);

        // 4. Ubah harga layanan oleh Owner -> tidak boleh mengubah unit_price di order_items lama (Snapshot)
        $serviceId = $order1->items->first()->service_id;
        $this->actingAs($owner)
            ->patch(route('owner.services.update-price', $serviceId), ['price' => 95_000])
            ->assertRedirect();

        $this->assertSame(50_000, $order1->items()->first()->unit_price);

        // 5. Refund oleh Owner -> status Refunded & membuat reversal negatif di commission_entries
        $this->actingAs($owner)
            ->post(route('owner.orders.refund', $order1), ['reason' => 'Komplain pelanggan'])
            ->assertRedirect();

        $order1->refresh();
        $this->assertSame(PaymentStatus::Refunded, $order1->payment_status);

        $netCommission = (int) CommissionEntry::query()
            ->whereIn('order_item_id', $order1->items->pluck('id'))
            ->sum('amount');

        $this->assertSame(0, $netCommission);
    }

    public function test_cross_tenant_isolation_blocks_owner_b_from_modifying_tenant_a_resources(): void
    {
        $ownerB = User::query()->where('email', 'owner@gentlemancut.id')->firstOrFail();
        $tenantAService = Service::query()->withoutGlobalScope('tenant')->where('name', 'Haircut')->firstOrFail();

        // Owner Gentleman Cut mencoba mengubah harga layanan milik Barber King -> 404 karena Global Scope BelongsToTenant
        $this->actingAs($ownerB)
            ->patch("/owner/services/{$tenantAService->id}/price", ['price' => 10_000])
            ->assertNotFound();

        $this->assertSame(50_000, $tenantAService->fresh()->price);
    }

    public function test_custom_dropdowns_render_search_input_when_options_are_five_or_more(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('owner.reports'))
            ->assertOk()
            ->assertSee('data-ui-select', false)
            ->assertSee('data-select-search', false);

        $this->actingAs($owner)
            ->get(route('cashier.walk-in'))
            ->assertOk()
            ->assertSee('data-ui-select', false)
            ->assertSee('data-multiple="true"', false)
            ->assertSee('Cari layanan...', false)
            ->assertSee('Cari nama barber...', false);

        $this->actingAs($owner)
            ->get(route('cashier.bookings'))
            ->assertOk()
            ->assertSee('Booking Hari Ini (Menunggu Check-in)', false);
    }
}
