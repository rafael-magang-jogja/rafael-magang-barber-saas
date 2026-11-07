<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SuperadminPlatformFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->superadmin = User::query()->where('email', 'superadmin@barberos.id')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
    }

    public function test_non_superadmin_cannot_access_platform_endpoints(): void
    {
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->owner)->get(route('platform.dashboard'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.plans'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.transactions'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.reports'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.promotions'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.settings'))->assertForbidden();
    }

    public function test_superadmin_can_access_platform_dashboard_and_all_tabs(): void
    {
        $this->actingAs($this->superadmin)->get(route('platform.dashboard'))->assertOk()->assertSee('Platform Superadmin');
        $this->actingAs($this->superadmin)->get(route('platform.plans'))->assertOk()->assertSee('Katalog Paket Langganan SaaS');
        $this->actingAs($this->superadmin)->get(route('platform.transactions'))->assertOk()->assertSee('Omzet GMV');
        $this->actingAs($this->superadmin)->get(route('platform.reports'))->assertOk()->assertSee('Laporan Pertumbuhan Bisnis SaaS');
        $this->actingAs($this->superadmin)->get(route('platform.promotions'))->assertOk()->assertSee('Katalog Iklan & Promosi Produk');
        $this->actingAs($this->superadmin)->get(route('platform.settings'))->assertOk()->assertSee('Identitas Platform SaaS');
    }

    public function test_superadmin_can_update_promotions(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('platform.promotions.update'), [
            'promoted_products_slide_seconds' => 7,
            'promoted_products_whatsapp' => '089912345678',
            'promoted_products' => [
                [
                    'id' => 'prod-test-1',
                    'name' => 'Pomade Premium Wangi Kopi',
                    'tagline' => 'Aroma Kopi Mewah Tahan Seharian',
                    'price' => 'Rp80.000',
                    'original_price' => 'Rp100.000',
                    'price_color' => 'text-amber-400',
                    'is_active' => '1',
                    'stock_status' => 'habis',
                    'desc' => 'Pomade berkualitas aroma kopi mantap.',
                    'features' => "Wangi kopi segar\nTahan 12 jam\nWaterbased",
                ],
            ],
        ]);

        $response->assertRedirect(route('platform.promotions'));
        $response->assertSessionHas('status');

        $this->actingAs($this->superadmin)
            ->get(route('platform.promotions'))
            ->assertOk()
            ->assertSee('Pomade Premium Wangi Kopi')
            ->assertSee('Rp80.000')
            ->assertSee('Stok Habis');
    }

    public function test_superadmin_promotion_rejects_invalid_file_format_or_large_size(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // File format PDF (tidak diizinkan, hanya PNG/JPG/JPEG/WEBP/SVG)
        $invalidFile = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->superadmin)->post(route('platform.promotions.update'), [
            'promoted_products' => [
                [
                    'name' => 'Pomade Test',
                    'price' => 'Rp50.000',
                    'image_files' => [$invalidFile],
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['promoted_products.0.image_files.0']);

        // File gambar melebihi 2MB (> 2048 KB)
        $oversizedFile = \Illuminate\Http\UploadedFile::fake()->create('huge_banner.png', 3000, 'image/png');

        $responseOversized = $this->actingAs($this->superadmin)->post(route('platform.promotions.update'), [
            'promoted_products' => [
                [
                    'name' => 'Pomade Test',
                    'price' => 'Rp50.000',
                    'image_files' => [$oversizedFile],
                ],
            ],
        ]);

        $responseOversized->assertSessionHasErrors(['promoted_products.0.image_files.0']);
    }

    public function test_superadmin_can_create_new_tenant(): void
    {
        $plan = Plan::query()->firstOrFail();

        $response = $this->actingAs($this->superadmin)->post(route('platform.tenants.store'), [
            'tenant_name' => 'Barber Vintage Test',
            'slug' => 'barber-vintage-test',
            'plan_id' => $plan->id,
            'owner_name' => 'Budi Pemilik',
            'owner_email' => 'budi.pemilik@example.com',
            'owner_phone' => '081299998888',
            'password' => 'password',
            'password_confirmation' => 'password',
            'branch_name' => 'Cabang Vintage Pusat',
            'branch_code' => 'VNT',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::query()->where('slug', 'barber-vintage-test')->firstOrFail();
        $this->assertEquals('Barber Vintage Test', $tenant->name);
        $this->assertEquals(TenantStatus::Active, $tenant->status);

        $ownerUser = User::query()->where('email', 'budi.pemilik@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $ownerUser->password));

        $membership = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $ownerUser->id)
            ->firstOrFail();

        $this->assertEquals(Role::Owner, $membership->role);
    }

    public function test_superadmin_can_view_edit_and_toggle_tenant(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        // View Detail
        $this->actingAs($this->superadmin)
            ->get(route('platform.tenants.show', $tenant))
            ->assertOk()
            ->assertSee($tenant->name);

        // Update Tenant Profile
        $this->actingAs($this->superadmin)
            ->put(route('platform.tenants.update', $tenant), [
                'name' => 'Barber King Updated',
                'slug' => $tenant->slug,
                'status' => 'active',
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertEquals('Barber King Updated', $tenant->name);
        $this->assertEquals(TenantStatus::Active, $tenant->status);

        // Toggle Status (Active -> Suspended -> Active)
        $this->actingAs($this->superadmin)
            ->patch(route('platform.tenants.toggle', $tenant))
            ->assertRedirect();

        $tenant->refresh();
        $this->assertEquals(TenantStatus::Suspended, $tenant->status);

        $this->actingAs($this->superadmin)
            ->patch(route('platform.tenants.toggle', $tenant))
            ->assertRedirect();

        $tenant->refresh();
        $this->assertEquals(TenantStatus::Active, $tenant->status);
    }

    public function test_superadmin_can_extend_subscription_and_reset_password(): void
    {
        $tenant = Tenant::query()->with('owner')->firstOrFail();
        $plan = Plan::query()->firstOrFail();

        // Extend Subscription
        $response = $this->actingAs($this->superadmin)
            ->post(route('platform.tenants.subscription', $tenant), [
                'plan_id' => $plan->id,
                'extension_days' => '30',
                'status' => 'active',
            ]);

        $response->assertRedirect();

        // Reset Owner Password
        $response = $this->actingAs($this->superadmin)
            ->post(route('platform.tenants.reset-password', $tenant), [
                'new_password' => 'newsecretpass123',
                'new_password_confirmation' => 'newsecretpass123',
            ]);

        $response->assertRedirect();

        $tenant->owner->refresh();
        $this->assertTrue(Hash::check('newsecretpass123', $tenant->owner->password));
    }

    public function test_superadmin_can_manage_plans(): void
    {
        // Create new plan
        $this->actingAs($this->superadmin)->post(route('platform.plans.store'), [
            'name' => 'Paket Ultra Barbershop',
            'code' => 'ultra',
            'price_monthly' => 599000,
            'max_branches' => 20,
            'max_staff' => 50,
            'description' => 'Paket skala enterprise untuk barbershop jaringan nasional.',
        ])->assertRedirect();

        $plan = Plan::query()->where('code', 'ultra')->firstOrFail();
        $this->assertEquals(599000, $plan->price_monthly);

        // Access dedicated edit page
        $this->actingAs($this->superadmin)
            ->get(route('platform.plans.edit', $plan))
            ->assertOk()
            ->assertSee('Edit Paket')
            ->assertSee('Paket Ultra Barbershop');

        // Update plan
        $this->actingAs($this->superadmin)->put(route('platform.plans.update', $plan), [
            'name' => 'Paket Ultra Enterprise',
            'code' => 'ultra-enterprise',
            'price_monthly' => 699000,
            'max_branches' => 30,
            'max_staff' => 100,
            'is_active' => true,
        ])->assertRedirect(route('platform.plans'));

        $plan->refresh();
        $this->assertEquals(699000, $plan->price_monthly);

        // Toggle plan status (deactivate/activate without deleting)
        $this->actingAs($this->superadmin)
            ->patch(route('platform.plans.toggle', $plan))
            ->assertRedirect();

        $plan->refresh();
        $this->assertFalse($plan->is_active);

        // Page view contains standard fixed plans
        $viewResponse = $this->actingAs($this->superadmin)->get(route('platform.plans'));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Starter');
        $viewResponse->assertSee('Pro');
        $viewResponse->assertSee('Business');
    }

    public function test_superadmin_can_export_transactions_and_reports_csv(): void
    {
        $transactionsExport = $this->actingAs($this->superadmin)
            ->get(route('platform.transactions.export'));
        $transactionsExport->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $transactionsExport->headers->get('content-type'));

        $reportsExport = $this->actingAs($this->superadmin)
            ->get(route('platform.reports.export'));
        $reportsExport->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $reportsExport->headers->get('content-type'));
    }

    public function test_superadmin_can_update_platform_settings(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('platform.settings.update'), [
            'platform_name' => 'BarberOS Enterprise Hub',
            'platform_tagline' => 'Solusi Multi-Outlet Modern',
            'support_email' => 'help@barberos.id',
            'support_phone' => '081234567890',
            'default_trial_days' => 21,
            'announcement_banner' => 'Perawatan server Sabtu malam.',
            'is_maintenance' => false,
        ]);

        $response->assertRedirect();

        $this->actingAs($this->superadmin)
            ->get(route('platform.settings'))
            ->assertOk()
            ->assertSee('BarberOS Enterprise Hub')
            ->assertSee('help@barberos.id')
            ->assertSee('21');
    }

    public function test_superadmin_sees_pending_subscription_payment_confirmation_notification(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();
        $subscription = \App\Models\Subscription::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Buat tagihan langganan dengan status pending (mengajukan konfirmasi)
        \App\Models\SubscriptionInvoice::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'target_plan_id' => $proPlan->id,
            'number' => 'INV-SUB-TEST-001',
            'amount' => 199000,
            'status' => 'pending',
            'gateway' => 'manual_transfer',
            'payment_method' => 'bank_transfer',
            'proof_path' => 'proofs/test_transfer.jpg',
            'due_at' => now()->addDays(2),
        ]);

        // 2. Akses halaman Tagihan & Konfirmasi
        $response = $this->actingAs($this->superadmin)->get(route('platform.subscriptions'));
        $response->assertOk();
        $response->assertSee('Perlu tindakan Superadmin');
        $response->assertSee('Menunggu Konfirmasi (1)');

        // 3. Akses halaman Dashboard Superadmin
        $dashResponse = $this->actingAs($this->superadmin)->get(route('platform.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('1 Pengajuan Konfirmasi Pembayaran Langganan');
    }

    public function test_superadmin_theme_color_and_favicon_are_strictly_isolated_from_tenants(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update([
            'brand_color' => '#8A2BE2',
            'settings' => array_merge($tenant->settings ?? [], [
                'favicon_path' => 'favicons/custom_tenant_favicon.png',
            ]),
        ]);

        $platformColor = \App\Support\Platform\PlatformConfig::themeColor();
        $platformFavicon = \App\Support\Platform\PlatformConfig::faviconUrl();

        // Simulasikan session yang sebelumnya pernah mengunjungi customer tenant
        $response = $this->actingAs($this->superadmin)
            ->withSession([
                'active_tenant_id' => $tenant->id,
                'active_customer_tenant_id' => $tenant->id,
            ])
            ->get(route('platform.dashboard'));

        $response->assertOk();
        // Superadmin harus menggunakan tema platform resmi, bukan warna tenant
        $response->assertSee('--tenant-brand: ' . $platformColor, false);
        $response->assertDontSee('--tenant-brand: #8A2BE2', false);
        // Favicon harus official platform favicon, bukan milik tenant
        $response->assertSee('href="' . \App\Support\Platform\PlatformConfig::faviconUrl() . '"', false);
        $response->assertDontSee('custom_tenant_favicon.png');

        // Bahkan saat membuka halaman detail tenant /platform/tenants/{tenant}
        $detailResponse = $this->actingAs($this->superadmin)
            ->get(route('platform.tenants.show', $tenant));

        $detailResponse->assertOk();
        $detailResponse->assertSee('--tenant-brand: ' . $platformColor, false);
        $detailResponse->assertSee('href="' . \App\Support\Platform\PlatformConfig::faviconUrl() . '"', false);
        $detailResponse->assertDontSee('href="' . $tenant->faviconUrl() . '"', false);
    }
}
