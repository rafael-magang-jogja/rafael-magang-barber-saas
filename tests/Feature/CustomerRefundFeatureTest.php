<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Models\Branch;
use App\Models\CommissionEntry;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerRefundFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cancelling_qris_paid_ticket_requires_bank_and_requires_cashier_or_owner_confirmation(): void
    {
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerUser = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $cashierUser = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', 'barber')->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerProfile = $customerUser->customerProfiles()->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Tiket dan Order berbayar QRIS di muka
        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customerProfile->id,
            'barber_membership_id' => $barber->id,
            'date' => now()->toDateString(),
            'sequence' => 91,
            'number' => 'Q-091',
            'type' => 'walk_in',
            'is_cashier_input' => false,
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'barber_membership_id' => $barber->id,
            'queue_ticket_id' => $ticket->id,
            'idempotency_key' => (string) Str::uuid(),
            'number' => 'ORD-QRIS-001',
            'subtotal' => $service->price,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => $service->price,
            'paid_total' => $service->price,
            'change_total' => 0,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $orderItem = OrderItem::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'service_id' => $service->id,
            'name' => $service->name,
            'type' => 'service',
            'qty' => 1,
            'unit_price' => $service->price,
            'discount' => 0,
            'line_total' => $service->price,
        ]);

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'method' => PaymentMethod::Qris,
            'amount' => $service->price,
            'status' => 'success',
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
        ]);

        CommissionEntry::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'membership_id' => $barber->id,
            'order_item_id' => $orderItem->id,
            'type' => 'service',
            'base_amount' => $service->price,
            'amount' => 15000,
            'earned_at' => now(),
        ]);

        // 2. Customer cancel tiket QRIS: wajib input rekening dan status jadi pending refund
        $cancelResponse = $this->actingAs($customerUser)
            ->post(route('customer.tickets.cancel', $ticket->id), [
                'reason' => 'Ada keperluan mendadak',
                'refund_bank_name' => 'BCA',
                'refund_account_number' => '081299998888',
                'refund_account_name' => 'Rafael Customer',
            ]);

        $cancelResponse->assertRedirect();

        $ticket->refresh();
        $this->assertSame(QueueStatus::Cancelled, $ticket->status);
        $this->assertSame('pending', $ticket->refund_status);
        $this->assertEquals($service->price, $ticket->refund_amount);
        $this->assertSame('BCA', $ticket->refund_bank_name);

        // Komisi barber langsung dihapus
        $this->assertDatabaseMissing('commission_entries', ['order_item_id' => $orderItem->id]);

        // 3. Kasir / Owner konfirmasi bahwa uang telah ditransfer
        $refundResponse = $this->actingAs($cashierUser)
            ->post(route('cashier.tickets.refund', $ticket->id), [
                'method' => 'transfer',
                'notes' => 'Telah ditransfer balik via BCA Mobile',
            ]);

        $refundResponse->assertRedirect();

        $ticket->refresh();
        $order->refresh();

        $this->assertSame('completed', $ticket->refund_status);
        $this->assertNotNull($ticket->refunded_at);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
    }

    public function test_cancelling_cash_paid_ticket_does_not_require_bank_account_and_completes_refund_instantly(): void
    {
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerUser = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', 'barber')->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerProfile = $customerUser->customerProfiles()->where('tenant_id', $tenant->id)->firstOrFail();

        // Tiket dibayar TUNAI (Cash) di kasir
        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customerProfile->id,
            'barber_membership_id' => $barber->id,
            'date' => now()->toDateString(),
            'sequence' => 92,
            'number' => 'C-092',
            'type' => 'walk_in',
            'is_cashier_input' => false,
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'barber_membership_id' => $barber->id,
            'queue_ticket_id' => $ticket->id,
            'idempotency_key' => (string) Str::uuid(),
            'number' => 'ORD-CASH-001',
            'subtotal' => $service->price,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => $service->price,
            'paid_total' => $service->price,
            'change_total' => 0,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'method' => PaymentMethod::Cash,
            'amount' => $service->price,
            'status' => 'success',
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
        ]);

        // Pembatalan tiket tunai: tanpa rekening bank sama sekali
        $cancelResponse = $this->actingAs($customerUser)
            ->post(route('customer.tickets.cancel', $ticket->id), [
                'reason' => 'Ingin pulang, batal cukur',
            ]);

        $cancelResponse->assertRedirect();

        $ticket->refresh();
        $order->refresh();

        $this->assertSame(QueueStatus::Cancelled, $ticket->status);
        // Untuk tunai: refund langsung selesai, uang diambil di kasir
        $this->assertSame('completed', $ticket->refund_status);
        $this->assertNotNull($ticket->refunded_at);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'type' => 'refund',
            'method' => PaymentMethod::Cash->value,
            'amount' => $service->price,
        ]);
    }

    public function test_dashboard_and_history_render_refund_account_fields_for_paid_ticket_and_booking(): void
    {
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerUser = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $barber = Membership::query()->where('tenant_id', $tenant->id)->where('role', 'barber')->firstOrFail();
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $customerProfile = $customerUser->customerProfiles()->where('tenant_id', $tenant->id)->firstOrFail();

        $ticket = QueueTicket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_profile_id' => $customerProfile->id,
            'barber_membership_id' => $barber->id,
            'date' => now()->toDateString(),
            'sequence' => 93,
            'number' => 'B-093',
            'type' => 'booking',
            'is_cashier_input' => false,
            'status' => QueueStatus::Waiting,
            'priority_at' => now(),
        ]);

        QueueTicketItem::query()->create([
            'queue_ticket_id' => $ticket->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'barber_membership_id' => $barber->id,
            'queue_ticket_id' => $ticket->id,
            'idempotency_key' => (string) Str::uuid(),
            'number' => 'ORD-QRIS-093',
            'subtotal' => $service->price,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => $service->price,
            'paid_total' => $service->price,
            'change_total' => 0,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'method' => PaymentMethod::Qris,
            'amount' => $service->price,
            'status' => 'success',
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
        ]);

        // Cek dashboard customer: form rekening harus muncul di modal pembatalan
        $dashboardResponse = $this->actingAs($customerUser)->get(route('customer.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('modal-cancel-active-ticket');
        $dashboardResponse->assertSee('refund_bank_name');
        $dashboardResponse->assertSee('refund_account_number');
        $dashboardResponse->assertSee('refund_account_name');

        // Cek history customer: form rekening harus muncul di modal pembatalan riwayat
        $historyResponse = $this->actingAs($customerUser)->get(route('customer.history'));
        $historyResponse->assertOk();
        $historyResponse->assertSee('modal-cancel-ticket-'.$ticket->id);
        $historyResponse->assertSee('refund_bank_name');
        $historyResponse->assertSee('refund_account_number');
        $historyResponse->assertSee('refund_account_name');
    }
}
