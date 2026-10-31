<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PlatformSubscriptionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->filled('status') ? (string) $request->string('status') : 'all';
        $tenantId = $request->filled('tenant_id') ? $request->integer('tenant_id') : null;
        $search = $request->filled('search') ? trim((string) $request->string('search')) : null;

        $baseQuery = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->with([
                'tenant.owner',
                'subscription.plan',
                'targetPlan',
            ])
            ->where('status', '!=', 'unpaid')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($tenantId !== null && $tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('number', 'like', "%{$search}%")
                        ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
                        ->orWhereHas('tenant.owner', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        // Metrik Finansial SaaS Global
        $totalMonthlyRevenue = (int) SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $totalAllTimeRevenue = (int) SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'paid')
            ->sum('amount');

        $pendingCount = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'pending')
            ->count();

        $paidCount = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'paid')
            ->count();

        $allTenants = Tenant::query()->orderBy('name')->get();
        $invoices = (clone $baseQuery)->latest('id')->paginate(15)->withQueryString();

        return view('platform.subscriptions', [
            'invoices' => $invoices,
            'allTenants' => $allTenants,
            'statusFilter' => $status,
            'tenantFilter' => $tenantId,
            'searchFilter' => $search,
            'totalMonthlyRevenue' => $totalMonthlyRevenue,
            'totalAllTimeRevenue' => $totalAllTimeRevenue,
            'pendingCount' => $pendingCount,
            'paidCount' => $paidCount,
        ]);
    }

    public function confirmPayment(Request $request, int $id): RedirectResponse
    {
        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant', 'targetPlan', 'subscription.plan'])
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return back()->with('error', "Tagihan {$invoice->number} sudah berstatus LUNAS sebelumnya.");
        }

        DB::transaction(function () use ($invoice, $request): void {
            // 1. Update Invoice menjadi Paid
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway' => $request->input('gateway', $invoice->payment_method ?? 'manual_transfer'),
                'gateway_ref' => $request->input('gateway_ref', 'CONFIRMED-BY-SUPERADMIN'),
            ]);

            // 2. Tentukan target plan & perpanjang masa aktif subscription
            $sub = $invoice->subscription;
            $tenant = $invoice->tenant;
            $targetPlanId = $invoice->target_plan_id ?? $sub?->plan_id;

            // Aturan Pengaktifan Langganan:
            // - Upgrade / Downgrade: Sisa masa aktif tidak ditumpuk, diganti murni 30 hari ke depan (now()->addDays(30))
            // - Renewal (Perpanjangan paket sama): Menambah sisa masa aktif (+30 hari)
            $isRenewal = ($invoice->type === 'renewal') || ($sub && $targetPlanId && $sub->plan_id == $targetPlanId && ! in_array($invoice->type, ['upgrade', 'downgrade'], true));

            if ($isRenewal) {
                $baseDate = ($sub && $sub->ends_at && $sub->ends_at->isFuture())
                    ? $sub->ends_at
                    : now();
                $newEndsAt = $baseDate->copy()->addDays(30);
            } else {
                $newEndsAt = now()->addDays(30);
            }

            if ($sub !== null) {
                $sub->update([
                    'plan_id' => $targetPlanId,
                    'status' => SubscriptionStatus::Active,
                    'price' => $invoice->amount,
                    'starts_at' => now(),
                    'ends_at' => $newEndsAt,
                ]);
            } elseif ($tenant !== null && $targetPlanId !== null) {
                $sub = Subscription::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $targetPlanId,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'ends_at' => $newEndsAt,
                    'price' => $invoice->amount,
                    'auto_renew' => true,
                ]);
                $invoice->update(['subscription_id' => $sub->id]);
            }

            // 3. Reset kustomisasi logo, tema, white-label, dan subdomain jika turun/beralih paket
            $targetPlan = $invoice->targetPlan ?? Plan::find($targetPlanId);
            if ($tenant !== null && $targetPlan !== null) {
                $tenant->resetPlanRestrictedFeatures($targetPlan->code);
            }

            // 4. Jika status tenant sempat suspended, aktifkan kembali
            if ($tenant !== null && $tenant->status === TenantStatus::Suspended) {
                $tenant->update(['status' => TenantStatus::Active]);
            }

            // 5. Catat ke Audit Log
            $this->auditLogger->log(
                action: 'subscription.payment_confirmed',
                auditable: $invoice,
                oldValues: ['status' => $invoice->getOriginal('status') ?? 'pending'],
                newValues: [
                    'status' => 'paid',
                    'type' => $invoice->type,
                    'target_plan_id' => $targetPlanId,
                    'new_ends_at' => $newEndsAt->toDateString(),
                    'tenant' => $tenant?->name,
                ],
                userId: $request->user()?->id,
            );
        });

        $tenantName = $invoice->tenant?->name ?? 'Barbershop';

        return back()->with('status', "Pembayaran tagihan '{$invoice->number}' untuk {$tenantName} berhasil DIKONFIRMASI LUNAS. Sistem barbershop telah aktif kembali.");
    }

    public function cancelInvoice(Request $request, int $id): RedirectResponse
    {
        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return back()->with('error', "Tagihan yang sudah Lunas tidak dapat dibatalkan.");
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Wajib memberikan alasan penolakan/pembatalan tagihan.',
            'cancellation_reason.min' => 'Alasan penolakan minimal 3 karakter.',
            'cancellation_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $invoice->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_by' => 'superadmin',
            'cancelled_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'subscription.invoice_rejected',
            auditable: $invoice,
            oldValues: ['status' => $invoice->getOriginal('status') ?? 'pending'],
            newValues: [
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => 'superadmin',
            ],
            userId: $request->user()?->id,
        );

        return back()->with('status', "Tagihan {$invoice->number} berhasil ditolak/dibatalkan dengan alasan: \"{$validated['cancellation_reason']}\".");
    }

    public function show(int $id): View
    {
        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->with(['tenant.owner', 'subscription.plan'])
            ->findOrFail($id);

        return view('invoices.subscription', [
            'invoice' => $invoice,
        ]);
    }
}
