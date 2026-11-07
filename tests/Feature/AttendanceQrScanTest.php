<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
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

    public function test_owner_schedule_settings_no_longer_renders_branch_qr_code(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('owner.schedule-settings'));

        $response->assertOk();
        $response->assertDontSee('QR Absensi & Check-in Staf');
        $response->assertDontSee('Unduh Gambar (PNG)');
        $response->assertDontSee('Cetak Standee / Poster');
        $response->assertDontSee('modal-print-standee');
        $response->assertDontSee('modal-confirm-reset-qr');
    }

    public function test_owner_can_view_scanner_and_scan_staff_qr_for_check_in_and_check_out(): void
    {
        $owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = \App\Models\Membership::query()->where('user_id', $barber->id)->firstOrFail();
        \App\Models\Attendance::query()->where('membership_id', $membership->id)->delete();

        $branch = Branch::query()->firstOrFail();

        // 1. Owner membuka halaman Scanner Absen Karyawan
        $responseScanner = $this->actingAs($owner)->get(route('owner.attendances.scanner'));
        $responseScanner->assertOk();
        $responseScanner->assertSee('Pemindai QR Absensi Karyawan');
        $responseScanner->assertSee('Riwayat Scan Hari Ini');

        // 2. Barber menghasilkan payload QR absensi
        $qrPayload = $membership->getAttendanceQrPayload();

        // 3. Owner memindai QR staf untuk Check-In (Masuk)
        $responseCheckIn = $this->actingAs($owner)->postJson(route('owner.attendances.scanner.process'), [
            'code' => $qrPayload,
            'branch_id' => $branch->id,
        ]);

        $responseCheckIn->assertOk();
        $responseCheckIn->assertJson([
            'success' => true,
            'action' => 'check_in',
        ]);

        $this->assertDatabaseHas('attendances', [
            'branch_id' => $branch->id,
            'membership_id' => $membership->id,
            'check_out_at' => null,
        ]);

        // 4. Owner memindai QR staf kembali untuk Check-Out (Pulang)
        $responseCheckOut = $this->actingAs($owner)->postJson(route('owner.attendances.scanner.process'), [
            'code' => $qrPayload,
            'branch_id' => $branch->id,
        ]);

        $responseCheckOut->assertOk();
        $responseCheckOut->assertJson([
            'success' => true,
            'action' => 'check_out',
        ]);

        $this->assertNotNull(\App\Models\Attendance::query()->where('membership_id', $membership->id)->first()->check_out_at);
    }

    public function test_direct_get_attendance_page_is_disabled_and_returns_404(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $branch = Branch::query()->firstOrFail();

        // Halaman GET absensi manual dinonaktifkan sehingga direct URL mengembalikan 404
        $response = $this->actingAs($barber)->get('/attend/' . $branch->qr_token);
        $response->assertNotFound();
    }

    public function test_staff_can_clock_in_and_clock_out_via_qr_scan(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();
        $membership = \App\Models\Membership::query()->where('user_id', $barber->id)->firstOrFail();
        \App\Models\Attendance::query()->where('membership_id', $membership->id)->delete();

        $branch = Branch::query()->firstOrFail();

        // 1. Clock In (Absen Masuk) with branch coordinates via in-app scanner POST
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

        // 2. Clock Out (Absen Pulang) with branch coordinates via in-app scanner POST
        $responseCheckOut = $this->actingAs($barber)->post(route('attend.record', $branch->qr_token), [
            'action' => 'check_out',
            'lat' => $branch->lat ?? -6.2088,
            'lng' => $branch->lng ?? 106.8456,
        ]);

        $responseCheckOut->assertSessionHas('status');
        $this->assertDatabaseHas('attendances', [
            'branch_id' => $branch->id,
            'membership_id' => $membership->id,
        ]);
    }

    public function test_cashier_can_access_dedicated_attendance_and_schedule_page(): void
    {
        $cashier = User::query()->where('email', 'kasir.sudirman@barberking.id')->firstOrFail();

        $response = $this->actingAs($cashier)->get(route('cashier.attendance'));

        $response->assertOk();
        $response->assertSee('Absensi & Jadwal Kerja');
        $response->assertSee('Tampilkan QR Absen Saya');
        $response->assertSee('modal-staff-qr-code');
        $response->assertSee('Jadwal Shift Kerja Saya');
        $response->assertSee('Riwayat &amp; Kehadiran', false);

        // Filter periode sama seperti owner, default 'today'
        $this->assertEquals('today', $response->viewData('period'));
        $response->assertSee('Hari Ini');
        $response->assertSee('7 Hari');
        $response->assertSee('Bulan Ini');

        // Test filter ke 7days
        $response7Days = $this->actingAs($cashier)->get(route('cashier.attendance', ['period' => '7days']));
        $response7Days->assertOk();
        $this->assertEquals('7days', $response7Days->viewData('period'));
    }

    public function test_barber_can_access_dedicated_attendance_and_schedule_page(): void
    {
        $barber = User::query()->where('email', 'andi@barberking.id')->firstOrFail();

        $response = $this->actingAs($barber)->get(route('barber.attendance'));

        $response->assertOk();
        $response->assertSee('Absensi Hari Ini');
        $response->assertSee('Tampilkan QR Absen Saya');
        $response->assertSee('modal-staff-qr-code');
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
