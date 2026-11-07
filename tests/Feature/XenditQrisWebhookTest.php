<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class XenditQrisWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.xendit.webhook_token' => 'test_webhook_token']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_xendit_webhook_paid_auto_confirms_booking_and_issues_qris_ticket_and_order(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

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
            'code' => 'BK-SDR-XNDT',
            'scheduled_at' => Carbon::parse('2026-11-06 14:45:00'),
            'estimated_end_at' => Carbon::parse('2026-11-06 15:15:00'),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_test_12345',
            'xendit_external_id' => 'BOS-BK-BK-SDR-XNDT-1730000000',
            'xendit_payment_method' => 'QRIS',
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        // Kirim payload webhook Xendit status PAID
        $response = $this->postJson(route('api.webhooks.xendit.invoice'), [
            'id' => 'inv_test_12345',
            'external_id' => 'BOS-BK-BK-SDR-XNDT-1730000000',
            'status' => 'PAID',
            'payment_method' => 'QRIS',
            'payment_channel' => 'QRIS',
            'paid_at' => '2026-11-06T10:05:00.000Z',
            'amount' => $service->price,
        ], [
            'x-callback-token' => 'test_webhook_token',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $booking->refresh()->load(['queueTicket.order.payments']);

        $this->assertContains($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn]);
        $this->assertNotNull($booking->xendit_paid_at);
        $this->assertNotNull($booking->queueTicket);
        $this->assertStringStartsWith('B-', $booking->queueTicket->number);

        // Verifikasi Order & Payment QRIS lunas
        $order = $booking->queueTicket->order;
        $this->assertNotNull($order);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $payment = $order->payments->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentMethod::Qris, $payment->method);
        $this->assertSame('success', $payment->status);
    }

    public function test_xendit_webhook_is_idempotent_on_duplicate_calls(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

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
            'code' => 'BK-SDR-IDEM',
            'scheduled_at' => Carbon::parse('2026-11-06 14:45:00'),
            'estimated_end_at' => Carbon::parse('2026-11-06 15:15:00'),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_idem_123',
            'xendit_external_id' => 'BOS-BK-BK-SDR-IDEM-1730000000',
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        $payload = [
            'id' => 'inv_idem_123',
            'external_id' => 'BOS-BK-BK-SDR-IDEM-1730000000',
            'status' => 'PAID',
            'payment_method' => 'QRIS',
            'paid_at' => '2026-11-06T10:05:00.000Z',
        ];

        // Panggilan webhook pertama
        $this->postJson(route('api.webhooks.xendit.invoice'), $payload, [
            'x-callback-token' => 'test_webhook_token',
        ])->assertOk();

        $booking->refresh();
        $ticketId = $booking->queueTicket->id;

        // Panggilan webhook kedua (duplikat retry dari Xendit)
        $secondResponse = $this->postJson(route('api.webhooks.xendit.invoice'), $payload, [
            'x-callback-token' => 'test_webhook_token',
        ]);
        $secondResponse->assertOk();

        // Tiket tidak boleh dibuat ulang atau berlipat ganda
        $booking->refresh();
        $this->assertSame($ticketId, $booking->queueTicket->id);
    }

    public function test_xendit_webhook_expired_marks_pending_booking_as_expired(): void
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
            'code' => 'BK-SDR-EXPR',
            'scheduled_at' => now()->addDay(),
            'estimated_end_at' => now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_expr_999',
            'xendit_external_id' => 'BOS-BK-BK-SDR-EXPR-999',
        ]);

        $this->postJson(route('api.webhooks.xendit.invoice'), [
            'id' => 'inv_expr_999',
            'external_id' => 'BOS-BK-BK-SDR-EXPR-999',
            'status' => 'EXPIRED',
        ], [
            'x-callback-token' => 'test_webhook_token',
        ])->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
    }

    public function test_xendit_webhook_rejects_unauthorized_token_when_configured(): void
    {
        config(['services.xendit.webhook_token' => 'super_secret_webhook_token_123']);

        $response = $this->postJson(route('api.webhooks.xendit.invoice'), [
            'id' => 'inv_test_unauthorized',
            'status' => 'PAID',
        ], [
            'x-callback-token' => 'wrong_token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthorized webhook token',
        ]);
    }

    public function test_customer_booking_detail_page_renders_qris_card_properly(): void
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
            'code' => 'BK-SDR-VIEW',
            'scheduled_at' => now()->addDay(),
            'estimated_end_at' => now()->addDay()->addMinutes(30),
            'total_duration_minutes' => 30,
            'estimated_total' => $service->price,
            'status' => BookingStatus::Pending,
            'source' => 'online',
            'xendit_invoice_id' => 'inv_view_123',
            'xendit_payment_url' => 'https://checkout-staging.xendit.co/v2/test',
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => $service->price,
            'duration_minutes' => 30,
        ]);

        // Customer login and view booking detail
        $response = $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id));

        $response->assertOk();
        $response->assertSee('QRIS');
        $response->assertSee('Buka Pembayaran QRIS');
        $response->assertSee('Menunggu Pembayaran');
    }
}
