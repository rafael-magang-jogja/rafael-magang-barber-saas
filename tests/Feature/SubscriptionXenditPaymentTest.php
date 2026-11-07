<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SubscriptionXenditPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.xendit.webhook_token' => 'sub_webhook_secret_token']);
        $this->seed(DatabaseSeeder::class);

        $this->owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $this->tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
    }

    public function test_free_plan_bypasses_xendit_and_activates_immediately(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

        // Buat paket gratis (Rp0)
        $freePlan = Plan::query()->create([
            'name' => 'Starter Free',
            'code' => 'starter-free',
            'description' => 'Paket evaluasi gratis',
            'price_monthly' => 0,
            'max_branches' => 1,
            'max_staff' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->post(route('owner.subscription.upgrade'), [
                'plan_id' => $freePlan->id,
            ]);

        $response->assertRedirect(route('owner.subscription'));
        $response->assertSessionHas('status');

        $latestSub = $this->tenant->activeSubscription();
        $this->assertNotNull($latestSub);
        $this->assertSame($freePlan->id, $latestSub->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $latestSub->status);

        $latestInvoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($latestInvoice);
        $this->assertSame(0, $latestInvoice->amount);
        $this->assertSame('paid', $latestInvoice->status);
        $this->assertSame('free_tier', $latestInvoice->gateway);
    }

    public function test_paid_plan_creates_xendit_invoice_and_redirects_to_invoice_page(): void
    {
        $paidPlan = Plan::query()->where('price_monthly', '>', 0)->firstOrFail();

        $response = $this->actingAs($this->owner)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->post(route('owner.subscription.upgrade'), [
                'plan_id' => $paidPlan->id,
            ]);

        $latestInvoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($latestInvoice);
        $this->assertSame($paidPlan->id, $latestInvoice->target_plan_id);
        $this->assertSame('unpaid', $latestInvoice->status);
        $this->assertSame('xendit', $latestInvoice->gateway);
        $this->assertNotNull($latestInvoice->xendit_invoice_id);
        $this->assertNotNull($latestInvoice->xendit_payment_url);

        $response->assertRedirect(route('owner.subscription.invoice', $latestInvoice->id));
    }

    public function test_xendit_webhook_paid_activates_subscription_and_extends_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

        $paidPlan = Plan::query()->where('price_monthly', '>', 0)->firstOrFail();
        $sub = $this->tenant->activeSubscription();

        // Simulasikan tenant suspended karena telat bayar
        $this->tenant->update(['status' => TenantStatus::Suspended]);

        $invoice = SubscriptionInvoice::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $sub?->id,
            'target_plan_id' => $paidPlan->id,
            'type' => 'renewal',
            'number' => 'INV-SUB-TEST-001',
            'amount' => $paidPlan->price_monthly,
            'period_start' => now(),
            'period_end' => now()->addDays(30),
            'status' => 'unpaid',
            'due_at' => now()->addDays(2),
            'gateway' => 'xendit',
            'xendit_invoice_id' => 'xnd_inv_sub_123',
            'xendit_external_id' => 'BOS-SUB-INV-SUB-TEST-001-999',
            'xendit_payment_url' => 'https://checkout.xendit.co/web/xnd_inv_sub_123',
        ]);

        $payload = [
            'id' => 'xnd_inv_sub_123',
            'external_id' => 'BOS-SUB-INV-SUB-TEST-001-999',
            'status' => 'PAID',
            'amount' => $paidPlan->price_monthly,
            'payment_method' => 'QRIS',
            'payment_channel' => 'QRIS',
            'paid_at' => '2026-11-06T10:05:00.000Z',
        ];

        $response = $this->postJson(route('api.webhooks.xendit.invoice'), $payload, [
            'x-callback-token' => 'sub_webhook_secret_token',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription activated successfully',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->tenant->refresh();
        $this->assertSame(TenantStatus::Active, $this->tenant->status);

        $activeSub = $this->tenant->activeSubscription();
        $this->assertNotNull($activeSub);
        $this->assertSame(SubscriptionStatus::Active, $activeSub->status);
        $this->assertTrue($activeSub->ends_at->isFuture());
    }

    public function test_xendit_webhook_subscription_is_idempotent(): void
    {
        $paidPlan = Plan::query()->where('price_monthly', '>', 0)->firstOrFail();
        $sub = $this->tenant->activeSubscription();

        $invoice = SubscriptionInvoice::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $sub?->id,
            'target_plan_id' => $paidPlan->id,
            'type' => 'renewal',
            'number' => 'INV-SUB-IDEM-001',
            'amount' => $paidPlan->price_monthly,
            'status' => 'paid', // sudah lunas
            'due_at' => now()->addDays(2),
            'paid_at' => now(),
            'gateway' => 'xendit',
            'xendit_invoice_id' => 'xnd_inv_sub_idem',
            'xendit_external_id' => 'BOS-SUB-INV-SUB-IDEM-001-888',
        ]);

        $payload = [
            'id' => 'xnd_inv_sub_idem',
            'external_id' => 'BOS-SUB-INV-SUB-IDEM-001-888',
            'status' => 'PAID',
            'amount' => $paidPlan->price_monthly,
        ];

        $response = $this->postJson(route('api.webhooks.xendit.invoice'), $payload, [
            'x-callback-token' => 'sub_webhook_secret_token',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription invoice already paid',
        ]);
    }

    public function test_xendit_webhook_unauthorized_token_is_rejected(): void
    {
        $payload = [
            'id' => 'xnd_inv_unauth',
            'external_id' => 'BOS-SUB-INV-SUB-UNAUTH-001',
            'status' => 'PAID',
        ];

        $response = $this->postJson(route('api.webhooks.xendit.invoice'), $payload, [
            'x-callback-token' => 'wrong_token',
        ]);

        $response->assertStatus(401);
    }
}
