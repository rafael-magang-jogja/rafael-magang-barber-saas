<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\QueueStatus;
use App\Models\Branch;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CashierPaymentLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_ticket_is_locked_for_cashier_until_customer_chooses_payment(): void
    {
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerUser = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Hapus tiket demo agar tidak mempengaruhi pengujian tombol terkunci
        QueueTicket::query()->delete();

        // 1. Tiket mandiri customer (is_cashier_input = false), status completed, belum checkout di HP
        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customerUser->customerProfiles()->where('tenant_id', $tenant->id)->firstOrFail()->id,
            'date' => now()->toDateString(),
            'sequence' => 99,
            'number' => 'A-099',
            'type' => 'walk_in',
            'is_cashier_input' => false,
            'status' => QueueStatus::Completed,
            'priority_at' => now(),
            'completed_at' => now(),
        ]);

        QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
        ]);

        // Cek tampilan dashboard kasir
        $response = $this->actingAs($cashierUser)->get(route('cashier.dashboard'));
        $response->assertOk();
        $response->assertSee('Tombol Bayar Terkunci');
        $response->assertSee('Menunggu Pilihan Pembayaran');

        // Kasir dapat membuka rute pembayaran untuk memproses tiket pelanggan
        $paymentResponse = $this->actingAs($cashierUser)->get(route('cashier.tickets.payment', $ticket));
        $paymentResponse->assertOk();

        // 2. Sekarang customer memilih metode pembayaran di HP (QRIS)
        $checkoutService = app(CheckoutService::class);
        $checkoutService->checkoutQueueTicket(
            ticket: $ticket,
            method: PaymentMethod::Qris,
            cashierUserId: null,
            markAsPaid: false,
        );

        // Setelah customer memilih, kasir melihat tombol telah terbuka (unlocked)
        $unlockedResponse = $this->actingAs($cashierUser)->get(route('cashier.dashboard'));
        $unlockedResponse->assertOk();
        $unlockedResponse->assertDontSee('Tombol Bayar Terkunci');
        $unlockedResponse->assertSee('Cek QRIS &amp; Selesaikan', false);
    }

    public function test_cashier_input_ticket_is_not_locked_when_completed(): void
    {
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Hapus tiket demo agar tidak mempengaruhi pengujian tombol terkunci
        QueueTicket::query()->delete();

        // Kasir memasukkan tiket walk-in langsung dari meja kasir (is_cashier_input = true)
        $walkInResponse = $this->actingAs($cashierUser)->post(route('cashier.walk-in.store'), [
            'branch_id' => $branch->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081299998888',
            'service_ids' => [$service->id],
        ]);
        $walkInResponse->assertSessionHasNoErrors();

        $ticket = QueueTicket::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('is_cashier_input', true)
            ->latest('id')
            ->firstOrFail();

        $walkInResponse->assertRedirect(route('cashier.dashboard'));

        $this->assertTrue($ticket->is_cashier_input);

        // Update status menjadi completed
        $ticket->update([
            'status' => QueueStatus::Completed,
            'completed_at' => now(),
        ]);

        // Cek dashboard kasir: tombol TIDAK di-lock, langsung bisa bayar di kasir
        $response = $this->actingAs($cashierUser)->get(route('cashier.dashboard'));
        $response->assertOk();
        $response->assertSee('Bayar di Kasir');
        $response->assertDontSee('Tombol Bayar Terkunci');

        // Buka rute pembayaran langsung bisa tanpa error/redirect
        $paymentResponse = $this->actingAs($cashierUser)->get(route('cashier.tickets.payment', $ticket));
        $paymentResponse->assertOk();
    }
}
