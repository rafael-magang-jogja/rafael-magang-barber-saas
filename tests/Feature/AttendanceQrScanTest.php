<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AttendanceQrScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::today()->setTime(10, 0, 0));
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_schedule_settings_renders_branch_qr_code_card_and_print_options(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('owner.schedule-settings'));

        $response->assertOk();
        $response->assertSee('QR Absensi & Check-in Staf');
        $response->assertSee('Unduh Gambar (PNG)');
        $response->assertSee('Cetak Standee / Poster');
        $response->assertSee('modal-print-standee');
    }

    public function test_staff_barber_can_view_qr_scan_confirmation_page(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = \App\Models\Membership::query()->where('user_id', $barber->id)->firstOrFail();
        \App\Models\Attendance::query()->where('membership_id', $membership->id)->delete();

        $branch = Branch::query()->firstOrFail();

        $response = $this->actingAs($barber)->get(route('attend.scan', $branch->qr_token));

        $response->assertOk();
        $response->assertSee('Absensi Staf');
        $response->assertSee($branch->name);
        $response->assertSee('Konfirmasi Absen Masuk');
    }

    public function test_staff_can_clock_in_and_clock_out_via_qr_scan(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = \App\Models\Membership::query()->where('user_id', $barber->id)->firstOrFail();
        \App\Models\Attendance::query()->where('membership_id', $membership->id)->delete();

        $branch = Branch::query()->firstOrFail();

        // 1. Clock In (Absen Masuk) with branch coordinates
        $responseCheckIn = $this->actingAs($barber)->post(route('attend.record', $branch->qr_token), [
            'action' => 'check_in',
            'lat' => $branch->lat ?? -6.2088,
            'lng' => $branch->lng ?? 106.8456,
        ]);

        $responseCheckIn->assertSessionHas('status');
        $this->assertDatabaseHas('attendances', [
            'branch_id' => $branch->id,
            'membership_id' => $membership->id,
        ]);

        // 2. View scan confirmation should now show Clock Out option
        $responseView = $this->actingAs($barber)->get(route('attend.scan', $branch->qr_token));
        $responseView->assertOk();
        $responseView->assertSee('Konfirmasi Absen Pulang');

        // 3. Clock Out (Absen Pulang) with branch coordinates
        $responseCheckOut = $this->actingAs($barber)->post(route('attend.record', $branch->qr_token), [
            'action' => 'check_out',
            'lat' => $branch->lat ?? -6.2088,
            'lng' => $branch->lng ?? 106.8456,
        ]);

        $responseCheckOut->assertSessionHas('status');
    }

    public function test_cashier_can_access_dedicated_attendance_and_schedule_page(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.attendance'));

        $response->assertOk();
        $response->assertSee('Absensi & Jadwal Kerja');
        $response->assertSee('Jadwal Shift Kerja Saya');
        $response->assertSee('Jadwal &amp; Kehadiran Barber Cabang Hari Ini', false);
    }

    public function test_barber_can_access_dedicated_attendance_and_schedule_page(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barber)->get(route('barber.attendance'));

        $response->assertOk();
        $response->assertSee('Absensi Hari Ini');
        $response->assertSee('Jadwal Shift Mingguan Saya');
        $response->assertSee('Riwayat Absensi (14 Hari)');
    }

    public function test_attendance_fails_when_user_is_outside_branch_gps_radius(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = \App\Models\Membership::query()->where('user_id', $barber->id)->firstOrFail();
        \App\Models\Attendance::query()->where('membership_id', $membership->id)->delete();

        $branch = Branch::query()->whereNotNull('lat')->firstOrFail();

        // 1. Submit dengan koordinat jauh di luar radius (misal ~10 km jauhnya)
        $responseFail = $this->actingAs($barber)->post(route('attend.record', $branch->qr_token), [
            'action' => 'check_in',
            'lat' => -6.350000,
            'lng' => 106.950000,
        ]);

        $responseFail->assertSessionHasErrors(['attendance']);

        // 2. Submit dengan koordinat tepat di titik cabang
        $responseSuccess = $this->actingAs($barber)->post(route('attend.record', $branch->qr_token), [
            'action' => 'check_in',
            'lat' => $branch->lat,
            'lng' => $branch->lng,
        ]);

        $responseSuccess->assertSessionHas('status');
    }
}
