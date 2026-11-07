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
use App\Services\SubscriptionActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PlatformSubscriptionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SubscriptionActivationService $subscriptionActivationService,
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

        $this->subscriptionActivationService->activateFromInvoice(
            invoice: $invoice,
            payload: [
                'gateway' => $request->input('gateway', $invoice->gateway ?? $invoice->payment_method ?? 'manual_transfer'),
                'gateway_ref' => $request->input('gateway_ref', $invoice->gateway_ref ?? 'CONFIRMED-BY-SUPERADMIN'),
                'paid_at' => now(),
            ],
            actor: $request->user(),
            defaultGateway: 'manual_transfer',
            defaultGatewayRef: 'CONFIRMED-BY-SUPERADMIN'
        );

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
