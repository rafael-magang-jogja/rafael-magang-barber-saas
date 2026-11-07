<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerPortalThreeModesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_official_mode_renders_platform_theme_and_full_tenant_selection(): void
    {
        // Pelanggan netral yang belum scan QR outlet tertentu & belum memiliki antrean aktif
        $neutralCustomer = User::factory()->create();

        // 1. Dashboard Official Platform
        $dashResponse = $this->actingAs($neutralCustomer)->get(route('customer.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Produk Rekomendasi');
        $dashResponse->assertSee('Pilihan Barbershop Mitra');
        $dashResponse->assertSee('Buka Maps');
        $dashResponse->assertDontSee('Buka Portal Barbershop');

        // 2. Booking Official
        $response = $this->actingAs($neutralCustomer)->get(route('customer.booking'));
        $response->assertOk();
        $response->assertSee('Pilih Barbershop');
        $response->assertDontSee('Portal Resmi Eksklusif');
        $response->assertDontSee('Tersinkron dari QR Barbershop');
    }

    public function test_starter_tenant_renders_official_platform_theme_color(): void
    {
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();

        // Set tenant ke Starter
        $sub = $tenant->activeSubscription();
        $sub?->update(['plan_id' => $starterPlan->id]);

        $response = $this->get(route('customer.portal', $tenant->slug));

        $response->assertOk();
        // Starter tidak memiliki fitur custom brand_color, sehingga menggunakan warna resmi platform
        $response->assertSee('--tenant-brand: #D97706', false);
        $response->assertDontSee('Powered by BarberOS');
    }

    public function test_pro_tenant_renders_custom_brand_color_on_portal_while_customer_pages_stay_official(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();

        // 1. Sebelum scan QR, menu booking customer membuka halaman booking platform resmi
        $bookingResponse = $this->actingAs($customer)->get(route('customer.booking'));
        $bookingResponse->assertOk();
        $bookingResponse->assertSee('Booking Jadwal');
        $bookingResponse->assertSee('Pilih Barbershop');
        $bookingResponse->assertSee('BarberOS Pelanggan');
        $bookingResponse->assertDontSee('Tersinkron dari QR Barbershop');

        // 2. Kunjungi portal QR tenant Pro: tampilan mengikuti brand barbershop dan mengunci sesi ke tenant tersebut
        $portalResponse = $this->actingAs($customer)->get(route('customer.portal', $tenant->slug));
        $portalResponse->assertOk();
        $portalResponse->assertSee('--tenant-brand: #D97706', false);
        $portalResponse->assertSee('Powered by BarberOS');

        // 3. Setelah scan QR, akses ke /customer/booking diarahkan ke tab booking portal tenant yang di-scan
        $afterScanBookingResponse = $this->actingAs($customer)->get(route('customer.booking'));
        $afterScanBookingResponse->assertRedirect($tenant->customerPortalUrl(null, ['tab' => 'booking']));
    }

    public function test_business_subdomain_renders_whitelabel_and_exclusive_mode(): void
    {
        $customer = User::query()->where('email', 'rafael@customer.id')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $businessPlan = Plan::query()->where('code', 'business')->firstOrFail();

        // Set tenant ke Business
        $sub = $tenant->activeSubscription();
        $sub?->update(['plan_id' => $businessPlan->id]);

        // 1. Kunjungi Subdomain Bisnis: http://barber-king.localhost
        $subdomainHome = $this->followingRedirects()->get('http://barber-king.localhost/');
        $subdomainHome->assertOk();
        $subdomainHome->assertSee($tenant->name);
        // White-label: tidak menampilkan "Powered by BarberOS"
        $subdomainHome->assertDontSee('Powered by BarberOS');

        // 2. Kunjungi Layar TV via Subdomain Langsung: http://barber-king.localhost/tv/SDR
        $tvResponse = $this->followingRedirects()->get('http://barber-king.localhost/tv/SDR');
        $tvResponse->assertOk();
        $tvResponse->assertSee('SDR');

        // 3. Pelanggan pada subdomain Business buka /customer/booking: diarahkan ke portal booking subdomain tenant tersebut
        $bookingResponse = $this->actingAs($customer)->get('http://barber-king.localhost/customer/booking');
        $bookingResponse->assertRedirect('http://localhost/b/barber-king?tab=booking');
    }

    public function test_guest_mode_maintains_consistent_theme_and_navigation_between_portal_and_ticket(): void
    {
        $this->travelTo(now()->setTime(11, 0));

        $tenant = Tenant::query()->where('slug', 'barber-king')->firstOrFail();
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();
        $tenant->activeSubscription()?->update(['plan_id' => $proPlan->id]);
        $tenant->update(['brand_color' => '#1E3A8A']);

        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $service = \App\Models\Service::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // 1. Guest buka beranda barbershop via QR
        $portalResponse = $this->get(route('customer.portal', $tenant->slug));
        $portalResponse->assertOk();
        $portalResponse->assertSee('--tenant-brand: #1E3A8A', false);
        $portalResponse->assertSee('Beranda');
        $portalResponse->assertSee(route('customer.portal', ['tenant' => $tenant->slug, 'tab' => 'booking']));

        // 2. Guest ambil antrean
        $queueResponse = $this->post(route('customer.queue.store', $tenant->slug), [
            'branch_id' => $branch->id,
            'customer_name' => 'Tamu Barbershop',
            'customer_phone' => '081234567890',
            'service_ids' => [$service->id],
        ]);
        $ticket = \App\Models\QueueTicket::query()->latest('id')->firstOrFail();
        $queueResponse->assertRedirect(route('customer.tickets.show', $ticket->id));

        // 3. Guest melihat detail tiket: warna tema & navigasi tetap konsisten dengan tenant
        $ticketResponse = $this->withSession(['guest_ticket_ids' => [$ticket->id]])
            ->get(route('customer.tickets.show', $ticket->id));
        $ticketResponse->assertOk();
        $ticketResponse->assertSee('--tenant-brand: #1E3A8A', false);
        $ticketResponse->assertSee($tenant->name);
        $ticketResponse->assertSee('Tiket Aktif');
        $ticketResponse->assertSee(route('customer.portal', $tenant->slug));
        $ticketResponse->assertSee('Kembali ke Beranda ' . $tenant->name);

        // 4. Jika guest mencoba akses dashboard umum tanpa login, middleware auth mengalihkannya ke login
        $guestDashResponse = $this->get(route('customer.dashboard'));
        $guestDashResponse->assertRedirect(route('login'));
    }
}
