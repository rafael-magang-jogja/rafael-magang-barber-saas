<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CustomerWithdrawal;
use App\Models\Tenant;
use App\Models\TenantPayout;
use App\Models\TenantPayoutItem;
use App\Services\CustomerWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PlatformPayoutController extends Controller
{
    public function __construct(
        private readonly CustomerWalletService $customerWalletService,
    ) {}

    public function index(Request $request): View
    {
        // 1. Ringkasan Metrik Escrow Global Platform
        $totalPaidBookingsQuery = Booking::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where(function ($q): void {
                $q->whereNotNull('xendit_paid_at')
                    ->orWhereNotNull('payment_approved_at')
                    ->orWhereNotNull('payment_proof_path');
            });

        $totalPlatformRevenue = (int) (clone $totalPaidBookingsQuery)->sum('estimated_total');
        $totalPlatformBookingsCount = (int) (clone $totalPaidBookingsQuery)->count();

        $totalDisbursedAmount = (int) TenantPayout::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'completed')
            ->sum('amount');

        $totalPendingBalance = max(0, $totalPlatformRevenue - $totalDisbursedAmount);

        // 2. Daftar Tenant dengan Status Saldo Booking & Rekening Payout
        $tenants = Tenant::query()
            ->with(['owner'])
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant): array {
                // Booking yang lunas dan belum dicairkan (tidak ada di tenant_payout_items)
                $unpaidBookings = Booking::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
                    ->where(function ($q): void {
                        $q->whereNotNull('xendit_paid_at')
                            ->orWhereNotNull('payment_approved_at')
                            ->orWhereNotNull('payment_proof_path');
                    })
                    ->whereDoesntHave('payoutItem')
                    ->get();

                $availableBalance = (int) $unpaidBookings->sum('estimated_total');
                $unpaidBookingsCount = $unpaidBookings->count();

                $completedPayoutsSum = (int) TenantPayout::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                $hasBankInfo = ! empty($tenant->payout_bank_name) && ! empty($tenant->payout_account_number) && ! empty($tenant->payout_account_holder);

                return [
                    'tenant' => $tenant,
                    'available_balance' => $availableBalance,
                    'unpaid_count' => $unpaidBookingsCount,
                    'unpaid_booking_ids' => $unpaidBookings->pluck('id')->all(),
                    'total_disbursed' => $completedPayoutsSum,
                    'bank_name' => $tenant->payout_bank_name ?: 'Belum diatur owner',
                    'account_number' => $tenant->payout_account_number ?: 'Belum diatur owner',
                    'account_holder' => $tenant->payout_account_holder ?: 'Belum diatur owner',
                    'has_bank_info' => $hasBankInfo,
                ];
            });

        // 3. Riwayat Pencairan Dana (Payouts History)
        $payoutsQuery = TenantPayout::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'processedByUser', 'items.booking']);

        if ($request->filled('tenant_id')) {
            $payoutsQuery->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('status')) {
            $payoutsQuery->where('status', $request->string('status'));
        }

        $payouts = $payoutsQuery->latest('id')->paginate(15)->withQueryString();

        return view('platform.payouts.index', [
            'totalPlatformRevenue' => $totalPlatformRevenue,
            'totalPlatformBookingsCount' => $totalPlatformBookingsCount,
            'totalDisbursedAmount' => $totalDisbursedAmount,
            'totalPendingBalance' => $totalPendingBalance,
            'tenants' => $tenants,
            'payouts' => $payouts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'transfer_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [
            'tenant_id.required' => 'Pilih barbershop yang akan dicairkan.',
            'transfer_proof.required' => 'Unggah berkas bukti transfer pencairan dari Superadmin.',
            'transfer_proof.max' => 'Ukuran berkas bukti transfer maksimal 3 MB.',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        if (empty($tenant->payout_bank_name) || empty($tenant->payout_account_number) || empty($tenant->payout_account_holder)) {
            return back()->withErrors(['payout' => "Owner {$tenant->name} belum mengatur rekening atau e-wallet tujuan pencairan di dashboard mereka. Superadmin tidak diperkenankan memproses pencairan sebelum data rekening dilengkapi oleh owner."]);
        }

        // Ambil semua booking yang lunas dan belum dicairkan untuk tenant ini
        $eligibleBookings = Booking::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where(function ($q): void {
                $q->whereNotNull('xendit_paid_at')
                    ->orWhereNotNull('payment_approved_at')
                    ->orWhereNotNull('payment_proof_path');
            })
            ->whereDoesntHave('payoutItem')
            ->lockForUpdate()
            ->get();

        if ($eligibleBookings->isEmpty()) {
            return back()->withErrors(['payout' => "Tidak ada saldo booking yang dapat dicairkan untuk {$tenant->name}."]);
        }

        $totalAmount = (int) $eligibleBookings->sum('estimated_total');

        $proofPath = $request->file('transfer_proof')->store('payout-proofs', 'public');

        $referenceNo = 'PO-'.date('Ymd').'-'.strtoupper(Str::random(6));

        DB::transaction(function () use ($tenant, $referenceNo, $totalAmount, $validated, $proofPath, $eligibleBookings): void {
            $payout = TenantPayout::create([
                'tenant_id' => $tenant->id,
                'reference_no' => $referenceNo,
                'amount' => $totalAmount,
                'status' => 'completed',
                'bank_name' => $tenant->payout_bank_name,
                'account_number' => $tenant->payout_account_number,
                'account_holder' => $tenant->payout_account_holder,
                'transfer_proof_path' => $proofPath,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($eligibleBookings as $booking) {
                TenantPayoutItem::create([
                    'tenant_payout_id' => $payout->id,
                    'booking_id' => $booking->id,
                    'amount' => $booking->estimated_total,
                ]);
            }
        });

        return back()->with('status', 'Pencairan dana sebesar Rp'.number_format($totalAmount, 0, ',', '.')." untuk {$tenant->name} ({$referenceNo}) berhasil diselesaikan!");
    }

    public function approveCustomerWithdrawal(Request $request, CustomerWithdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'transfer_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [
            'transfer_proof.max' => 'Ukuran berkas bukti transfer maksimal 3 MB.',
        ]);

        $proofPath = null;
        if ($request->hasFile('transfer_proof')) {
            $proofPath = $request->file('transfer_proof')->store('customer-withdrawal-proofs', 'public');
        }

        try {
            $this->customerWalletService->approveWithdrawal(
                withdrawal: $withdrawal,
                proofPath: $proofPath,
                processedByUserId: $request->user()?->id
            );

            return back();
        } catch (\Throwable $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
    }

    public function rejectCustomerWithdrawal(Request $request, CustomerWithdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan penarikan dana wajib diisi.',
        ]);

        try {
            $this->customerWalletService->rejectWithdrawal(
                withdrawal: $withdrawal,
                reason: trim((string) $validated['rejection_reason']),
                processedByUserId: $request->user()?->id
            );

            return back();
        } catch (\Throwable $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
    }

    public function withdrawals(Request $request): View
    {
        $status = $request->string('status')->toString();

        $withdrawals = CustomerWithdrawal::query()
            ->with(['user', 'customerProfile', 'processedByUser'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $pendingCount = CustomerWithdrawal::query()->where('status', 'pending')->count();
        $completedCount = CustomerWithdrawal::query()->where('status', 'completed')->count();
        $rejectedCount = CustomerWithdrawal::query()->where('status', 'rejected')->count();
        $totalCount = CustomerWithdrawal::query()->count();

        $pendingSum = (int) CustomerWithdrawal::query()->where('status', 'pending')->sum('amount');
        $completedSum = (int) CustomerWithdrawal::query()->where('status', 'completed')->sum('amount');

        return view('platform.withdrawals.index', [
            'withdrawals' => $withdrawals,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'rejectedCount' => $rejectedCount,
            'totalCount' => $totalCount,
            'pendingSum' => $pendingSum,
            'completedSum' => $completedSum,
            'currentStatus' => $status,
        ]);
    }
}
