<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\FaviconService;
use App\Support\Platform\PlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlatformBrandingAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $owner;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->superadmin = User::query()->where('email', 'superadmin@barberos.id')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
    }

    public function test_superadmin_can_update_platform_theme_color_and_logo_icon(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('platform.settings.update'), [
            'platform_name' => 'Barber Royal Platform',
            'platform_tagline' => 'Eksklusif Multi Outlet',
            'platform_theme_color' => '#059669',
            'logo_type' => 'icon',
            'logo_icon' => 'scissors',
            'default_trial_days' => 30,
            'is_maintenance' => false,
        ]);

        $response->assertRedirect();
        PlatformConfig::clearCache();

        $this->assertSame('#059669', PlatformConfig::themeColor());
        $this->assertSame('icon', PlatformConfig::logoType());
        $this->assertSame('scissors', PlatformConfig::logoIcon());
        $this->assertFalse(PlatformConfig::isMaintenance());

        // Pastikan berkas favicon.ico dan favicon.svg tercipta di public/favicons
        $this->assertFileExists(public_path('favicons/favicon.ico'));
        $this->assertFileExists(public_path('favicons/favicon.svg'));
        $this->assertFileExists(public_path('favicon.ico'));

        // Cek halaman settings menampilkan tema & icon terpilih
        $this->actingAs($this->superadmin)
            ->get(route('platform.settings'))
            ->assertOk()
            ->assertSee('Barber Royal Platform')
            ->assertSee('#059669')
            ->assertSee('Gunting Barber');
    }

    public function test_superadmin_can_upload_custom_logo_image(): void
    {
        Storage::fake('public');

        $fakeLogo = UploadedFile::fake()->image('custom_logo.png', 200, 200);

        $response = $this->actingAs($this->superadmin)->post(route('platform.settings.update'), [
            'platform_name' => 'Barber Logo Test',
            'platform_theme_color' => '#4F46E5',
            'logo_type' => 'image',
            'logo_image' => $fakeLogo,
            'default_trial_days' => 14,
        ]);

        $response->assertRedirect();
        PlatformConfig::clearCache();

        $this->assertSame('image', PlatformConfig::logoType());
        $this->assertNotNull(PlatformConfig::logoImageUrl());
        $this->assertFileExists(public_path('favicons/favicon.ico'));
    }

    public function test_maintenance_mode_restricts_regular_user_login_but_allows_superadmin(): void
    {
        // 1. Aktifkan Maintenance Mode
        $this->actingAs($this->superadmin)->post(route('platform.settings.update'), [
            'platform_name' => 'BarberOS Platform',
            'platform_theme_color' => '#D97706',
            'logo_type' => 'icon',
            'default_trial_days' => 14,
            'is_maintenance' => true,
            'announcement_banner' => 'Server sedang maintenance darurat!',
        ]);

        PlatformConfig::clearCache();
        $this->assertTrue(PlatformConfig::isMaintenance());

        // 2. User biasa mencoba login -> harus ditolak dengan error pemeliharaan
        auth()->logout();
        $loginResponse = $this->post(route('login.store'), [
            'login' => 'owner@barberking.id',
            'password' => 'password',
        ]);

        $loginResponse->assertSessionHasErrors('login');
        $this->assertGuest();

        // 3. Superadmin mencoba login -> tetap diizinkan
        $adminLoginResponse = $this->post(route('login.store'), [
            'login' => 'superadmin@barberos.id',
            'password' => 'password',
        ]);

        $adminLoginResponse->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($this->superadmin);

        // 4. Halaman login menampilkan banner mode pemeliharaan
        auth()->logout();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Platform Sedang Dalam Pemeliharaan')
            ->assertSee('Server sedang maintenance darurat!');
    }

    public function test_tenant_pro_auto_generates_favicon_with_brand_color(): void
    {
        Storage::fake('public');
        $tenant = Tenant::query()->firstOrFail();

        // Pastikan tenant memiliki fitur brand_color (Pro tier)
        $fakeLogo = UploadedFile::fake()->image('barber_logo.png', 100, 100);

        $response = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => 'Barber King Premium',
            'brand_color' => '#8B5CF6',
            'logo' => $fakeLogo,
            'booking_advance_days' => 7,
            'default_duration_minutes' => 30,
        ]);

        $response->assertRedirect();
        $tenant->refresh();

        $this->assertSame('#8B5CF6', $tenant->brand_color);
        $this->assertNotNull($tenant->logo_path);

        // Favicon tenant ter-generate otomatis di direktori khusus tenant
        $this->assertFileExists(public_path(FaviconService::TENANTS_DIR . "/{$tenant->id}/favicon.ico"));
        $this->assertFileExists(public_path(FaviconService::TENANTS_DIR . "/{$tenant->id}/favicon.svg"));
        $this->assertStringContainsString("favicons/tenants/{$tenant->id}/favicon.ico", $tenant->faviconUrl());
    }

    public function test_tenant_business_can_upload_custom_favicon_for_subdomain(): void
    {
        Storage::fake('public');
        $tenant = Tenant::query()->firstOrFail();

        // Assign plan Business agar hasFeature('custom_favicon') bernilai true
        $businessPlan = \App\Models\Plan::query()->where('code', 'business')->firstOrFail();
        \App\Models\Subscription::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $businessPlan->id,
            'status' => \App\Enums\SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        $this->assertTrue($tenant->hasFeature('custom_favicon'));

        $fakeFavicon = UploadedFile::fake()->create('custom_favicon.ico', 32, 'image/x-icon');

        $response = $this->actingAs($this->owner)->put(route('owner.settings.update'), [
            'name' => 'Barber King Subdomain',
            'brand_color' => '#10B981',
            'favicon' => $fakeFavicon,
            'booking_advance_days' => 7,
            'default_duration_minutes' => 30,
        ]);

        $response->assertRedirect();
        $tenant->refresh();

        $this->assertNotNull($tenant->settings['favicon_path'] ?? null);
        $this->assertFileExists(public_path(FaviconService::TENANTS_DIR . "/{$tenant->id}/favicon.ico"));
    }
}
