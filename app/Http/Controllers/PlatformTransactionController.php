<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlatformTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $selectedMonth = (string) $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        $startDate = Carbon::parse($selectedMonth . '-01')->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();

        $prevMonthDate = (clone $startDate)->subMonth();
        $prevMonthStart = (clone $prevMonthDate)->startOfMonth();
        $prevMonthEnd = (clone $prevMonthDate)->endOfMonth();

        $search = $request->filled('search') ? (string) $request->string('search') : null;
        $tenantId = $request->filled('tenant_id') ? $request->integer('tenant_id') : null;

        // 1. Metrik Keuangan Global Se-Platform pada Bulan Terpilih (Paid Orders Only)
        $totalGmv = (int) Order::query()
            ->withoutGlobalScope('tenant')
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('grand_total');

        $totalPaidCount = (int) Order::query()
            ->withoutGlobalScope('tenant')
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        $prevMonthGmv = (int) Order::query()
            ->withoutGlobalScope('tenant')
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$prevMonthStart, $prevMonthEnd])
            ->sum('grand_total');

        $growthPercentage = $prevMonthGmv > 0
            ? round((($totalGmv - $prevMonthGmv) / $prevMonthGmv) * 100, 1)
            : ($totalGmv > 0 ? 100.0 : 0.0);

        $aov = $totalPaidCount > 0 ? (int) round($totalGmv / $totalPaidCount) : 0;

        // 2. Daftar Kinerja Barbershop / Tenant pada Bulan Terpilih
        $tenants = Tenant::query()
            ->with(['subscriptions.plan'])
            ->withCount('branches')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->withSum([
                'orders as month_gmv' => fn ($q) => $q->withoutGlobalScope('tenant')
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate, $endDate]),
            ], 'grand_total')
            ->withCount([
                'orders as month_orders_count' => fn ($q) => $q->withoutGlobalScope('tenant')
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate, $endDate]),
            ])
            ->orderByDesc('month_gmv')
            ->paginate(15)
            ->withQueryString();

        // 3. Tren 6 Bulan Terakhir untuk Visualisasi Grafik Platform
        $monthlyTrends = [];
        $peakGmv = 0;
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd = (clone $mStart)->endOfMonth();
            $mGmv = (int) Order::query()
                ->withoutGlobalScope('tenant')
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$mStart, $mEnd])
                ->sum('grand_total');
            $mCount = (int) Order::query()
                ->withoutGlobalScope('tenant')
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$mStart, $mEnd])
                ->count();

            if ($mGmv > $peakGmv) {
                $peakGmv = $mGmv;
            }

            $monthlyTrends[] = [
                'month_key' => $mStart->format('Y-m'),
                'label' => $mStart->translatedFormat('M Y'),
                'gmv' => $mGmv,
                'count' => $mCount,
                'is_current' => $mStart->format('Y-m') === $selectedMonth,
            ];
        }

        // Hitung persentase tinggi bar grafik tren
        $safePeak = max($peakGmv, 1);
        foreach ($monthlyTrends as $idx => $trend) {
            $pct = $trend['gmv'] > 0 ? (int) round(($trend['gmv'] / $safePeak) * 100) : 8;
            $monthlyTrends[$idx]['height_percentage'] = max(8, $pct);
        }

        // Pilihan Bulan (12 Bulan ke Belakang)
        $monthOptions = [];
        for ($j = 0; $j < 12; $j++) {
            $optDate = now()->subMonths($j);
            $monthOptions[] = [
                'value' => $optDate->format('Y-m'),
                'label' => $optDate->translatedFormat('F Y'),
            ];
        }

        $allTenants = Tenant::query()->orderBy('name')->get(['id', 'name', 'slug']);

        return view('platform.transactions', [
            'selectedMonth' => $selectedMonth,
            'selectedMonthLabel' => $startDate->translatedFormat('F Y'),
            'monthOptions' => $monthOptions,
            'tenants' => $tenants,
            'allTenants' => $allTenants,
            'totalGmv' => $totalGmv,
            'totalPaidCount' => $totalPaidCount,
            'prevMonthGmv' => $prevMonthGmv,
            'growthPercentage' => $growthPercentage,
            'aov' => $aov,
            'monthlyTrends' => $monthlyTrends,
            'search' => $search,
            'tenantId' => $tenantId,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $selectedMonth = (string) $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        $startDate = Carbon::parse($selectedMonth . '-01')->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();
        $search = $request->filled('search') ? (string) $request->string('search') : null;
        $tenantId = $request->filled('tenant_id') ? $request->integer('tenant_id') : null;

        $tenants = Tenant::query()
            ->with(['subscriptions.plan'])
            ->withCount('branches')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->withSum([
                'orders as month_gmv' => fn ($q) => $q->withoutGlobalScope('tenant')
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate, $endDate]),
            ], 'grand_total')
            ->withCount([
                'orders as month_orders_count' => fn ($q) => $q->withoutGlobalScope('tenant')
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate, $endDate]),
            ])
            ->orderByDesc('month_gmv')
            ->get();

        $totalTenants = $tenants->count();
        $totalBranches = (int) $tenants->sum('branches_count');
        $totalOrders = (int) $tenants->sum('month_orders_count');
        $totalGmv = (int) $tenants->sum('month_gmv');
        $aov = $totalOrders > 0 ? (int) round($totalGmv / $totalOrders) : 0;

        $rows = [];
        foreach ($tenants as $idx => $tenant) {
            $sub = $tenant->subscriptions->first();
            $tenantGmv = (int) ($tenant->month_gmv ?? 0);
            $sharePct = $totalGmv > 0 ? round(($tenantGmv / $totalGmv) * 100, 1) : 0;

            $rows[] = [
                $idx + 1,
                $tenant->id,
                $tenant->name,
                $tenant->slug,
                $sub?->plan?->name ?? 'Starter',
                $tenant->branches_count,
                ucfirst((string) $tenant->status->value),
                (int) ($tenant->month_orders_count ?? 0),
                $tenantGmv,
                $sharePct . '%',
            ];
        }

        $excelService = app(\App\Services\ExcelExportService::class);

        return $excelService->downloadXlsx(
            filename: 'rekap-pendapatan-platform-' . $selectedMonth . '.xlsx',
            data: [
                'title' => 'Laporan Pendapatan & Volume Transaksi Bulanan Se-Platform',
                'meta' => [
                    'Periode Bulan' => $startDate->translatedFormat('F Y') . " ({$selectedMonth})",
                    'Waktu Ekspor' => now()->translatedFormat('l, d F Y - H:i') . ' WIB',
                    'Penyusun' => 'Superadmin SaaS Barbershop',
                ],
                'kpi_summary' => [
                    'Total Mitra Barbershop Terdata' => $totalTenants,
                    'Total Cabang Operasional' => $totalBranches,
                    'Total Transaksi Pangkas Selesai' => $totalOrders,
                    'Total Perputaran Omzet Platform (GMV)' => $totalGmv,
                    'Rata-Rata Nilai Transaksi per Tiket (AOV)' => $aov,
                ],
                'headers' => [
                    'Peringkat',
                    'ID Tenant',
                    'Nama Barbershop',
                    'Slug URL',
                    'Paket Langganan',
                    'Jumlah Cabang',
                    'Status Tenant',
                    'Total Transaksi',
                    'Total Omzet GMV (Rp)',
                    'Kontribusi (%)',
                ],
                'alignments' => [
                    'center',
                    'center',
                    'left',
                    'left',
                    'center',
                    'center',
                    'center',
                    'center',
                    'right',
                    'right',
                ],
                'formats' => [
                    'integer',
                    'integer',
                    'text',
                    'text',
                    'text',
                    'integer',
                    'text',
                    'integer',
                    'currency',
                    'text',
                ],
                'rows' => $rows,
                'totals' => [
                    'TOTAL KESELURUHAN',
                    '',
                    '',
                    '',
                    '',
                    $totalBranches,
                    '',
                    $totalOrders,
                    $totalGmv,
                    '100%',
                ],
                'theme_color' => '0F172A', // Slate 900
            ]
        );
    }
}
