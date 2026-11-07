<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AuthAndRoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_page_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Selamat datang');
    }

    public function test_owner_can_login_and_is_redirected_to_owner_dashboard(): void
    {
        $response = $this->post('/login', [
            'login' => 'owner@barberking.id',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('owner.dashboard'));

        $this->get('/owner')->assertOk()->assertSee('Dashboard Owner');
    }

    public function test_user_can_login_with_remember_me(): void
    {
        $user = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->post('/login', [
            'login' => 'owner@barberking.id',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        /** @var \Illuminate\Auth\SessionGuard $guard */
        $guard = Auth::guard();
        $response->assertCookie($guard->getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_barber_cannot_access_owner_or_platform_dashboard(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $this->actingAs($barber)->get('/barber')->assertOk();
        $this->actingAs($barber)->get('/owner')->assertForbidden();
        $this->actingAs($barber)->get('/platform')->assertForbidden();
    }

    public function test_public_customer_portal_and_tv_display_render_for_tenant(): void
    {
        $this->get('/b/barber-king')->assertOk()->assertSee('Barber King');
        $this->get('/b/barber-king/tv/SDR')->assertOk()->assertSee('SEDANG DIPANGGIL');
    }

    public function test_all_role_dashboards_and_owner_management_pages_render_without_error(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@barberos.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $this->actingAs($superadmin)->get(route('platform.dashboard'))->assertOk()->assertSee('Platform Superadmin');

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('owner.branches'))->assertOk();
        $this->actingAs($owner)->get(route('owner.staff'))->assertOk();
        $this->actingAs($owner)->get(route('owner.services'))->assertOk();
        $this->actingAs($owner)->get(route('owner.reports'))->assertOk();

        $this->actingAs($cashier)->get(route('cashier.dashboard'))->assertOk()->assertSee('Papan Antrean & POS');
    }

    public function test_owner_cannot_open_customer_portal_and_retains_owner_sidebar_on_cashier_page(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        // Owner diblokir dari Portal Customer
        $this->actingAs($owner)->get('/b/barber-king')->assertForbidden();

        // Saat Owner membuka halaman Antrean & Kasir, sidebar tetap menampilkan role & menu Owner
        $this->actingAs($owner)
            ->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertSee('Owner Barbershop')
            ->assertSee('Dashboard')
            ->assertSee('Cabang')
            ->assertSee('Laporan');

        // Sub-menu dropdown Antrean & Kasir dapat diakses dengan lancar oleh Owner maupun Admin/Kasir
        $this->actingAs($owner)->get(route('cashier.walk-in'))->assertOk()->assertSee('Input Antrean Walk-in Baru');
        $this->actingAs($owner)->get(route('cashier.chairs'))->assertRedirect(route('cashier.dashboard'));
        $this->actingAs($owner)->get(route('cashier.dashboard'))->assertOk()->assertSee('Monitor Kursi Barber');
    }
}
