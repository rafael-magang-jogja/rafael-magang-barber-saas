<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\AttendanceClock;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AttendanceClockLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_attendance_clock_renders_wire_poll_60s_and_current_time(): void
    {
        Livewire::test(AttendanceClock::class)
            ->assertSeeHtml('wire:poll.60s')
            ->assertSee('WIB')
            ->assertSee(now()->format('H:i'));
    }

    public function test_attendance_clock_with_pulse_renders_indicator(): void
    {
        Livewire::test(AttendanceClock::class, ['showPulse' => true])
            ->assertSeeHtml('wire:poll.60s')
            ->assertSeeHtml('animate-pulse')
            ->assertSee('WIB');
    }

    public function test_barber_attendance_dashboard_renders_livewire_clock_and_clean_scanner(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barber)->get('/barber/attendance');

        $response->assertOk();
        $response->assertSee('wire:poll.60s', false);
        $response->assertSee('Absensi Hari Ini', false);
        $response->assertSee('Tampilkan QR Absen Saya', false);
    }

    public function test_cashier_attendance_dashboard_renders_livewire_clock(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get('/cashier/attendance');

        $response->assertOk();
        $response->assertSee('wire:poll.60s', false);
        $response->assertSee('Absensi &amp; Jadwal Kerja', false);
    }

    public function test_scan_attendance_with_invalid_or_foreign_qr_returns_simple_clear_note(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        // Staf salah memindai QR code antrean pelanggan
        $response = $this->actingAs($barber)->postJson('/attend/' . urlencode('http://127.0.0.1:8000/b/barber-king?branch=SDR'), [
            'action' => 'check_in',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'message' => 'Gunakan QR code absensi resmi outlet.',
        ]);
    }

    public function test_sidebar_and_bottom_nav_render_attendance_status_dots(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        // 1. Sebelum absen: lingkaran kuning paten (animate-pulse / belum absen)
        $responseBefore = $this->actingAs($cashier)->get('/cashier');
        $responseBefore->assertOk();
        $responseBefore->assertSee('bg-yellow-400 ring-2 ring-yellow-400/20 animate-pulse', false);

        // 2. Buat absensi masuk hari ini untuk kasir
        $membership = \App\Models\Membership::query()->where('user_id', $cashier->id)->firstOrFail();
        \App\Models\Attendance::query()->create([
            'tenant_id' => $membership->tenant_id,
            'branch_id' => $membership->branch_id,
            'membership_id' => $membership->id,
            'date' => now()->format('Y-m-d'),
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        // 3. Setelah absen: lingkaran hijau
        $responseAfter = $this->actingAs($cashier)->get('/cashier');
        $responseAfter->assertOk();
        $responseAfter->assertSee('bg-emerald-500 ring-2 ring-emerald-500/20', false);
    }
}
