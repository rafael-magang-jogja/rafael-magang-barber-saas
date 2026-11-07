<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TenantPayout;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OwnerPayoutController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = app(CurrentTenant::class)->get();

        // 1. Total Pendapatan Booking Masuk ke Platform
        $totalBookingRevenue = (int) Booking::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where(function ($q): void {
                $q->whereNotNull('xendit_paid_at')
                    ->orWhereNotNull('payment_approved_at')
                    ->orWhereNotNull('payment_proof_path');
            })
            ->sum('estimated_total');

        // 2. Total yang Sudah Dicairkan oleh Superadmin
        $disbursedAmount = (int) TenantPayout::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->sum('amount');

        // 3. Saldo yang Siap Dicairkan (Mengendap di Rekening Superadmin)
        $unpaidBookings = Booking::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where(function ($q): void {
                $q->whereNotNull('xendit_paid_at')
                    ->orWhereNotNull('payment_approved_at')
                    ->orWhereNotNull('payment_proof_path');
            })
            ->whereDoesntHave('payoutItem')
            ->with(['items.service', 'barber'])
            ->latest('scheduled_at')
            ->get();

        $pendingBalance = (int) $unpaidBookings->sum('estimated_total');

        // 4. Riwayat Pencairan Dana
        $payouts = TenantPayout::query()
            ->where('tenant_id', $tenant->id)
            ->with(['processedByUser', 'items.booking'])
            ->latest('id')
            ->paginate(10);

        return view('owner.payouts.index', [
            'tenant' => $tenant,
            'totalBookingRevenue' => $totalBookingRevenue,
            'disbursedAmount' => $disbursedAmount,
            'pendingBalance' => $pendingBalance,
            'unpaidBookings' => $unpaidBookings,
            'payouts' => $payouts,
        ]);
    }

    public function updateBankAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payout_bank_name' => ['required', 'string', 'max:50'],
            'payout_account_number' => ['required', 'string', 'max:50'],
            'payout_account_holder' => ['required', 'string', 'max:100'],
        ], [
            'payout_bank_name.required' => 'Nama bank atau e-wallet tujuan pencairan wajib diisi.',
            'payout_account_number.required' => 'Nomor rekening atau nomor HP e-wallet wajib diisi.',
            'payout_account_holder.required' => 'Nama pemilik rekening atau akun e-wallet wajib diisi.',
        ]);

        $tenant = app(CurrentTenant::class)->get();
        $tenant->update($validated);

        return back()->with('status', 'Rekening bank atau akun e-wallet tujuan pencairan berhasil diperbarui!');
    }
}
