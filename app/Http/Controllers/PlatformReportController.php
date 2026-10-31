<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlatformReportController extends Controller
{
    public function index(): View
    {
        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('status', TenantStatus::Active)->count();
        $trialTenants = Tenant::query()->where('status', TenantStatus::Trial)->count();
        $suspendedTenants = Tenant::query()->where('status', TenantStatus::Suspended)->count();

        $totalBranches = Branch::query()->withoutGlobalScope('tenant')->count();

        // Estimasi Monthly Recurring Revenue (MRR) dari Langganan SaaS
        $activeSubscriptions = Subscription::query()
            ->withoutGlobalScope('tenant')
            ->where('status', SubscriptionStatus::Active)
            ->with('plan')
            ->get();

        $mrr = (int) $activeSubscriptions->sum(fn ($s) => $s->price ?: ($s->plan?->price_monthly ?? 0));

        // Total GMV Transaksi Seluruh Barbershop
        $totalGmv = (int) Order::query()
            ->withoutGlobalScope('tenant')
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        // Distribusi Penggunaan Paket
        $plansWithCount = Plan::query()
            ->withCount(['subscriptions' => fn ($q) => $q->withoutGlobalScope('tenant')->where('status', SubscriptionStatus::Active)])
            ->get();

        // Tren Barbershop Bergabung (6 Bulan Terakhir)
        $driver = Tenant::query()->getConnection()->getDriverName();
        $dateFormatSql = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : 'DATE_FORMAT(created_at, "%Y-%m")';

        $monthlyGrowth = Tenant::query()
            ->selectRaw("{$dateFormatSql} as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(6)
            ->get()
            ->reverse();

        return view('platform.reports', [
            'totalTenants' => $totalTenants,
            'activeTenants' => $activeTenants,
            'trialTenants' => $trialTenants,
            'suspendedTenants' => $suspendedTenants,
            'totalBranches' => $totalBranches,
            'mrr' => $mrr,
            'totalGmv' => $totalGmv,
            'plansWithCount' => $plansWithCount,
            'monthlyGrowth' => $monthlyGrowth,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $tenants = Tenant::query()
            ->with(['owner', 'subscriptions.plan'])
            ->withCount('branches')
            ->orderBy('id')
            ->get();

        $totalTenants = $tenants->count();
        $activeTenants = $tenants->where('status.value', 'active')->count();
        $trialTenants = $tenants->where('status.value', 'trial')->count();
        $totalBranches = (int) $tenants->sum('branches_count');
        $totalMrr = (int) $tenants->sum(function ($t) {
            $sub = $t->subscriptions->first();
            return $sub ? ($sub->price ?: ($sub->plan?->price_monthly ?? 0)) : 0;
        });

        $rows = [];
        foreach ($tenants as $idx => $tenant) {
            $sub = $tenant->subscriptions->first();
            $price = (int) ($sub?->price ?? ($sub?->plan?->price_monthly ?? 0));

            $rows[] = [
                $idx + 1,
                $tenant->id,
                $tenant->name,
                $tenant->slug,
                $tenant->owner?->name ?? '-',
                $tenant->owner?->email ?? '-',
                $tenant->owner?->phone ?? '-',
                $tenant->branches_count,
                $sub?->plan?->name ?? 'Belum Ada',
                $price,
                ucfirst((string) $tenant->status->value),
                $sub?->ends_at ? $sub->ends_at->translatedFormat('d M Y') : ($tenant->trial_ends_at ? $tenant->trial_ends_at->translatedFormat('d M Y') : '-'),
                $tenant->created_at->translatedFormat('d M Y - H:i'),
            ];
        }

        $excelService = app(\App\Services\ExcelExportService::class);

        return $excelService->downloadXlsx(
            filename: 'laporan-ekosistem-saas-' . now()->format('Ymd-His') . '.xlsx',
            data: [
                'title' => 'Laporan Ekosistem & Pertumbuhan Mitra SaaS',
                'meta' => [
                    'Waktu Ekspor' => now()->translatedFormat('l, d F Y - H:i') . ' WIB',
                    'Penyusun' => 'Superadmin SaaS Barbershop',
                ],
                'kpi_summary' => [
                    'Total Mitra Barbershop Terdaftar' => $totalTenants,
                    'Mitra Status Aktif (Berlangganan)' => $activeTenants,
                    'Mitra Status Uji Coba (Trial 14 Hari)' => $trialTenants,
                    'Total Seluruh Cabang Terdaftar' => $totalBranches,
                    'Estimasi Monthly Recurring Revenue (MRR)' => $totalMrr,
                ],
                'headers' => [
                    'No',
                    'ID Tenant',
                    'Nama Barbershop',
                    'Slug URL',
                    'Nama Owner',
                    'Email Owner',
                    'WhatsApp Owner',
                    'Jumlah Cabang',
                    'Paket Langganan',
                    'Tarif (Rp/bln)',
                    'Status Akun',
                    'Masa Aktif Berakhir',
                    'Tanggal Bergabung',
                ],
                'alignments' => [
                    'center',
                    'center',
                    'left',
                    'left',
                    'left',
                    'left',
                    'center',
                    'center',
                    'center',
                    'right',
                    'center',
                    'center',
                    'center',
                ],
                'formats' => [
                    'integer',
                    'integer',
                    'text',
                    'text',
                    'text',
                    'text',
                    'text',
                    'integer',
                    'text',
                    'currency',
                    'text',
                    'text',
                    'text',
                ],
                'rows' => $rows,
                'totals' => [
                    'TOTAL',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    $totalBranches,
                    '',
                    $totalMrr,
                    '',
                    '',
                    '',
                ],
                'theme_color' => '0F172A', // Slate 900
            ]
        );
    }
}
