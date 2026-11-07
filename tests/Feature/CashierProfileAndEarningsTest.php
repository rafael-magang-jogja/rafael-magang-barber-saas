<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CashierProfileAndEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_can_view_earnings_page_and_filter_period(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.earnings'));
        $response->assertOk();
        $response->assertSee('Pendapatan &amp; Transaksi Kasir', false);
        $response->assertSee('Filter Periode Performa');
        $response->assertSee('Total Omzet Transaksi POS');

        // Test filter today
        $responseToday = $this->actingAs($cashier)->get(route('cashier.earnings', ['period' => 'today']));
        $responseToday->assertOk();
        $responseToday->assertSee('Hari Ini');

        // Test filter 7days
        $responseWeek = $this->actingAs($cashier)->get(route('cashier.earnings', ['period' => '7days']));
        $responseWeek->assertOk();
    }

    public function test_cashier_can_view_profile_page(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.profile'));
        $response->assertOk();
        $response->assertSee('Profil Kasir');
        $response->assertSee('Kasir Aktif');
        $response->assertSee('Detail Penugasan');
        $response->assertSee('Keamanan Akun');
        $response->assertSee('Edit Profil');
    }

    public function test_cashier_can_view_edit_profile_page(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.profile.edit'));
        $response->assertOk();
        $response->assertSee('Edit Profil');
        $response->assertSee('Nama Tampilan Kasir');
        $response->assertSee('Email Login');
    }

    public function test_cashier_can_update_profile(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->put(route('cashier.profile.update'), [
            'name' => 'Kasir Sudirman Update',
            'display_name' => 'Kasir Top Sudirman',
            'email' => 'kasir.baru@barberking.id',
            'phone' => '081299998888',
        ]);

        $response->assertRedirect(route('cashier.profile'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $cashier->id,
            'name' => 'Kasir Sudirman Update',
            'email' => 'kasir.baru@barberking.id',
            'phone' => '081299998888',
        ]);

        $this->assertDatabaseHas('memberships', [
            'user_id' => $cashier->id,
            'display_name' => 'Kasir Top Sudirman',
        ]);
    }

    public function test_cashier_can_update_password_with_valid_current_password(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->put(route('cashier.profile.password'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('cashier.profile'));
        $response->assertSessionHas('success');

        $cashier->refresh();
        $this->assertTrue(Hash::check('newpassword123', $cashier->password));
    }

    public function test_cashier_cannot_update_password_with_wrong_current_password(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->put(route('cashier.profile.password'), [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_cashier_recent_orders_renders_sequential_numbering_without_hash_or_id(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.earnings'));
        $response->assertOk();
        $response->assertSee('Riwayat Transaksi Terakhir');
        $response->assertSee('Tanggal &amp; Waktu', false);
        $response->assertDontSee('#11');
        $response->assertDontSee('#10');
    }
}
