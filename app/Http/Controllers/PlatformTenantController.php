<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PlatformTenantService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class PlatformTenantController extends Controller
{
    public function __construct(
        private readonly PlatformTenantService $platformTenantService,
    ) {}

    public function create(): View
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('price_monthly')->get();

        return view('platform.tenant-create', [
            'plans' => $plans,
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->platformTenantService->createTenantWithOwnerAndBranch(
            validated: $request->validated(),
            actorUserId: $request->user()?->id,
        );

        return redirect()->route('platform.dashboard')->with('status', "Barbershop '{$tenant->name}' dan akun Owner berhasil didaftarkan. Owner dapat menambahkan cabang operasional secara mandiri.");
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load([
            'owner',
            'branches' => fn ($q) => $q->withoutGlobalScope('tenant'),
            'memberships' => fn ($q) => $q->withoutGlobalScope('tenant')->with(['user', 'branch']),
            'subscriptions' => fn ($q) => $q->withoutGlobalScope('tenant')->with('plan')->latest('id'),
            'services' => fn ($q) => $q->withoutGlobalScope('tenant')->with('category'),
        ]);

        $ordersCount = Order::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $totalGmv = Order::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        $activeSubscription = $tenant->subscriptions->first();
        $plans = Plan::query()->where('is_active', true)->orderBy('price_monthly')->get();
        $subscriptionInvoices = \App\Models\SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->with('subscription.plan')
            ->latest('id')
            ->get();

        return view('platform.tenant-detail', [
            'tenant' => $tenant,
            'ordersCount' => $ordersCount,
            'totalGmv' => (int) $totalGmv,
            'activeSubscription' => $activeSubscription,
            'plans' => $plans,
            'subscriptionInvoices' => $subscriptionInvoices,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:trial,active,suspended,cancelled'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'brand_color' => !empty($validated['brand_color']) ? $validated['brand_color'] : ($tenant->brand_color ?: '#D97706'),
            'status' => TenantStatus::from($validated['status']),
        ]);

        if ($tenant->owner && (!empty($validated['owner_name']) || !empty($validated['owner_phone']))) {
            $tenant->owner->update([
                'name' => !empty($validated['owner_name']) ? $validated['owner_name'] : $tenant->owner->name,
                'phone' => !empty($validated['owner_phone']) ? $validated['owner_phone'] : $tenant->owner->phone,
            ]);
        }

        return back()->with('status', "Profil dan konfigurasi barbershop '{$tenant->name}' berhasil diperbarui.");
    }

    public function toggleStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        $updated = $this->platformTenantService->toggleTenantStatus($tenant, $request->user()?->id);

        return back()->with('status', "Status tenant {$updated->name} diubah menjadi {$updated->status->value}.");
    }

    public function updateSubscription(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'extension_period' => ['required', 'in:30,90,180,365,custom'],
            'custom_ends_at' => ['nullable', 'date', 'required_if:extension_period,custom'],
            'status' => ['nullable', 'in:active,trialing,past_due,cancelled,expired'],
        ]);

        $plan = Plan::query()->findOrFail((int) $validated['plan_id']);
        $activeSub = $tenant->subscriptions()->withoutGlobalScope('tenant')->latest('id')->first();

        $baseDate = ($activeSub && $activeSub->ends_at && $activeSub->ends_at->isFuture())
            ? $activeSub->ends_at
            : now();

        $newEndsAt = match ((string) $validated['extension_period']) {
            '30' => $baseDate->copy()->addDays(30),
            '90' => $baseDate->copy()->addDays(90),
            '180' => $baseDate->copy()->addDays(180),
            '365' => $baseDate->copy()->addYear(),
            'custom' => Carbon::parse((string) $validated['custom_ends_at']),
            default => $baseDate->copy()->addMonth(),
        };

        $subStatus = !empty($validated['status'])
            ? SubscriptionStatus::from((string) $validated['status'])
            : SubscriptionStatus::Active;

        if ($activeSub) {
            $activeSub->update([
                'plan_id' => $plan->id,
                'status' => $subStatus,
                'ends_at' => $newEndsAt,
                'price' => $plan->price_monthly,
            ]);
        } else {
            Subscription::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $subStatus,
                'starts_at' => now(),
                'ends_at' => $newEndsAt,
                'price' => $plan->price_monthly,
                'auto_renew' => true,
            ]);
        }

        $tenant->update([
            'status' => TenantStatus::Active,
            'trial_ends_at' => $newEndsAt,
        ]);

        $tenant->resetPlanRestrictedFeatures($plan->code);

        return back()->with('status', "Paket langganan tenant {$tenant->name} berhasil diperbarui ke {$plan->name} (Masa aktif s/d {$newEndsAt->translatedFormat('d M Y')}).");
    }

    public function resetOwnerPassword(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'new_password' => ['nullable', 'string', 'min:6'],
        ]);

        $newPass = $validated['new_password'] ?: 'password';

        if ($tenant->owner) {
            $tenant->owner->update([
                'password' => Hash::make($newPass),
            ]);

            return back()->with('status', "Password akun owner ({$tenant->owner->email}) berhasil direset ke: '{$newPass}'");
        }

        return back()->with('error', 'Akun owner tidak ditemukan untuk tenant ini.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $name = $tenant->name;
        $tenant->delete();

        return redirect()
            ->route('platform.dashboard')
            ->with('status', "Tenant barbershop '{$name}' berhasil dinonaktifkan/diarsipkan dari platform.");
    }
}
