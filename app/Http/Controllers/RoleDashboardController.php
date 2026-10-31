<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class RoleDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryService $dashboardQueryService,
    ) {}

    public function platform(): View
    {
        return view('dashboards.platform', $this->dashboardQueryService->getPlatformDashboardData());
    }

    public function owner(): View
    {
        return view('dashboards.owner', $this->dashboardQueryService->getOwnerDashboardData());
    }

    public function cashier(Request $request): View
    {
        $dateFilter = (string) $request->input('date_filter', 'today');
        $customDate = $request->input('custom_date');

        $selectedDate = now()->toDateString();
        if ($dateFilter === 'yesterday') {
            $selectedDate = now()->subDay()->toDateString();
        } elseif ($dateFilter === 'custom' && !empty($customDate)) {
            $selectedDate = \Illuminate\Support\Carbon::parse($customDate)->toDateString();
        }

        return view('dashboards.cashier', $this->dashboardQueryService->getCashierBoardData($request->user()?->id, $selectedDate));
    }

    public function cashierWalkIn(): View
    {
        return view('dashboards.cashier', $this->dashboardQueryService->getCashierWalkInData());
    }

    public function cashierBookings(): View
    {
        return view('dashboards.cashier', $this->dashboardQueryService->getCashierBookingsData());
    }

    public function cashierChairs(): RedirectResponse
    {
        return redirect()->route('cashier.dashboard');
    }

    public function cashierAttendance(Request $request): View
    {
        return view('dashboards.cashier-attendance', $this->dashboardQueryService->getCashierAttendanceData($request->user()->id));
    }

    public function barber(Request $request): View
    {
        return view('dashboards.barber', $this->dashboardQueryService->getBarberDashboardData($request->user()?->id));
    }

    public function barberAttendance(Request $request): View
    {
        return view('dashboards.barber-attendance', $this->dashboardQueryService->getBarberAttendanceData($request->user()->id));
    }

    public function barberEarnings(Request $request): View
    {
        return view('dashboards.barber-earnings', $this->dashboardQueryService->getBarberEarningsData(
            userId: $request->user()?->id,
            period: (string) $request->input('period', 'this_month'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
        ));
    }

    public function barberProfile(Request $request): View
    {
        return view('dashboards.barber-profile', $this->dashboardQueryService->getBarberProfileData($request->user()?->id));
    }

    public function editBarberProfile(Request $request): View
    {
        return view('dashboards.barber-profile-edit', $this->dashboardQueryService->getBarberProfileData($request->user()?->id));
    }

    public function updateBarberProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $membership = \App\Models\Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('user_id', $user?->id)
            ->first();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'display_name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user?->id],
            'specialty' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'display_name.required' => 'Nama panggilan / display antrean wajib diisi.',
            'email.required' => 'Email login wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($membership !== null) {
            $membership->update([
                'display_name' => $validated['display_name'],
                'specialty' => $validated['specialty'] ?? null,
            ]);
        }

        return redirect()->route('barber.profile')->with('status', 'Informasi pribadi berhasil diperbarui.');
    }

    public function updateBarberPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Kata sandi berhasil diperbarui.');
    }

    public function customerPortal(Request $request, string $tenantSlug): View
    {
        return view('dashboards.customer', $this->dashboardQueryService->getCustomerPortalData(
            tenantSlug: $tenantSlug,
            ticketNumber: $request->query('ticket'),
            userId: $request->user()?->id,
            branchCodeOrId: $request->query('branch') ?: $request->query('branch_id'),
        ));
    }

    public function tvDisplay(string $tenantSlug, string $branchCode): View
    {
        return view('dashboards.tv', $this->dashboardQueryService->getTvDisplayData($tenantSlug, $branchCode));
    }

    public function showInvoice(string $orderNumber): View
    {
        return view('invoices.show', $this->dashboardQueryService->getInvoiceDetailData($orderNumber));
    }

    public function requestTimeOff(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:leave,sick,holiday'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:500'],
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'type.required' => 'Pilih jenis izin yang diajukan.',
            'type.in' => 'Jenis izin harus salah satu dari: Cuti, Sakit, atau Izin Libur.',
            'date_from.required' => 'Tanggal mulai izin wajib diisi.',
            'date_to.required' => 'Tanggal selesai izin wajib diisi.',
            'date_to.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'reason.required' => 'Alasan izin wajib diisi secara jelas.',
            'attachment.required' => 'Bukti surat dokter / surat keterangan wajib diunggah.',
            'attachment.mimes' => 'Bukti surat harus berupa file gambar (JPG, PNG) atau PDF.',
            'attachment.max' => 'Ukuran file bukti surat maksimal 5 MB.',
        ]);

        $user = $request->user();
        $membership = \App\Models\Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('user_id', $user?->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return back()->with('error', 'Data keanggotaan staf Anda tidak ditemukan.');
        }

        $attachmentPath = $request->file('attachment')->store('time-offs', 'public');

        \App\Models\BarberTimeOff::query()->create([
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->id,
            'type' => $validated['type'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'reason' => $validated['reason'],
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Permohonan izin berhasil diajukan dan sedang menunggu persetujuan Owner.');
    }
}
