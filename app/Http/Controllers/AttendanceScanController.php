<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AttendanceScanController extends Controller
{
    /**
     * Tampilkan halaman konfirmasi absensi hasil scan QR code cabang.
     */
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $branch = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->with(['tenant'])
            ->first();

        if ($branch === null) {
            abort(404, 'Kode QR absensi cabang tidak valid atau sudah tidak aktif.');
        }

        $tenant = $branch->tenant;
        $user = $request->user();

        // Cari membership user pada tenant ini (bisa sebagai barber, admin/kasir, atau owner)
        $membership = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return view('attendance.scan-confirm', [
                'branch' => $branch,
                'tenant' => $tenant,
                'user' => $user,
                'membership' => null,
                'attendance' => null,
                'error' => "Akun Anda ({$user->email}) tidak terdaftar sebagai staf atau pengelola pada {$tenant->name}.",
            ]);
        }

        $today = now()->format('Y-m-d');
        $now = now();
        $attendance = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('membership_id', $membership->id)
            ->whereDate('date', $today)
            ->first();

        $error = null;
        if ($attendance === null || $attendance->check_in_at === null) {
            $dayNum = $now->dayOfWeek;
            $sched = \App\Models\BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->where('day_of_week', $dayNum)
                ->first();

            $openingHours = $branch->opening_hours[$dayNum] ?? null;
            $isOff = false;
            $shiftStartStr = '09:00';

            if ($sched !== null) {
                $isOff = (bool) $sched->is_off;
                $shiftStartStr = $sched->start_time ? substr((string) $sched->start_time, 0, 5) : '09:00';
            } elseif ($openingHours !== null) {
                $isOff = empty($openingHours['is_open']);
                $shiftStartStr = $openingHours['open'] ?? '09:00';
            }

            if ($isOff) {
                $error = 'Belum saatnya absen';
            } else {
                $scheduledStart = Carbon::parse("{$today} {$shiftStartStr}:00");
                $earliestAllowedCheckIn = $scheduledStart->copy()->subMinutes(60);

                if ($now->lessThan($earliestAllowedCheckIn)) {
                    $error = 'Belum saatnya absen';
                }
            }
        }

        return view('attendance.scan-confirm', [
            'branch' => $branch,
            'tenant' => $tenant,
            'user' => $user,
            'membership' => $membership,
            'attendance' => $attendance,
            'error' => $error,
        ]);
    }

    /**
     * Hitung jarak antara dua koordinat GPS menggunakan formula Haversine (dalam meter).
     */
    public static function calculateDistanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Proses pencatatan absensi (Masuk atau Pulang) hasil scan QR.
     */
    public function record(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $branch = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->with(['tenant'])
            ->first();

        if ($branch === null) {
            $msg = 'Kode QR absensi cabang tidak valid atau sudah tidak aktif.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 200);
            }
            return back()->withErrors(['attendance' => $msg]);
        }

        $tenant = $branch->tenant;
        $user = $request->user();

        $membership = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            $msg = 'Anda tidak memiliki hak akses staf untuk melakukan absensi pada cabang ini.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 200);
            }
            return back()->withErrors(['attendance' => $msg]);
        }

        // =========================================================================
        // VALIDASI GPS RADIUS CABANG:
        // Jika cabang memiliki titik koordinat GPS (lat & lng), staf WAJIB berada
        // di dalam batas radius yang diizinkan (default: 50/100 meter).
        // =========================================================================
        $userLat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $userLng = $request->filled('lng') ? (float) $request->input('lng') : null;

        if ($branch->lat !== null && $branch->lng !== null) {
            if ($userLat === null || $userLng === null) {
                $msg = 'Akses lokasi (GPS) diperlukan untuk verifikasi kehadiran. Harap aktifkan izin lokasi di browser.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 200);
                }
                return back()->withErrors(['attendance' => $msg]);
            }

            $maxRadius = $branch->radius_meters > 0 ? (int) $branch->radius_meters : 100;
            $distanceMeters = self::calculateDistanceInMeters(
                $userLat,
                $userLng,
                (float) $branch->lat,
                (float) $branch->lng
            );

            if ($distanceMeters > $maxRadius) {
                $formattedDist = number_format(round($distanceMeters), 0, ',', '.');
                $msg = "Di luar radius outlet ({$formattedDist} m, batas maks: {$maxRadius} m). Absensi harus di lokasi outlet.";
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 200);
                }
                return back()->withErrors(['attendance' => $msg]);
            }
        }

        $action = $request->input('action', 'check_in'); // 'check_in' atau 'check_out'
        $today = now()->format('Y-m-d');
        $now = now();

        $attendance = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('membership_id', $membership->id)
            ->whereDate('date', $today)
            ->first();

        $redirectRoute = match ($membership->role?->value) {
            'barber' => route('barber.attendance'),
            'admin', 'cashier' => route('cashier.attendance'),
            'owner' => route('owner.attendances'),
            default => route('home'),
        };

        if ($action === 'check_out') {
            if ($attendance === null || $attendance->check_in_at === null) {
                $msg = 'Anda belum melakukan absen masuk hari ini.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 200);
                }
                return back()->withErrors(['attendance' => $msg]);
            }

            if ($attendance->check_out_at !== null) {
                $msg = 'Anda sudah absen pulang sebelumnya pada pukul '.$attendance->check_out_at->format('H:i').'.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 200);
                }
                return back()->withErrors(['attendance' => $msg]);
            }

            $attendance->update([
                'check_out_at' => $now,
            ]);

            $msg = 'Absen pulang berhasil dicatat pada pukul '.$now->format('H:i').'. Terima kasih!';
            session()->flash('status', $msg);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'action' => 'check_out',
                    'time' => $now->format('H:i').' WIB',
                ]);
            }

            return redirect($redirectRoute)->with('status', $msg);
        }

        // Action Check-In
        if ($attendance !== null && $attendance->check_in_at !== null) {
            $msg = 'Anda sudah absen masuk hari ini pada pukul '.$attendance->check_in_at->format('H:i').'.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 200);
            }
            return back()->withErrors(['attendance' => $msg]);
        }

        // Cari jadwal shift hari ini (BarberSchedule per membership atau opening_hours cabang)
        $dayNum = $now->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu
        $sched = \App\Models\BarberSchedule::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $membership->id)
            ->where('day_of_week', $dayNum)
            ->first();

        $openingHours = $branch->opening_hours[$dayNum] ?? null;
        $isOff = false;
        $shiftStartStr = '09:00';

        if ($sched !== null) {
            $isOff = (bool) $sched->is_off;
            $shiftStartStr = $sched->start_time ? substr((string) $sched->start_time, 0, 5) : '09:00';
        } elseif ($openingHours !== null) {
            $isOff = empty($openingHours['is_open']);
            $shiftStartStr = $openingHours['open'] ?? '09:00';
        }

        if ($isOff) {
            $msg = 'Belum saatnya absen';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 200);
            }
            return back()->withErrors(['attendance' => $msg]);
        }

        $scheduledStart = Carbon::parse("{$today} {$shiftStartStr}:00");
        $earliestAllowedCheckIn = $scheduledStart->copy()->subMinutes(60);

        // Batasan: Absen masuk hanya bisa dilakukan maksimal 60 menit sebelum jam shift dimulai
        if ($now->lessThan($earliestAllowedCheckIn)) {
            $msg = 'Belum saatnya absen';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 200);
            }
            return back()->withErrors(['attendance' => $msg]);
        }

        $toleranceMinutes = $branch->late_tolerance_minutes ?? 15;
        $lateMinutes = 0;

        if ($now->greaterThan($scheduledStart)) {
            $diffMinutes = (int) $scheduledStart->diffInMinutes($now, false);
            if ($diffMinutes > $toleranceMinutes) {
                $lateMinutes = $diffMinutes;
            }
        }

        $status = $lateMinutes > 0 ? 'late' : 'present';

        if ($attendance !== null) {
            $attendance->update([
                'branch_id' => $branch->id,
                'check_in_at' => $now,
                'check_in_lat' => $request->filled('lat') ? (float) $request->input('lat') : null,
                'check_in_lng' => $request->filled('lng') ? (float) $request->input('lng') : null,
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]);
        } else {
            Attendance::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'membership_id' => $membership->id,
                    'date' => $today,
                    'check_in_at' => $now,
                    'check_in_lat' => $request->filled('lat') ? (float) $request->input('lat') : null,
                    'check_in_lng' => $request->filled('lng') ? (float) $request->input('lng') : null,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                ]);
        }

        $msg = 'Absen masuk berhasil dicatat pada pukul '.$now->format('H:i').'. Selamat bertugas!';
        if ($lateMinutes > 0) {
            $msg .= " (Terlambat {$lateMinutes} menit).";
        }

        session()->flash('status', $msg);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'action' => 'check_in',
                'time' => $now->format('H:i').' WIB',
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]);
        }

        return redirect($redirectRoute)->with('status', $msg);
    }
}
