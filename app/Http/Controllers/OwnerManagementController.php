<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Owner\RefundOrderRequest;
use App\Http\Requests\Owner\StoreBranchRequest;
use App\Http\Requests\Owner\StoreHairColorRequest;
use App\Http\Requests\Owner\StoreServiceRequest;
use App\Http\Requests\Owner\StoreStaffRequest;
use App\Http\Requests\Owner\UpdateBranchRequest;
use App\Http\Requests\Owner\UpdateHairColorRequest;
use App\Http\Requests\Owner\UpdateServicePriceRequest;
use App\Http\Requests\Owner\UpdateServiceRequest;
use App\Http\Requests\Owner\UpdateStaffRequest;
use App\Models\Attendance;
use App\Models\BarberSchedule;
use App\Models\BarberTimeOff;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\CustomerProfile;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Plan;
use App\Models\Review;
use App\Models\SalaryScheme;
use App\Support\Platform\FaviconService;
use App\Models\Service;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\BranchRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\StaffRepository;
use App\Services\CheckoutService;
use App\Services\OwnerManagementService;
use App\Services\SubscriptionActivationService;
use App\Services\XenditPaymentService;
use App\Support\Tenancy\CurrentTenant;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OwnerManagementController extends Controller
{
    public function __construct(
        private readonly OwnerManagementService $ownerManagementService,
        private readonly CheckoutService $checkoutService,
        private readonly BranchRepository $branchRepository,
        private readonly StaffRepository $staffRepository,
        private readonly CatalogRepository $catalogRepository,
        private readonly OrderRepository $orderRepository,
        private readonly SubscriptionActivationService $subscriptionActivationService,
        private readonly XenditPaymentService $xenditPaymentService,
    ) {}

    public function branches(Request $request): View|RedirectResponse
    {
        if ($request->filled('edit')) {
            $branch = Branch::query()
                ->where('tenant_id', app(CurrentTenant::class)->id())
                ->find($request->integer('edit'));

            if ($branch) {
                return redirect()->route('owner.branches.edit', $branch);
            }
        }

        return view('owner.branches', [
            'branches' => $this->branchRepository->getBranchesWithCounts(),
        ]);
    }

    public function createBranch(): View|RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant && ! $tenant->canAddBranch()) {
            return redirect()->route('owner.branches')
                ->with('warning', "Kuota cabang untuk paket {$tenant->plan()?->name} telah mencapai batas maksimal ({$tenant->maxBranches()} cabang). Silakan upgrade paket langganan Anda.");
        }

        return view('owner.branches-create', [
            'editingBranch' => null,
        ]);
    }

    public function editBranch(Branch $branch): View
    {
        return view('owner.branches-create', [
            'editingBranch' => $branch,
        ]);
    }

    public function storeBranch(StoreBranchRequest $request): RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant && ! $tenant->canAddBranch()) {
            return redirect()->route('owner.branches')
                ->with('warning', "Kuota cabang untuk paket {$tenant->plan()?->name} telah mencapai batas maksimal ({$tenant->maxBranches()} cabang). Silakan upgrade paket langganan Anda.");
        }

        $branch = $this->ownerManagementService->createBranch($request->validated());

        return redirect()->route('owner.branches')->with('status', "Cabang {$branch->name} berhasil ditambahkan.");
    }

    public function updateBranch(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->ownerManagementService->updateBranch($branch, $request->validated());

        return redirect()->route('owner.branches')->with('status', "Cabang {$branch->name} berhasil diperbarui.");
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant === null || $branch->tenant_id !== $tenant->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus cabang ini.');
        }

        // 1. Cek minimal 1 cabang tersisa
        if ($tenant->branches()->count() <= 1) {
            return redirect()->route('owner.branches')
                ->with('warning', 'Tidak dapat menghapus cabang ini karena barbershop Anda wajib memiliki minimal 1 cabang operasional.');
        }

        // 2. Cek apakah ada antrean aktif yang sedang berjalan
        $hasActiveQueues = $branch->queueTickets()
            ->whereIn('status', [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called, \App\Enums\QueueStatus::InService])
            ->exists();

        if ($hasActiveQueues) {
            return redirect()->route('owner.branches')
                ->with('warning', "Cabang {$branch->name} tidak dapat dihapus saat ini karena masih memiliki antrean pelanggan yang sedang berjalan.");
        }

        // 3. Cek apakah ada booking aktif
        $hasPendingBookings = $branch->bookings()
            ->whereIn('status', [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::Draft])
            ->whereDate('scheduled_at', '>=', now()->toDateString())
            ->exists();

        if ($hasPendingBookings) {
            return redirect()->route('owner.branches')
                ->with('warning', "Cabang {$branch->name} tidak dapat dihapus saat ini karena masih memiliki reservasi/booking aktif di masa mendatang.");
        }

        $branchName = $branch->name;
        $this->ownerManagementService->deleteBranch($branch);

        return redirect()->route('owner.branches')
            ->with('status', "Cabang {$branchName} berhasil dihapus dari sistem.");
    }

    public function staff(): View
    {
        return view('owner.staff', [
            'memberships' => $this->staffRepository->getAllMembershipsWithRelations(),
            'branches' => $this->branchRepository->getActiveBranches(),
            'schemes' => $this->staffRepository->getActiveSalarySchemes(),
        ]);
    }

    public function storeStaff(StoreStaffRequest $request): RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant && ! $tenant->canAddStaff()) {
            return back()->with('warning', "Kuota staf untuk paket {$tenant->plan()?->name} telah mencapai batas maksimal ({$tenant->maxStaff()} karyawan). Silakan upgrade paket langganan Anda.");
        }

        $tenantId = (int) app(CurrentTenant::class)->id();

        $staff = $this->ownerManagementService->createStaff($tenantId, $request->validated());

        return back()->with('status', "Karyawan {$staff->display_name} berhasil ditambahkan.");
    }

    public function updateStaff(UpdateStaffRequest $request, Membership $membership): RedirectResponse
    {
        $updated = $this->ownerManagementService->updateStaff($membership, $request->validated());

        return redirect()
            ->route('owner.staff')
            ->with('status', "Data karyawan {$updated->display_name} berhasil diperbarui.");
    }

    public function destroyStaff(Membership $membership): RedirectResponse
    {
        $tenantId = (int) app(CurrentTenant::class)->id();
        if ($membership->tenant_id !== $tenantId) {
            abort(403);
        }

        $name = $membership->display_name ?? $membership->user?->name ?? 'Karyawan';
        $this->ownerManagementService->deleteStaff($membership);

        return redirect()
            ->route('owner.staff', request()->only(['branch_id', 'role', 'page']))
            ->with('status', "Karyawan {$name} berhasil dihapus.");
    }

    public function services(): View
    {
        return view('owner.services', [
            'categories' => $this->catalogRepository->getServiceCategories(),
            'services' => $this->catalogRepository->getServicesWithCategory(),
        ]);
    }

    public function storeService(StoreServiceRequest $request): RedirectResponse
    {
        $service = $this->ownerManagementService->createService($request->validated());

        return back()->with('status', "Layanan {$service->name} berhasil disimpan.");
    }

    public function updateService(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $updated = $this->ownerManagementService->updateService($service, $request->validated());

        return redirect()
            ->route('owner.services')
            ->with('status', "Layanan {$updated->name} berhasil diperbarui.");
    }

    public function updateServicePrice(UpdateServicePriceRequest $request, Service $service): RedirectResponse
    {
        $updated = $this->ownerManagementService->updateServicePrice($service, (int) $request->validated('price'));

        return back()->with('status', "Harga {$updated->name} berhasil diperbarui.");
    }

    public function colors(): View
    {
        return view('owner.colors', [
            'colors' => $this->catalogRepository->getAllHairColors(),
        ]);
    }

    public function storeColor(StoreHairColorRequest $request): RedirectResponse
    {
        $color = $this->ownerManagementService->createHairColor($request->validated());

        return back()->with('status', "Warna coloring {$color->name} berhasil ditambahkan.");
    }

    public function updateColor(UpdateHairColorRequest $request, HairColor $hairColor): RedirectResponse
    {
        $updated = $this->ownerManagementService->updateHairColor($hairColor, $request->validated());

        return redirect()
            ->route('owner.colors')
            ->with('status', "Stok & pengaturan warna {$updated->name} berhasil diperbarui.");
    }

    public function reports(Request $request): View
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $barberId = $request->filled('barber_id') ? $request->integer('barber_id') : null;
        $paymentStatus = $request->filled('payment_status') ? (string) $request->string('payment_status') : null;
        $period = (string) $request->input('period', 'all');

        $dateFrom = match ($period) {
            'today' => now()->startOfDay()->toDateString(),
            '7days' => now()->subDays(6)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'last_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'custom' => $request->filled('date_from') ? (string) $request->input('date_from') : null,
            default => $request->filled('date_from') ? (string) $request->input('date_from') : null,
        };

        $dateTo = match ($period) {
            'today' => now()->endOfDay()->toDateString(),
            '7days' => now()->toDateString(),
            'this_month' => now()->endOfMonth()->toDateString(),
            'last_month' => now()->subMonth()->endOfMonth()->toDateString(),
            'custom' => $request->filled('date_to') ? (string) $request->input('date_to') : null,
            default => $request->filled('date_to') ? (string) $request->input('date_to') : null,
        };

        // Query Review & Ulasan Pelanggan untuk Bahan Evaluasi Cabang / Usaha Mandiri
        $reviewRating = $request->filled('review_rating') ? $request->integer('review_rating') : null;

        $reviewsBaseQuery = Review::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo));

        $reviewMetrics = (clone $reviewsBaseQuery)
            ->selectRaw("
                COUNT(*) as total_reviews,
                COALESCE(AVG(rating), 0) as avg_rating,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as count_5,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as count_4,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as count_3,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as count_2,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as count_1,
                COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_count,
                COUNT(CASE WHEN rating <= 3 THEN 1 END) as evaluation_count
            ")
            ->first();

        $reviews = (clone $reviewsBaseQuery)
            ->when($reviewRating, fn ($q) => $q->where('rating', $reviewRating))
            ->with([
                'branch',
                'barber.user',
                'customerProfile',
                'order',
            ])
            ->latest('id')
            ->paginate(10, ['*'], 'reviews_page')
            ->withQueryString();

        return view('owner.reports', [
            'orders' => $this->orderRepository->getOrdersForReport($branchId, $barberId, $paymentStatus, $dateFrom, $dateTo),
            'branches' => $this->branchRepository->getAllBranches(),
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'analytics' => $this->ownerManagementService->getOperationalAnalytics($branchId, $barberId),
            'currentPeriod' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reviews' => $reviews,
            'reviewMetrics' => $reviewMetrics,
            'selectedReviewRating' => $reviewRating,
        ]);
    }

    public function toggleReviewVisibility(Review $review): RedirectResponse
    {
        $review->update([
            'is_published' => ! $review->is_published,
        ]);

        $statusLabel = $review->is_published ? 'ditampilkan di profil publik' : 'disembunyikan dari profil publik';

        return back()->with('status', "Status ulasan pelanggan berhasil diubah: {$statusLabel}.");
    }

    public function replyReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ]);

        $review->update([
            'replied_text' => $validated['reply'],
            'replied_at' => now(),
        ]);

        return back()->with('status', 'Balasan apresiasi ulasan pelanggan berhasil disimpan.');
    }

    public function exportReportsCsv(Request $request): StreamedResponse
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $barberId = $request->filled('barber_id') ? $request->integer('barber_id') : null;
        $paymentStatus = $request->filled('payment_status') ? (string) $request->string('payment_status') : null;
        $period = (string) $request->input('period', 'all');

        $dateFrom = match ($period) {
            'today' => now()->startOfDay()->toDateString(),
            '7days' => now()->subDays(6)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'last_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'custom' => $request->filled('date_from') ? (string) $request->input('date_from') : null,
            default => $request->filled('date_from') ? (string) $request->input('date_from') : null,
        };

        $dateTo = match ($period) {
            'today' => now()->endOfDay()->toDateString(),
            '7days' => now()->toDateString(),
            'this_month' => now()->endOfMonth()->toDateString(),
            'last_month' => now()->subMonth()->endOfMonth()->toDateString(),
            'custom' => $request->filled('date_to') ? (string) $request->input('date_to') : null,
            default => $request->filled('date_to') ? (string) $request->input('date_to') : null,
        };

        $orders = $this->orderRepository->getOrdersForReport($branchId, $barberId, $paymentStatus, $dateFrom, $dateTo);
        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $selectedBranch = $branchId ? \App\Models\Branch::find($branchId) : null;

        $sumSubtotal = (int) $orders->sum('subtotal');
        $sumDiscount = (int) $orders->sum('discount_total');
        $sumTip = (int) $orders->sum('tip_amount');
        $sumGrandTotal = (int) $orders->sum('grand_total');

        $rows = [];
        foreach ($orders as $idx => $order) {
            $rows[] = [
                $idx + 1,
                $order->number,
                $order->branch?->name ?? 'Outlet Pusat',
                $order->customerProfile?->name ?? 'Walk-in',
                $order->barber?->display_name ?? ($order->barber?->user?->name ?? '-'),
                (int) $order->subtotal,
                (int) $order->discount_total,
                (int) $order->tip_amount,
                (int) $order->grand_total,
                strtoupper((string) $order->payment_status->value),
                $order->paid_at ? $order->paid_at->translatedFormat('d M Y - H:i') : '-',
            ];
        }

        $excelService = app(\App\Services\ExcelExportService::class);

        return $excelService->downloadXlsx(
            filename: 'laporan-keuangan-' . now()->format('Ymd-His') . '.xlsx',
            data: [
                'title' => 'Laporan Keuangan & Transaksi Bisnis',
                'meta' => [
                    'Nama Barbershop' => $tenant?->name ?? 'Barbershop',
                    'Cabang Outlet' => $selectedBranch ? $selectedBranch->name : 'Semua Cabang',
                    'Periode Tanggal' => ($dateFrom && $dateTo) ? "{$dateFrom} s/d {$dateTo}" : 'Semua Riwayat',
                    'Waktu Unduh' => now()->translatedFormat('l, d F Y - H:i') . ' WIB',
                ],
                'kpi_summary' => [
                    'Total Transaksi Terdata' => $orders->count(),
                    'Total Transaksi Lunas' => $orders->where('payment_status.value', 'paid')->count(),
                    'Total Subtotal Layanan' => $sumSubtotal,
                    'Total Diskon Diberikan' => $sumDiscount,
                    'Total Tip Pelanggan' => $sumTip,
                    'Total Penerimaan Bersih (Grand Total)' => $sumGrandTotal,
                ],
                'headers' => [
                    'No',
                    'No. Invoice',
                    'Cabang Outlet',
                    'Customer',
                    'Barber Melayani',
                    'Subtotal (Rp)',
                    'Diskon (Rp)',
                    'Tip Barber (Rp)',
                    'Grand Total (Rp)',
                    'Status',
                    'Waktu Pembayaran',
                ],
                'alignments' => [
                    'center',
                    'left',
                    'left',
                    'left',
                    'left',
                    'right',
                    'right',
                    'right',
                    'right',
                    'center',
                    'center',
                ],
                'formats' => [
                    'integer',
                    'text',
                    'text',
                    'text',
                    'text',
                    'currency',
                    'currency',
                    'currency',
                    'currency',
                    'text',
                    'text',
                ],
                'rows' => $rows,
                'totals' => [
                    'TOTAL KESELURUHAN',
                    '',
                    '',
                    '',
                    '',
                    $sumSubtotal,
                    $sumDiscount,
                    $sumTip,
                    $sumGrandTotal,
                    '',
                    '',
                ],
                'theme_color' => '18181B', // Dark zinc theme
            ]
        );
    }

    public function refundOrder(RefundOrderRequest $request, Order $order): RedirectResponse
    {
        try {
            $this->checkoutService->refundOrder($order, (string) $request->validated('reason'), $request->user()?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', "Invoice {$order->number} telah di-refund dan komisi barber dibalik (reversal).");
    }

    public function transactions(Request $request): View
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $barberId = $request->filled('barber_id') ? $request->integer('barber_id') : null;
        $paymentStatus = $request->filled('payment_status') ? (string) $request->string('payment_status') : null;
        $search = $request->filled('search') ? (string) $request->string('search') : null;

        $period = (string) $request->input('period', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $startDate = null;
        $endDate = null;

        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'yesterday':
                $startDate = now()->subDay()->startOfDay();
                $endDate = now()->subDay()->endOfDay();
                break;
            case '7days':
                $startDate = now()->subDays(6)->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'this_month':
                $startDate = now()->startOfMonth()->startOfDay();
                $endDate = now()->endOfMonth()->endOfDay();
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth()->startOfDay();
                $endDate = now()->subMonth()->endOfMonth()->endOfDay();
                break;
            case 'custom':
                if (!empty($dateFrom)) {
                    $startDate = \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay();
                }
                if (!empty($dateTo)) {
                    $endDate = \Illuminate\Support\Carbon::parse($dateTo)->endOfDay();
                }
                break;
        }

        $baseQuery = Order::query()
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('barber_membership_id', $barberId))
            ->when($paymentStatus !== null && $paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('number', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', fn ($cp) => $cp->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            });

        $stats = (clone $baseQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) as total_revenue,
                COALESCE(COUNT(CASE WHEN payment_status = 'paid' THEN 1 END), 0) as paid_count,
                COALESCE(COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END), 0) as refunded_count
            ")
            ->first();

        $totalRevenue = (int) ($stats->total_revenue ?? 0);
        $paidCount = (int) ($stats->paid_count ?? 0);
        $refundedCount = (int) ($stats->refunded_count ?? 0);

        $orders = (clone $baseQuery)
            ->with(['branch', 'customerProfile', 'barber.user', 'items.service', 'payments'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        $hasAnalyticsPro = $tenant?->hasFeature('analytics_pro') ?? true;

        return view('owner.transactions', [
            'orders' => $orders,
            'totalRevenue' => $totalRevenue,
            'paidCount' => $paidCount,
            'refundedCount' => $refundedCount,
            'branches' => $this->branchRepository->getAllBranches(),
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'analytics' => $hasAnalyticsPro ? $this->ownerManagementService->getFinancialAnalytics($branchId, $barberId, $startDate, $endDate) : null,
            'hasAnalyticsPro' => $hasAnalyticsPro,
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function payroll(Request $request): View
    {
        $payrolls = Payroll::query()
            ->with(['branch', 'approver', 'items.membership.user', 'items.membership.salaryScheme', 'items.membership.branch'])
            ->latest('period_end')
            ->get();

        // Otomatis sinkronkan draf payroll dengan data transaksi, komisi, dan tip terbaru
        $hasUpdatedDraft = false;
        foreach ($payrolls as $p) {
            if ($p->status->value === 'draft') {
                $this->ownerManagementService->generatePayroll(
                    tenantId: $p->tenant_id,
                    branchId: $p->branch_id,
                    periodStart: $p->period_start->format('Y-m-d'),
                    periodEnd: $p->period_end->format('Y-m-d'),
                    userId: (int) auth()->id(),
                    payrollId: $p->id,
                );
                $hasUpdatedDraft = true;
            }
        }

        if ($hasUpdatedDraft) {
            $payrolls = Payroll::query()
                ->with(['branch', 'approver', 'items.membership.user', 'items.membership.salaryScheme', 'items.membership.branch'])
                ->latest('period_end')
                ->get();
        }

        $activeBarbers = $this->staffRepository->getActiveBarbers(withRelations: true);
        $activeStaff = Membership::query()
            ->with(['user', 'branch'])
            ->where('is_active', true)
            ->where('role', '!=', \App\Enums\Role::Owner)
            ->orderBy('display_name')
            ->get();
        $branches = $this->branchRepository->getActiveBranches();

        if ($request->filled('payroll_id')) {
            session(['owner_selected_payroll_id' => $request->integer('payroll_id')]);
        }

        $targetPayrollId = $request->integer('payroll_id') ?: session('owner_selected_payroll_id');
        $selectedPayroll = $targetPayrollId ? $payrolls->firstWhere('id', $targetPayrollId) : null;

        if (! $selectedPayroll) {
            $selectedPayroll = $payrolls->first();
            if ($selectedPayroll) {
                session(['owner_selected_payroll_id' => $selectedPayroll->id]);
            }
        }

        return view('owner.payroll', [
            'payrolls' => $payrolls,
            'selectedPayroll' => $selectedPayroll,
            'activeBarbers' => $activeBarbers,
            'activeStaff' => $activeStaff,
            'branches' => $branches,
        ]);
    }

    public function generatePayroll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', 'integer'],
            'membership_id' => ['nullable', 'integer', 'exists:memberships,id'],
            'payroll_id' => ['nullable', 'integer'],
        ]);

        $tenantId = (int) app(CurrentTenant::class)->id();
        $branchId = ! empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $membershipId = ! empty($validated['membership_id']) ? (int) $validated['membership_id'] : null;
        $payrollId = ! empty($validated['payroll_id']) ? (int) $validated['payroll_id'] : null;

        $payroll = $this->ownerManagementService->generatePayroll(
            tenantId: $tenantId,
            branchId: $branchId,
            periodStart: (string) $validated['period_start'],
            periodEnd: (string) $validated['period_end'],
            userId: (int) $request->user()?->id,
            membershipId: $membershipId,
            payrollId: $payrollId,
        );

        $msg = $membershipId !== null
            ? 'Slip gaji karyawan berhasil dihitung dan diperbarui.'
            : ($branchId !== null
                ? 'Payroll cabang berhasil dihitung dan diperbarui.'
                : 'Draf Payroll berhasil dihitung dan dibuat otomatis untuk seluruh karyawan.');

        session(['owner_selected_payroll_id' => $payroll->id]);

        return redirect()->route('owner.payroll', ['payroll_id' => $payroll->id])
            ->with('status', $msg);
    }

    public function approvePayroll(Payroll $payroll, Request $request): RedirectResponse
    {
        $this->ownerManagementService->approvePayroll($payroll, (int) $request->user()?->id);

        return back()->with('status', 'Payroll periode ' . $payroll->period_start->format('d M') . ' - ' . $payroll->period_end->format('d M Y') . ' telah disetujui (Approved).');
    }

    public function markPayrollPaid(Payroll $payroll): RedirectResponse
    {
        try {
            $this->ownerManagementService->markPayrollPaid($payroll);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Payroll periode ' . $payroll->period_start->format('d M') . ' - ' . $payroll->period_end->format('d M Y') . ' telah ditandai Lunas / Dibayar.');
    }

    public function markPayrollItemPaid(PayrollItem $item, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->ownerManagementService->markPayrollItemPaid(
                item: $item,
                paymentMethod: $validated['payment_method'] ?? 'transfer',
                notes: $validated['payment_reference'] ?? null,
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $name = $item->membership?->display_name ?? $item->membership?->user?->name ?? 'Karyawan';
        return back()->with('status', "Gaji {$name} sebesar Rp" . number_format($item->net_total, 0, ',', '.') . " telah ditandai Lunas / Dibayar.");
    }

    public function payrollStaff(Request $request): View
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $role = $request->filled('role') ? (string) $request->string('role') : null;
        $search = $request->filled('search') ? (string) $request->string('search') : null;

        $membershipsQuery = Membership::query()
            ->with(['user', 'branch', 'salaryScheme'])
            ->where('is_active', true)
            ->where('role', '!=', \App\Enums\Role::Owner)
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($role !== null && $role !== '', fn ($q) => $q->where('role', $role))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('display_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('id');

        $memberships = $membershipsQuery->get();

        $salarySchemes = SalaryScheme::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $branches = $this->branchRepository->getActiveBranches();

        $individualCommissions = CommissionRule::query()
            ->whereNotNull('membership_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('membership_id');

        $totalBaseSalary = (int) $memberships->sum(fn ($m) => $m->effective_base_salary);
        $customSalaryCount = $memberships->whereNotNull('custom_base_salary')->count();
        $avgBaseSalary = $memberships->count() > 0 ? (int) round($totalBaseSalary / $memberships->count()) : 0;

        return view('owner.payroll.staff', [
            'memberships' => $memberships,
            'salarySchemes' => $salarySchemes,
            'branches' => $branches,
            'individualCommissions' => $individualCommissions,
            'totalBaseSalary' => $totalBaseSalary,
            'customSalaryCount' => $customSalaryCount,
            'avgBaseSalary' => $avgBaseSalary,
        ]);
    }

    public function updateStaffSalary(Request $request, Membership $membership): RedirectResponse
    {
        $tenantId = (int) app(CurrentTenant::class)->id();

        $isBarber = $membership->role === \App\Enums\Role::Barber || (is_object($membership->role) && $membership->role->value === 'barber') || $membership->role === 'barber';

        $rules = [
            'salary_scheme_id' => [
                'required',
                'integer',
                Rule::exists('salary_schemes', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if (! $isBarber) {
            $rules['salary_mode'] = ['required', 'in:standard,custom'];
            $rules['custom_base_salary'] = ['nullable', 'numeric', 'min:0', 'required_if:salary_mode,custom'];
        }

        $validated = $request->validate($rules);

        $customBase = null;
        if (! $isBarber && ($validated['salary_mode'] ?? '') === 'custom') {
            $customBase = (int) ($validated['custom_base_salary'] ?? 0);
        }

        $membership->update([
            'salary_scheme_id' => (int) $validated['salary_scheme_id'],
            'custom_base_salary' => $customBase,
        ]);

        // Bersihkan aturan komisi khusus individu (persentase bagi hasil kini murni mengikuti pengaturan skema jabatan di menu rules)
        CommissionRule::query()
            ->where('tenant_id', $tenantId)
            ->where('membership_id', $membership->id)
            ->delete();

        return redirect()->route('owner.payroll.staff')
            ->with('status', "Gaji & skema karyawan {$membership->display_name} berhasil diperbarui.");
    }

    public function payrollRules(): View
    {
        $schemes = SalaryScheme::query()
            ->withCount(['memberships' => fn ($q) => $q->where('role', '!=', \App\Enums\Role::Owner)])
            ->with(['memberships' => fn ($q) => $q->where('role', '!=', \App\Enums\Role::Owner)->with(['user', 'branch'])])
            ->orderBy('id')
            ->get();

        $services = Service::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('service_category_id')
            ->orderBy('id')
            ->get();

        $serviceRules = CommissionRule::query()
            ->where('scope', 'service')
            ->whereNull('membership_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('service_id');

        return view('owner.payroll.rules', [
            'schemes' => $schemes,
            'services' => $services,
            'serviceRules' => $serviceRules,
        ]);
    }

    public function storeSalaryScheme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:base_plus_commission,fixed,commission_only'],
            'base_salary' => ['nullable'],
            'alpha_penalty_nominal' => ['nullable'],
            'commission_percentage' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'bonus_tiers' => ['nullable', 'array'],
            'bonus_tiers.*.min_services' => ['nullable', 'integer', 'min:1'],
            'bonus_tiers.*.bonus' => ['nullable'],
        ]);

        $tenantId = (int) app(CurrentTenant::class)->id();

        $bonusTiers = [];
        if (in_array($validated['type'], ['base_plus_commission', 'commission_only'], true) && !empty($request->input('bonus_tiers'))) {
            foreach ($request->input('bonus_tiers') as $tier) {
                $min = (int) ($tier['min_services'] ?? 0);
                $bonus = (int) preg_replace('/[^\d]/', '', (string) ($tier['bonus'] ?? '0'));
                if ($min > 0 && $bonus > 0) {
                    $bonusTiers[] = [
                        'min_services' => $min,
                        'bonus' => $bonus,
                    ];
                }
            }
            usort($bonusTiers, fn ($a, $b) => $a['min_services'] <=> $b['min_services']);
        }

        $alphaPenalty = (int) preg_replace('/[^\d]/', '', (string) $request->input('alpha_penalty_nominal', '0'));
        $rawBaseSalary = (int) preg_replace('/[^\d]/', '', (string) $request->input('base_salary', '0'));

        $commPercentage = null;
        if ($validated['type'] === 'commission_only') {
            $baseSalary = 0;
            $commModel = $request->input('commission_model', 'per_service');
            if ($commModel === 'percentage' || (!empty($request->input('commission_percentage')) && $commModel !== 'per_service')) {
                $commPercentage = max(1, min(100, (int) $request->input('commission_percentage', 30)));
                $targetBonusRules = !empty($bonusTiers)
                    ? ['commission_percentage' => $commPercentage, 'tiers' => $bonusTiers]
                    : ['commission_percentage' => $commPercentage];
            } else {
                $commPercentage = null;
                $targetBonusRules = !empty($bonusTiers) ? $bonusTiers : null;
            }
        } elseif ($validated['type'] === 'base_plus_commission') {
            $baseSalary = $rawBaseSalary;
            $targetBonusRules = !empty($bonusTiers) ? $bonusTiers : null;
        } else {
            $baseSalary = $rawBaseSalary;
            $targetBonusRules = null;
        }

        $payload = [
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'base_salary' => $baseSalary,
            'alpha_penalty_nominal' => 0,
            'period' => 'monthly',
            'target_bonus_rules' => $targetBonusRules,
            'is_active' => true,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('salary_schemes', 'commission_percentage')) {
            $payload['commission_percentage'] = $commPercentage;
        }

        $scheme = SalaryScheme::query()->create($payload);

        return redirect()->route('owner.payroll.rules')
            ->with('status', "Jabatan / Skema {$scheme->name} berhasil ditambahkan.");
    }

    public function updateSalaryScheme(Request $request, SalaryScheme $scheme): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:base_plus_commission,fixed,commission_only'],
            'base_salary' => ['nullable'],
            'alpha_penalty_nominal' => ['nullable'],
            'commission_percentage' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'bonus_tiers' => ['nullable', 'array'],
            'bonus_tiers.*.min_services' => ['nullable', 'integer', 'min:1'],
            'bonus_tiers.*.bonus' => ['nullable'],
        ]);

        $bonusTiers = [];
        if (in_array($validated['type'], ['base_plus_commission', 'commission_only'], true) && !empty($request->input('bonus_tiers'))) {
            foreach ($request->input('bonus_tiers') as $tier) {
                $min = (int) ($tier['min_services'] ?? 0);
                $bonus = (int) preg_replace('/[^\d]/', '', (string) ($tier['bonus'] ?? '0'));
                if ($min > 0 && $bonus > 0) {
                    $bonusTiers[] = [
                        'min_services' => $min,
                        'bonus' => $bonus,
                    ];
                }
            }
            usort($bonusTiers, fn ($a, $b) => $a['min_services'] <=> $b['min_services']);
        }

        $alphaPenalty = (int) preg_replace('/[^\d]/', '', (string) $request->input('alpha_penalty_nominal', '0'));
        $rawBaseSalary = (int) preg_replace('/[^\d]/', '', (string) $request->input('base_salary', '0'));

        $commPercentage = null;
        if ($validated['type'] === 'commission_only') {
            $baseSalary = 0;
            $commModel = $request->input('commission_model', 'per_service');
            if ($commModel === 'percentage' || (!empty($request->input('commission_percentage')) && $commModel !== 'per_service')) {
                $commPercentage = max(1, min(100, (int) $request->input('commission_percentage', 30)));
                $targetBonusRules = !empty($bonusTiers)
                    ? ['commission_percentage' => $commPercentage, 'tiers' => $bonusTiers]
                    : ['commission_percentage' => $commPercentage];
            } else {
                $commPercentage = null;
                $targetBonusRules = !empty($bonusTiers) ? $bonusTiers : null;
            }
        } elseif ($validated['type'] === 'base_plus_commission') {
            $baseSalary = $rawBaseSalary;
            $targetBonusRules = !empty($bonusTiers) ? $bonusTiers : null;
        } else {
            $baseSalary = $rawBaseSalary;
            $targetBonusRules = null;
        }

        $payload = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'base_salary' => $baseSalary,
            'alpha_penalty_nominal' => 0,
            'target_bonus_rules' => $targetBonusRules,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('salary_schemes', 'commission_percentage')) {
            $payload['commission_percentage'] = $commPercentage;
        }

        $scheme->update($payload);

        return redirect()->route('owner.payroll.rules')
            ->with('status', "Pengaturan skema {$scheme->name} berhasil diperbarui.");
    }

    public function destroySalaryScheme(SalaryScheme $scheme): RedirectResponse
    {
        $activeStaffCount = $scheme->memberships()->count();
        if ($activeStaffCount > 0) {
            return back()->with('error', "Skema '{$scheme->name}' tidak dapat dihapus karena masih digunakan oleh {$activeStaffCount} karyawan aktif.");
        }

        $name = $scheme->name;
        $scheme->delete();

        return redirect()->route('owner.payroll.rules')
            ->with('status', "Skema jabatan '{$name}' berhasil dihapus.");
    }

    public function storeServiceCommissionRule(Request $request): RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant && ! $tenant->hasFeature('commission_auto')) {
            return redirect()->route('owner.payroll.rules')
                ->with('warning', 'Fitur pengaturan komisi otomatis hanya tersedia pada paket Pro & Business. Silakan upgrade paket Anda.');
        }

        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'in:flat,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);
        $service = Service::query()->findOrFail($validated['service_id']);

        CommissionRule::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'scope' => 'service',
                'service_id' => $service->id,
                'membership_id' => null,
            ],
            [
                'name' => 'Komisi ' . $service->name,
                'type' => $validated['type'],
                'value' => (int) $validated['value'],
                'priority' => 20,
                'is_active' => true,
            ]
        );

        return redirect()->route('owner.payroll.rules')
            ->with('status', "Komisi layanan {$service->name} berhasil disimpan.");
    }

    public function schedules(Request $request): View
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        $barbers = Membership::query()
            ->where('role', \App\Enums\Role::Barber)
            ->where('is_active', true)
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['user', 'branch'])
            ->get();

        $schedules = BarberSchedule::query()
            ->with(['membership.user', 'branch'])
            ->get()
            ->groupBy('membership_id');

        // Statistik ringkas shift hari ini
        $currentDayOfWeek = (int) now()->dayOfWeek; // 0 = Minggu, 1 = Senin, ...
        $todayWorkingCount = 0;
        $todayOffCount = 0;

        foreach ($barbers as $b) {
            $sched = $schedules->get($b->id, collect())->firstWhere('day_of_week', $currentDayOfWeek);
            if ($sched && !$sched->is_off) {
                $todayWorkingCount++;
            } else {
                $todayOffCount++;
            }
        }

        return view('owner.schedules', [
            'barbers' => $barbers,
            'schedules' => $schedules,
            'branches' => $this->branchRepository->getAllBranches(),
            'todayWorkingCount' => $todayWorkingCount,
            'todayOffCount' => $todayOffCount,
            'currentDayOfWeek' => $currentDayOfWeek,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function editSchedule(Membership $membership): View
    {
        $membership->loadMissing(['user', 'branch']);
        $schedules = BarberSchedule::query()
            ->where('membership_id', $membership->id)
            ->get();
        $branches = $this->branchRepository->getAllBranches();

        return view('owner.schedule-edit', [
            'barber' => $membership,
            'schedules' => $schedules,
            'branches' => $branches,
        ]);
    }

    public function updateSchedule(Request $request, Membership $membership): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'days' => ['required', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.start_time' => ['required', 'string'],
            'days.*.end_time' => ['required', 'string'],
            'days.*.break_start' => ['nullable', 'string'],
            'days.*.break_end' => ['nullable', 'string'],
            'days.*.is_off' => ['nullable'],
        ]);

        $tenantId = (int) app(CurrentTenant::class)->id();

        $this->ownerManagementService->saveBarberSchedules(
            tenantId: $tenantId,
            membershipId: $membership->id,
            branchId: (int) $validated['branch_id'],
            days: $validated['days'],
        );

        $barberName = $membership->display_name ?? $membership->user?->name ?? 'Barber';

        return redirect()->route('owner.schedules')->with('status', "Jadwal shift & jam istirahat untuk {$barberName} berhasil diperbarui.");
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'id')->where('tenant_id', $tenantId),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'days' => ['required', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.start_time' => ['required', 'string'],
            'days.*.end_time' => ['required', 'string'],
            'days.*.break_start' => ['nullable', 'string'],
            'days.*.break_end' => ['nullable', 'string'],
            'days.*.is_off' => ['nullable'],
        ]);

        $this->ownerManagementService->saveBarberSchedules(
            tenantId: $tenantId,
            membershipId: (int) $validated['membership_id'],
            branchId: (int) $validated['branch_id'],
            days: $validated['days'],
        );

        return redirect()->route('owner.schedules')->with('status', 'Jadwal shift kerja barber berhasil disimpan.');
    }

    public function timeOffs(Request $request): View
    {
        $status = $request->input('status', 'all');
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        $barbers = Membership::query()
            ->where('role', \App\Enums\Role::Barber)
            ->where('is_active', true)
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['user', 'branch'])
            ->get();

        $timeOffsQuery = BarberTimeOff::query()
            ->with(['membership.user', 'membership.branch', 'approver'])
            ->when($branchId !== null && $branchId > 0, function ($q) use ($branchId): void {
                $q->whereHas('membership', fn ($m) => $m->where('branch_id', $branchId));
            })
            ->when($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true), function ($q) use ($status): void {
                $q->where('status', $status);
            })
            ->latest('date_from');

        $timeOffs = $timeOffsQuery->paginate(20)->withQueryString();

        // Statistik Time Off
        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $stats = BarberTimeOff::query()
            ->selectRaw("
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' AND date_from >= ? AND date_from <= ? THEN 1 END) as approved_month_count,
                COUNT(CASE WHEN status = 'approved' AND date_from <= ? AND date_to >= ? THEN 1 END) as today_off_barbers_count
            ", [$startOfMonth, $endOfMonth, $today, $today])
            ->first();

        $pendingCount = (int) ($stats->pending_count ?? 0);
        $approvedMonthCount = (int) ($stats->approved_month_count ?? 0);
        $todayOffBarbersCount = (int) ($stats->today_off_barbers_count ?? 0);

        // Pre-set status dot sidebar agar tidak query ulang di Blade
        app(CurrentTenant::class)->setHasPendingTimeOffDot($pendingCount > 0);

        return view('owner.time-offs', [
            'timeOffs' => $timeOffs,
            'barbers' => $barbers,
            'branches' => $this->branchRepository->getAllBranches(),
            'statusFilter' => $status,
            'branchFilter' => $branchId,
            'pendingCount' => $pendingCount,
            'approvedMonthCount' => $approvedMonthCount,
            'todayOffBarbersCount' => $todayOffBarbersCount,
        ]);
    }

    public function storeTimeOff(Request $request): RedirectResponse
    {
        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'id')->where('tenant_id', $tenantId),
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'type' => ['required', 'string', 'in:leave,sick,holiday'],
            'reason' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:pending,approved'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('time-offs', 'public');
        }

        $approvedBy = ($validated['status'] === 'approved') ? (int) $request->user()?->id : null;

        $this->ownerManagementService->createTimeOff($tenantId, $validated, $approvedBy);

        return back()->with('status', 'Data permohonan izin / libur barber berhasil ditambahkan.');
    }

    public function approveTimeOff(BarberTimeOff $timeOff, Request $request): RedirectResponse
    {
        $this->ownerManagementService->approveTimeOff($timeOff, (int) $request->user()?->id);

        return back()->with('status', 'Pengajuan libur / izin barber telah disetujui.');
    }

    public function rejectTimeOff(BarberTimeOff $timeOff, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->ownerManagementService->rejectTimeOff($timeOff, (int) $request->user()?->id, $validated['notes'] ?? null);

        return back()->with('status', 'Pengajuan libur / izin barber telah ditolak.');
    }

    public function attendanceScanner(Request $request): View
    {
        $branches = $this->branchRepository->getAllBranches();
        $selectedBranchId = $request->filled('branch_id')
            ? $request->integer('branch_id')
            : ($branches->first()?->id ?? null);
        $selectedBranch = $branches->firstWhere('id', $selectedBranchId) ?? $branches->first();

        $today = now()->format('Y-m-d');
        $tenantId = app(CurrentTenant::class)->id();

        $todayAttendances = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $today)
            ->when($selectedBranch !== null, fn ($q) => $q->where('branch_id', $selectedBranch->id))
            ->with(['membership.user', 'branch'])
            ->latest('updated_at')
            ->get();

        return view('owner.attendance-scanner', [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'todayAttendances' => $todayAttendances,
        ]);
    }

    public function processAttendanceScan(Request $request): JsonResponse
    {
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant === null) {
            return response()->json(['success' => false, 'message' => 'Sesi tenant tidak valid.'], 403);
        }

        $code = (string) $request->input('code', '');
        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'Data QR code kosong.'], 422);
        }

        // Dekode data QR (mendukung JSON terstruktur maupun string payload)
        $payload = null;
        if (str_starts_with(trim($code), '{')) {
            try {
                $payload = json_decode($code, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $payload = null;
            }
        }

        $membershipId = null;
        $token = null;
        $date = null;

        if (is_array($payload) && ($payload['type'] ?? '') === 'staff_attendance') {
            $membershipId = (int) ($payload['membership_id'] ?? 0);
            $token = (string) ($payload['token'] ?? '');
            $date = (string) ($payload['date'] ?? now()->toDateString());
        } elseif (preg_match('/^STAFF:(\d+):([a-zA-Z0-9]+)/', $code, $matches)) {
            $membershipId = (int) $matches[1];
            $token = $matches[2];
        }

        if (empty($membershipId) || empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Format QR code tidak valid atau bukan QR absensi staf resmi.',
            ], 422);
        }

        $membership = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('id', $membershipId)
            ->where('is_active', true)
            ->with(['user', 'branch'])
            ->first();

        if ($membership === null) {
            return response()->json([
                'success' => false,
                'message' => 'Data staf tidak ditemukan atau sudah tidak aktif di barbershop ini.',
            ], 404);
        }

        // Verifikasi keaslian token QR staf
        if (! $membership->verifyAttendanceQrToken($token, $date)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode QR absensi sudah kedaluwarsa atau tidak valid. Minta staf refresh layar HP.',
            ], 422);
        }

        // Tentukan cabang
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $branch = ($branchId ? Branch::query()->where('tenant_id', $tenant->id)->find($branchId) : null)
            ?? $membership->branch
            ?? Branch::query()->where('tenant_id', $tenant->id)->first();

        if ($branch === null) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang outlet belum ditentukan.',
            ], 422);
        }

        $today = now()->format('Y-m-d');
        $now = now();

        $attendance = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('membership_id', $membership->id)
            ->whereDate('date', $today)
            ->first();

        $staffName = $membership->display_name ?? $membership->user?->name ?? 'Staf';
        $roleLabel = match ($membership->role?->value) {
            'barber' => 'Staff Barber',
            'admin', 'cashier' => 'Kasir / Admin',
            default => 'Karyawan',
        };

        // 1. Kondisi: Sudah Absen Masuk dan Sudah Absen Pulang
        if ($attendance !== null && $attendance->check_in_at !== null && $attendance->check_out_at !== null) {
            return response()->json([
                'success' => true,
                'already_completed' => true,
                'action' => 'completed',
                'message' => "Staf {$staffName} sudah menyelesaikan absensi hari ini (Masuk: {$attendance->check_in_at->format('H:i')}, Pulang: {$attendance->check_out_at->format('H:i')}).",
                'staff' => [
                    'id' => $membership->id,
                    'name' => $staffName,
                    'role_label' => $roleLabel,
                    'branch' => $branch->name,
                ],
                'attendance' => [
                    'date' => $now->locale('id')->isoFormat('D MMMM Y'),
                    'status_label' => $attendance->status_label,
                    'check_in_at' => $attendance->check_in_at->format('H:i') . ' WIB',
                    'check_out_at' => $attendance->check_out_at->format('H:i') . ' WIB',
                    'late_minutes' => $attendance->late_minutes ?? 0,
                ],
            ]);
        }

        // 2. Kondisi: Sudah Absen Masuk tapi Belum Absen Pulang -> Catat Check-Out
        if ($attendance !== null && $attendance->check_in_at !== null) {
            $attendance->update([
                'check_out_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'action' => 'check_out',
                'message' => "Absen PULANG berhasil dicatat untuk {$staffName} pada pukul {$now->format('H:i')} WIB.",
                'staff' => [
                    'id' => $membership->id,
                    'name' => $staffName,
                    'role_label' => $roleLabel,
                    'branch' => $branch->name,
                ],
                'attendance' => [
                    'date' => $now->locale('id')->isoFormat('D MMMM Y'),
                    'status_label' => $attendance->status_label,
                    'check_in_at' => $attendance->check_in_at->format('H:i') . ' WIB',
                    'check_out_at' => $now->format('H:i') . ' WIB',
                    'late_minutes' => $attendance->late_minutes ?? 0,
                ],
            ]);
        }

        // 3. Kondisi: Belum Absen Masuk -> Catat Check-In
        $dayNum = $now->dayOfWeek; // 0=Minggu, 1=Senin, ...
        $sched = BarberSchedule::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $membership->id)
            ->where('day_of_week', $dayNum)
            ->first();

        $openingHours = $branch->opening_hours[$dayNum] ?? null;
        $shiftStartStr = '09:00';
        if ($sched !== null && $sched->start_time) {
            $shiftStartStr = substr((string) $sched->start_time, 0, 5);
        } elseif ($openingHours !== null && ! empty($openingHours['open'])) {
            $shiftStartStr = $openingHours['open'];
        }

        $scheduledStart = \Carbon\Carbon::parse("{$today} {$shiftStartStr}:00");
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
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]);
        } else {
            $attendance = Attendance::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'membership_id' => $membership->id,
                    'date' => $today,
                    'check_in_at' => $now,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                ]);
        }

        $statusDesc = $lateMinutes > 0 ? "Terlambat {$lateMinutes} menit" : "Tepat Waktu";
        $msg = "Absen MASUK berhasil dicatat untuk {$staffName} pada pukul {$now->format('H:i')} WIB ({$statusDesc}).";

        return response()->json([
            'success' => true,
            'action' => 'check_in',
            'message' => $msg,
            'staff' => [
                'id' => $membership->id,
                'name' => $staffName,
                'role_label' => $roleLabel,
                'branch' => $branch->name,
            ],
            'attendance' => [
                'date' => $now->locale('id')->isoFormat('D MMMM Y'),
                'status' => $status,
                'status_label' => $attendance->status_label,
                'check_in_at' => $now->format('H:i') . ' WIB',
                'check_out_at' => null,
                'late_minutes' => $lateMinutes,
            ],
        ]);
    }

    public function attendances(Request $request): View
    {
        $period = $request->input('period', 'today');
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $status = $request->input('status', 'all');
        $barberId = $request->filled('barber_id') ? $request->integer('barber_id') : null;

        $dateFrom = match ($period) {
            'today' => now()->startOfDay()->toDateString(),
            '7days' => now()->subDays(6)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'custom' => (string) $request->input('date_from', now()->toDateString()),
            default => now()->startOfDay()->toDateString(),
        };

        $dateTo = match ($period) {
            'today' => now()->endOfDay()->toDateString(),
            '7days' => now()->toDateString(),
            'this_month' => now()->endOfMonth()->toDateString(),
            'custom' => (string) $request->input('date_to', now()->toDateString()),
            default => now()->endOfDay()->toDateString(),
        };

        $tenantId = app(\App\Support\Tenancy\CurrentTenant::class)->get()?->id;
        $this->ownerManagementService->autoCloseDailyAttendances($tenantId, $dateFrom, $dateTo);

        $attendancesQuery = Attendance::query()
            ->with(['membership.user', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('membership_id', $barberId))
            ->when($status !== 'all' && in_array($status, ['present', 'late', 'leave', 'absent', 'sick', 'holiday'], true), fn ($q) => $q->where('status', $status))
            ->latest('date')
            ->latest('check_in_at');

        $attendances = (clone $attendancesQuery)->paginate(10)->withQueryString();

        // Statistik kehadiran untuk rentang waktu yang difilter
        $statsBase = Attendance::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('membership_id', $barberId));

        $stats = (clone $statsBase)
            ->selectRaw("
                COUNT(*) as total_records,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN status IN ('leave', 'absent', 'sick', 'holiday') THEN 1 END) as leave_count,
                COALESCE(SUM(late_minutes), 0) as total_late_minutes
            ")
            ->first();

        $totalRecords = (int) ($stats->total_records ?? 0);
        $presentCount = (int) ($stats->present_count ?? 0);
        $lateCount = (int) ($stats->late_count ?? 0);
        $leaveCount = (int) ($stats->leave_count ?? 0);
        $totalLateMinutes = (int) ($stats->total_late_minutes ?? 0);

        $barbers = Membership::query()
            ->where('role', \App\Enums\Role::Barber)
            ->where('is_active', true)
            ->when($branchId !== null && $branchId > 0, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['user', 'branch'])
            ->get();

        return view('owner.attendances', [
            'attendances' => $attendances,
            'barbers' => $barbers,
            'branches' => $this->branchRepository->getAllBranches(),
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statusFilter' => $status,
            'branchFilter' => $branchId,
            'barberFilter' => $barberId,
            'totalRecords' => $totalRecords,
            'presentCount' => $presentCount,
            'lateCount' => $lateCount,
            'leaveCount' => $leaveCount,
            'totalLateMinutes' => $totalLateMinutes,
        ]);
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'id')->where('tenant_id', $tenantId),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'string', 'in:present,late,sick,leave,holiday,absent'],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->ownerManagementService->recordAttendance($tenantId, $validated);

        return back()->with('status', 'Catatan absensi berhasil disimpan.');
    }

    public function updateAttendance(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'string', 'in:present,late,sick,leave,holiday,absent'],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $this->ownerManagementService->updateAttendance($attendance, $validated);

        return back()->with('status', 'Catatan absensi berhasil diperbarui.');
    }

    public function scheduleSettings(Request $request): View
    {
        $branches = $this->branchRepository->getAllBranches();
        $branches->load('tenant');
        $selectedBranchId = $request->filled('branch_id') ? $request->integer('branch_id') : ($branches->first()?->id ?? 1);
        $selectedBranch = $branches->firstWhere('id', $selectedBranchId) ?? $branches->first();

        if ($selectedBranch && empty($selectedBranch->qr_token)) {
            $selectedBranch->update([
                'qr_token' => \Illuminate\Support\Str::slug($selectedBranch->code . '-' . \Illuminate\Support\Str::random(12)),
            ]);
        }

        return view('owner.schedule-settings', [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
        ]);
    }

    public function updateScheduleSettings(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'booking_slot_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'radius_meters' => ['required', 'integer', 'min:5', 'max:5000'],
            'maps_url' => ['nullable', 'string', 'max:1000'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'opening_hours' => ['nullable', 'array'],
        ]);

        $newMapsUrl = !empty($validated['maps_url']) ? trim($validated['maps_url']) : null;
        $oldMapsUrl = !empty($branch->maps_url) ? trim($branch->maps_url) : null;

        // Jika maps_url dikosongkan oleh user, reset koordinat lat & lng ke null
        if (empty($newMapsUrl)) {
            $validated['maps_url'] = null;
            $validated['lat'] = null;
            $validated['lng'] = null;
        } else {
            // Ekstrak koordinat pin akurat dari maps_url baru
            $coords = OwnerManagementService::extractCoordinatesFromMapsUrl($newMapsUrl);
            if ($coords) {
                $validated['lat'] = $coords['lat'];
                $validated['lng'] = $coords['lng'];
            } elseif ($newMapsUrl !== $oldMapsUrl && (empty($validated['lat']) || $validated['lat'] == $branch->lat)) {
                // Jika URL diubah namun koordinat tidak dapat diekstrak, jangan gunakan koordinat lama link sebelumnya
                $validated['lat'] = null;
                $validated['lng'] = null;
            }
        }

        $this->ownerManagementService->updateBranchScheduleSettings($branch, $validated);

        return back()->with('status', "Pengaturan cabang {$branch->name} berhasil diperbarui.");
    }

    public function regenerateBranchQrToken(Branch $branch): RedirectResponse
    {
        $branch->update([
            'qr_token' => \Illuminate\Support\Str::slug($branch->code.'-'.\Illuminate\Support\Str::random(12)),
        ]);

        return back()->with('status', "Token QR absensi cabang {$branch->name} berhasil diperbarui.");
    }

    public function resolveMapsCoords(Request $request): JsonResponse
    {
        $url = $request->query('url');
        if (empty($url) || !is_string($url)) {
            return response()->json(['success' => false, 'message' => 'URL tidak boleh kosong.'], 400);
        }

        $coords = OwnerManagementService::extractCoordinatesFromMapsUrl($url);
        if ($coords) {
            return response()->json([
                'success' => true,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Titik koordinat tidak ditemukan pada URL ini.'], 404);
    }

    public function customers(Request $request): View
    {
        $search = $request->filled('search') ? (string) $request->string('search') : null;

        // Hanya tampilkan customer yang pernah berkunjung / menggunakan layanan pangkas di outlet ini
        $baseQuery = CustomerProfile::query()
            ->where(function ($q): void {
                $q->where('visit_count', '>', 0)
                    ->orWhereHas('orders', fn ($o) => $o->whereIn('payment_status', ['paid', 'completed']))
                    ->orWhereHas('queueTickets', fn ($qt) => $qt->whereIn('status', ['in_service', 'completed']));
            });

        $stats = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_customers,
                COUNT(CASE WHEN last_visit_at >= ? THEN 1 END) as active_customers,
                COALESCE(SUM(total_spent), 0) as total_spent_all
            ", [now()->subDays(30)->toDateTimeString()])
            ->first();

        $totalCustomers = (int) ($stats->total_customers ?? 0);
        $activeCustomers = (int) ($stats->active_customers ?? 0);
        $totalSpentAll = (int) ($stats->total_spent_all ?? 0);

        $customersQuery = (clone $baseQuery)
            ->withCount(['orders', 'bookings', 'queueTickets'])
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('last_visit_at');

        $customers = $customersQuery->paginate(15)->withQueryString();

        return view('owner.customers', [
            'customers' => $customers,
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'totalSpentAll' => $totalSpentAll,
        ]);
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) app(CurrentTenant::class)->id();

        $this->ownerManagementService->createCustomer($tenantId, $validated);

        return back()->with('status', "Customer {$validated['name']} berhasil ditambahkan ke database.");
    }

    public function updateCustomer(Request $request, CustomerProfile $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->ownerManagementService->updateCustomer($customer, $validated);

        return back()->with('status', "Data customer {$customer->name} berhasil diperbarui.");
    }

    public function settings(): View
    {
        $tenant = app(CurrentTenant::class)->get();
        $branches = $this->branchRepository->getAllBranches();

        return view('owner.settings', [
            'tenant' => $tenant,
            'branches' => $branches,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg,jpg,jpeg,webp', 'max:2048'],
            'remove_favicon' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:100'],
            'qris_account' => ['nullable', 'string', 'max:100'],
            'qris_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_qris_image' => ['nullable', 'boolean'],
            'booking_advance_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:180'],
            'announcement' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama barbershop wajib diisi.',
            'brand_color.required' => 'Warna brand wajib ditentukan.',
            'brand_color.regex' => 'Format kode warna harus hex 6 digit (contoh: #ff0000).',
            'logo.image' => 'File logo harus berupa gambar.',
            'logo.mimes' => 'Format logo harus PNG, JPG, JPEG, WEBP, atau SVG.',
            'logo.max' => 'Ukuran file logo maksimal 2MB.',
            'favicon.file' => 'File favicon harus berupa berkas yang sah.',
            'favicon.mimes' => 'Format favicon harus ICO, PNG, SVG, JPG, JPEG, atau WEBP.',
            'favicon.max' => 'Ukuran file favicon maksimal 2MB.',
        ]);

        /** @var Tenant $tenant */
        $tenant = app(CurrentTenant::class)->get();

        if (! $tenant->hasFeature('brand_color')) {
            $validated['brand_color'] = $tenant->brand_color ?? '#f59e0b';
            // Starter tidak memiliki akses custom logo & warna
        } else {
            if ($request->boolean('remove_logo')) {
                if (! empty($tenant->logo_path) && ! str_starts_with($tenant->logo_path, 'http')) {
                    Storage::disk('public')->delete($tenant->logo_path);
                }
                $validated['logo_path'] = null;
            } elseif ($request->hasFile('logo')) {
                if (! empty($tenant->logo_path) && ! str_starts_with($tenant->logo_path, 'http')) {
                    Storage::disk('public')->delete($tenant->logo_path);
                }
                $path = $request->file('logo')->store('logos', 'public');
                $validated['logo_path'] = $path;
            }
        }

        // Custom Favicon (Khusus Business Tier)
        $hasCustomFaviconFile = false;
        if ($tenant->hasFeature('custom_favicon')) {
            if ($request->boolean('remove_favicon')) {
                $oldPath = $tenant->settings['favicon_path'] ?? null;
                if (! empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                $validated['favicon_path'] = null;
                FaviconService::clearTenantFavicon($tenant);
            } elseif ($request->hasFile('favicon')) {
                $oldPath = $tenant->settings['favicon_path'] ?? null;
                if (! empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                $storedFavicon = FaviconService::storeCustomFaviconForTenant($tenant, $request->file('favicon'));
                $validated['favicon_path'] = $storedFavicon;
                $hasCustomFaviconFile = true;
            }
        }

        // Gambar QRIS Barbershop
        if ($request->boolean('remove_qris_image')) {
            $oldQris = $tenant->settings['qris_image_path'] ?? null;
            if (! empty($oldQris) && Storage::disk('public')->exists($oldQris)) {
                Storage::disk('public')->delete($oldQris);
            }
            $validated['qris_image_path'] = null;
        } elseif ($request->hasFile('qris_image')) {
            $oldQris = $tenant->settings['qris_image_path'] ?? null;
            if (! empty($oldQris) && Storage::disk('public')->exists($oldQris)) {
                Storage::disk('public')->delete($oldQris);
            }
            $validated['qris_image_path'] = $request->file('qris_image')->store('qris', 'public');
        }

        unset(
            $validated['logo'],
            $validated['remove_logo'],
            $validated['favicon'],
            $validated['remove_favicon'],
            $validated['qris_image'],
            $validated['remove_qris_image']
        );

        $updatedTenant = $this->ownerManagementService->updateTenantSettings($tenant, $validated);

        // Otomatis generate favicon tenant dengan ikon resmi platform + warna brand barbershop
        // jika tenant tidak mengunggah file favicon kustom tersendiri
        if (! $hasCustomFaviconFile && empty($updatedTenant->settings['favicon_path'])) {
            FaviconService::generateForTenant($updatedTenant);
        }

        return back()->with('status', 'Pengaturan barbershop berhasil disimpan.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Nama lengkap pemilik akun wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Email login pemilik wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar oleh pengguna lain.',
            'phone.max' => 'Nomor WhatsApp / telepon maksimal 30 karakter.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        // Sinkronkan display_name membership owner jika ada
        Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('user_id', $user->id)
            ->where('role', \App\Enums\Role::Owner)
            ->update(['display_name' => $validated['name']]);

        return back()->with('status_profile', 'Data profil pemilik akun berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok / salah.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status_password', 'Kata sandi akun Anda berhasil diperbarui.');
    }

    public function subscription(): View
    {
        /** @var Tenant|null $tenant */
        $tenant = app(CurrentTenant::class)->get();
        $tenantSub = $tenant?->activeSubscription() ?? $tenant?->subscriptions()->withoutGlobalScope('tenant')->with(['plan.features'])->latest('id')->first();
        $plans = Plan::query()->where('is_active', true)->with('features')->orderBy('price_monthly')->get();
        $invoices = $tenant
            ? SubscriptionInvoice::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->latest('id')->get()
            : collect();

        return view('owner.subscription', [
            'tenant' => $tenant,
            'tenantSub' => $tenantSub,
            'plans' => $plans,
            'invoices' => $invoices,
        ]);
    }

    public function upgradeSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        /** @var Tenant $tenant */
        $tenant = app(CurrentTenant::class)->get();
        $targetPlan = Plan::findOrFail((int) $validated['plan_id']);

        $existingSub = $tenant->activeSubscription() ?? $tenant->subscriptions()->withoutGlobalScope('tenant')->with('plan')->latest('id')->first();

        // Tentukan Tipe: activation, renewal, upgrade, downgrade
        $hasPaidHistory = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->exists();

        $currentPlanId = $existingSub?->plan_id;
        $currentPrice = $existingSub?->plan?->price_monthly ?? $existingSub?->price ?? 0;

        // Penanganan Paket Gratis (Rp0): Bypass payment gateway, aktifkan seketika
        if ($targetPlan->price_monthly <= 0) {
            $this->subscriptionActivationService->activateFreePlan($tenant, $targetPlan, $request->user());

            return redirect()->route('owner.subscription')
                ->with('status', "Paket {$targetPlan->name} (Gratis) berhasil diaktifkan seketika untuk barbershop Anda.");
        }

        if (! $hasPaidHistory) {
            $type = 'activation';
        } elseif ($currentPlanId !== null && $currentPlanId === $targetPlan->id) {
            $type = 'renewal';
        } elseif ($targetPlan->price_monthly > $currentPrice) {
            $type = 'upgrade';
        } else {
            $type = 'downgrade';
        }

        // Aturan Perpanjangan (Renewal): Hanya dibuka jika mendekati 7 hari (sisa <= 7 hari) atau sudah expired/past_due
        if ($type === 'renewal') {
            $endsAt = $existingSub?->ends_at;
            $diffDays = $endsAt ? (int) ceil(now()->floatDiffInDays($endsAt, false)) : null;
            $isNearExpiry = ($diffDays === null || $diffDays <= 7 || ($endsAt && $endsAt->isPast()) || $existingSub?->status === SubscriptionStatus::PastDue);

            if (! $isNearExpiry && $diffDays !== null) {
                return redirect()->route('owner.subscription')->with('error', "Perpanjangan paket {$targetPlan->name} hanya dibuka saat mendekati 7 hari masa aktif berakhir (saat ini masih {$diffDays} hari lagi).");
            }
        }

        // Cek apakah ada invoice yang masih belum lunas (unpaid atau pending)
        $pendingInvoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['unpaid', 'pending'])
            ->latest('id')
            ->first();

        $targetPeriodStart = ($type === 'renewal' && $existingSub && $existingSub->ends_at && $existingSub->ends_at->isFuture())
            ? $existingSub->ends_at
            : now();
        $targetPeriodEnd = $targetPeriodStart->copy()->addDays(30);

        if ($pendingInvoice !== null) {
            // Update invoice yang ada ke target plan dan tipe baru
            $pendingInvoice->update([
                'subscription_id' => $existingSub?->id,
                'target_plan_id' => $targetPlan->id,
                'amount' => $targetPlan->price_monthly,
                'type' => $type,
                'period_start' => $targetPeriodStart,
                'period_end' => $targetPeriodEnd,
                'status' => 'unpaid', // reset ke unpaid jika ganti paket
                'gateway' => 'xendit',
                'due_at' => now()->addDays(2),
            ]);
            $invoice = $pendingInvoice;
        } else {
            // Terbitkan invoice baru berstatus 'unpaid' dengan gateway Xendit
            $invoice = SubscriptionInvoice::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $existingSub?->id,
                'target_plan_id' => $targetPlan->id,
                'type' => $type,
                'number' => 'INV-SUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'amount' => $targetPlan->price_monthly,
                'period_start' => $targetPeriodStart,
                'period_end' => $targetPeriodEnd,
                'status' => 'unpaid',
                'due_at' => now()->addDays(2),
                'paid_at' => null,
                'gateway' => 'xendit',
            ]);
        }

        // Generate invoice Xendit secara otomatis
        try {
            $this->xenditPaymentService->getOrCreateSubscriptionInvoice($invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Generate Xendit subscription invoice failed: '.$e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }

        $typeName = match ($type) {
            'activation', 'initial' => 'aktivasi',
            'renewal' => 'perpanjangan',
            'upgrade' => 'upgrade',
            'downgrade' => 'downgrade',
        };

        return redirect()->route('owner.subscription.invoice', $invoice->id)->with('status', "Tagihan {$typeName} paket {$targetPlan->name} ({$invoice->number}) berhasil dibuat. Silakan selesaikan pembayaran instan di bawah.");
    }

    public function showSubscriptionInvoice(Request $request, int $id): View|RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app(CurrentTenant::class)->get();

        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->with(['targetPlan', 'subscription.plan'])
            ->findOrFail($id);

        // Fitur Simulasi Pembayaran Lokal / Dev Test
        if ($request->boolean('mock_xendit_pay') && $invoice->status !== 'paid') {
            $this->subscriptionActivationService->activateFromInvoice(
                invoice: $invoice,
                payload: [
                    'gateway' => 'xendit_simulator',
                    'gateway_ref' => 'SIMULATED-ONLINE-PAID',
                    'payment_method' => 'QRIS',
                    'payment_channel' => 'SIMULATOR',
                    'paid_at' => now(),
                ],
                actor: $request->user(),
                defaultGateway: 'xendit_simulator',
                defaultGatewayRef: 'SIMULATED-ONLINE-PAID'
            );

            return redirect()->route('owner.subscription.invoice', $invoice->id)
                ->with('status', 'Simulasi pembayaran berhasil! Langganan barbershop Anda telah aktif seketika.');
        }

        // Endpoint Cek Status Pembayaran (untuk polling otomatis frontend)
        if ($request->wantsJson() || $request->boolean('check_status')) {
            return response()->json([
                'status' => $invoice->status,
                'is_paid' => $invoice->status === 'paid',
                'paid_at' => $invoice->paid_at?->translatedFormat('d M Y, H:i'),
            ]);
        }

        // Pastikan invoice Xendit siap jika masih unpaid dan berbayar
        if ($invoice->status === 'unpaid' && ! $invoice->isFree()) {
            try {
                $this->xenditPaymentService->getOrCreateSubscriptionInvoice($invoice);
                $invoice->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Generate Xendit on showSubscriptionInvoice failed: '.$e->getMessage());
            }
        }

        $bankAccounts = SubscriptionInvoice::platformBankAccounts();

        $currentSub = $tenant->activeSubscription() ?? $tenant->subscriptions()->withoutGlobalScope('tenant')->with('plan')->latest('id')->first();
        $endsAt = $currentSub?->ends_at;
        $currentDaysRemaining = ($endsAt && $endsAt->isFuture()) ? (int) ceil(now()->floatDiffInDays($endsAt, false)) : 0;

        return view('owner.subscription-invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'currentSub' => $currentSub,
            'bankAccounts' => $bankAccounts,
            'currentDaysRemaining' => $currentDaysRemaining,
        ]);
    }

    public function submitPaymentProof(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = app(CurrentTenant::class)->get();

        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Tagihan ini sudah LUNAS dan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:transfer,cash'],
            'bank_name' => ['nullable', 'string', 'max:50', 'required_if:payment_method,transfer'],
            'proof' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'proof.required' => 'Wajib mengunggah foto atau struk bukti pembayaran.',
            'proof.file' => 'Bukti pembayaran harus berupa file yang valid.',
            'proof.mimes' => 'Format file tidak didukung. Hanya file format PNG, JPG, JPEG, WEBP, atau PDF yang diizinkan.',
            'proof.max' => 'Ukuran file bukti pembayaran melebihi batas maksimal 2MB (2.048 KB). Silakan gunakan file yang lebih kecil.',
            'bank_name.required_if' => 'Silakan pilih rekening bank tujuan transfer.',
        ]);

        $proofFile = $request->file('proof');
        $proofPath = $proofFile->store('subscription-proofs', 'public');

        // Hapus file bukti lama jika ada
        if (! empty($invoice->proof_path) && Storage::disk('public')->exists($invoice->proof_path)) {
            Storage::disk('public')->delete($invoice->proof_path);
        }

        $invoice->update([
            'payment_method' => $validated['payment_method'],
            'bank_name' => $validated['payment_method'] === 'transfer' ? ($validated['bank_name'] ?? null) : null,
            'proof_path' => $proofPath,
            'proof_uploaded_at' => now(),
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending', // Menunggu Verifikasi Superadmin
        ]);

        return redirect()->route('owner.subscription.invoice', $invoice->id)->with('status', 'Bukti pembayaran berhasil diunggah! Tagihan Anda sekarang berstatus "Menunggu Verifikasi Superadmin". Sistem akan aktif segera setelah dikonfirmasi.');
    }

    public function cancelSubscriptionInvoice(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = app(CurrentTenant::class)->get();

        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Tagihan yang sudah LUNAS tidak dapat dibatalkan.');
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Wajib memberikan alasan pembatalan tagihan.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        // Hapus file bukti transfer jika sempat diupload
        if (! empty($invoice->proof_path) && Storage::disk('public')->exists($invoice->proof_path)) {
            Storage::disk('public')->delete($invoice->proof_path);
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_by' => 'owner',
            'cancelled_at' => now(),
        ]);

        return redirect()->route('owner.subscription')->with('status', "Tagihan {$invoice->number} berhasil dibatalkan. Alasan: \"{$validated['cancellation_reason']}\". Anda dapat memilih kembali paket yang diinginkan.");
    }
}
