<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class WhatsAppInvoiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->tenant = Tenant::query()->firstOrFail();
        $this->branch = Branch::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();
    }

    public function test_wa_invoice_button_is_rendered_for_starter_plan_when_customer_has_phone(): void
    {
        // Set tenant to Starter plan
        $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();
        Subscription::query()->where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $starterPlan->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->tenant->fresh()->hasFeature('whatsapp_invoice'));

        // Buat customer dengan nomor WA
        $customer = CustomerProfile::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi Pratama',
            'phone' => '081234567890',
        ]);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_profile_id' => $customer->id,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'number' => 'INV-TEST-001',
            'subtotal' => 50000,
            'grand_total' => 50000,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'name' => 'Haircut Premium',
            'qty' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        $response = $this->actingAs($this->cashier)->get(route('invoices.show', $order->number));

        $response->assertOk();
        $response->assertSee('Kirim ke WhatsApp');
        $response->assertSee('https://wa.me/6281234567890', false);
        $response->assertSee('INV-TEST-001', false);
    }

    public function test_wa_invoice_button_is_not_rendered_when_viewer_is_customer(): void
    {
        $businessPlan = Plan::query()->where('code', 'business')->firstOrFail();
        Subscription::query()->where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $businessPlan->id,
            'status' => 'active',
        ]);

        $customer = CustomerProfile::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi Pratama',
            'phone' => '081234567890',
        ]);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_profile_id' => $customer->id,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'number' => 'INV-TEST-CUST',
            'subtotal' => 50000,
            'grand_total' => 50000,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $customerUser = User::factory()->create();
        $response = $this->actingAs($customerUser)->get(route('invoices.show', $order->number));

        $response->assertOk();
        $response->assertDontSee('Kirim ke WhatsApp');
        $response->assertSee('Kembali');
        $response->assertSee('Cetak / Simpan PDF');

        Auth::logout();
        $guestResponse = $this->get(route('invoices.show', $order->number));
        $guestResponse->assertOk();
        $guestResponse->assertDontSee('Kirim ke WhatsApp');
        $guestResponse->assertSee('Kembali');
        $guestResponse->assertSee('Cetak / Simpan PDF');
    }

    public function test_wa_invoice_button_is_not_rendered_when_customer_has_no_phone(): void
    {
        // Set tenant to Business plan
        $businessPlan = Plan::query()->where('code', 'business')->firstOrFail();
        Subscription::query()->where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $businessPlan->id,
            'status' => 'active',
        ]);

        // Customer tanpa nomor telepon / walk-in
        $customerWithoutPhone = CustomerProfile::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pelanggan Walk-In',
            'phone' => null,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_profile_id' => $customerWithoutPhone->id,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'number' => 'INV-TEST-002',
            'subtotal' => 40000,
            'grand_total' => 40000,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->get(route('invoices.show', $order->number));

        $response->assertOk();
        $response->assertDontSee('Kirim ke WhatsApp');
        $response->assertDontSee('https://wa.me/', false);
    }

    public function test_subscription_page_does_not_list_wa_notification_or_wa_invoice_as_restricted_benefits(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('owner.subscription'));

        $response->assertOk();
        $response->assertDontSee('Notifikasi WhatsApp Pelanggan');
        $response->assertDontSee('Kirim E-Invoice ke WhatsApp Pelanggan');
        $response->assertSee('White-label');
        $response->assertSee('Cetak Slip Gaji Resmi');
    }
}
