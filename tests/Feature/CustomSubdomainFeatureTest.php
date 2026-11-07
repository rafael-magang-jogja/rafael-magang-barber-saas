<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomSubdomainFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->tenant = Tenant::query()->firstOrFail();
        $this->owner = $this->tenant->owner;
    }

    public function test_portal_uses_standard_path_and_is_displayed_in_settings(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.settings'));
        $response->assertOk();
        $response->assertSee('Link Portal Pelanggan &amp; Booking Online', false);
        $response->assertSee("/b/{$this->tenant->slug}");
        $this->assertStringContainsString("/b/{$this->tenant->slug}", $this->tenant->customerPortalUrl());
    }

    public function test_subdomain_or_foreign_host_automatically_redirects_to_standard_root_portal(): void
    {
        // 1. Akses root di subdomain tenant langsung dialihkan ke /b/{slug} di domain utama normal
        $response = $this->get("http://{$this->tenant->slug}.localhost:8000/");
        $response->assertRedirect("http://localhost:8000/b/{$this->tenant->slug}");

        // 2. Akses login di subdomain tenant langsung dialihkan ke /b/{slug}/login
        $loginResponse = $this->get("http://{$this->tenant->slug}.localhost:8000/login");
        $loginResponse->assertRedirect("http://localhost:8000/b/{$this->tenant->slug}/login");

        // 3. Akses tv display di subdomain tenant langsung dialihkan ke /b/{slug}/tv/SUDIRMAN
        $tvResponse = $this->get("http://{$this->tenant->slug}.localhost:8000/tv/SUDIRMAN");
        $tvResponse->assertRedirect("http://localhost:8000/b/{$this->tenant->slug}/tv/SUDIRMAN");

        // 4. Akses dengan query param tetap dibawa ke /b/{slug}
        $queryResponse = $this->get("http://{$this->tenant->slug}.localhost:8000/?tab=booking");
        $queryResponse->assertRedirect("http://localhost:8000/b/{$this->tenant->slug}?tab=booking");
    }

    public function test_logout_redirects_to_normal_login(): void
    {
        $logoutResponse = $this->actingAs($this->owner)->post(route('logout'));
        $logoutResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_white_label_is_exclusive_to_business_plan(): void
    {
        $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();
        Subscription::query()->where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $starterPlan->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->tenant->fresh()->hasFeature('white_label'));

        $businessPlan = Plan::query()->where('code', 'business')->firstOrFail();
        Subscription::query()->where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $businessPlan->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->tenant->fresh()->hasFeature('white_label'));
        $this->assertTrue($this->tenant->fresh()->hasFeature('custom_favicon'));
    }
}
