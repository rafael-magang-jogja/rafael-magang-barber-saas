<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PlatformPlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()
            ->whereIn('code', ['starter', 'pro', 'business'])
            ->withCount(['subscriptions' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->orderBy('price_monthly')
            ->get();

        return view('platform.plans', [
            'plans' => $plans,
        ]);
    }

    public function edit(Plan $plan): View
    {
        $plan->loadCount(['subscriptions' => fn ($q) => $q->withoutGlobalScope('tenant')]);

        return view('platform.plan-edit', [
            'plan' => $plan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:plans,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'max_staff' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $code = !empty($validated['code']) ? Str::slug($validated['code']) : Str::slug($validated['name']);

        $plan = Plan::query()->create([
            'code' => $code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => (int) $validated['price_monthly'],
            'max_branches' => !empty($validated['max_branches']) ? (int) $validated['max_branches'] : null,
            'max_staff' => !empty($validated['max_staff']) ? (int) $validated['max_staff'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('status', "Paket langganan baru '{$plan->name}' berhasil ditambahkan ke katalog platform.");
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        if ($request->has('price_monthly')) {
            $rawPrice = preg_replace('/\D/', '', (string) $request->input('price_monthly'));
            $request->merge(['price_monthly' => $rawPrice !== '' ? (int) $rawPrice : 0]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'max_staff' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => (int) $validated['price_monthly'],
            'max_branches' => !empty($validated['max_branches']) ? (int) $validated['max_branches'] : null,
            'max_staff' => !empty($validated['max_staff']) ? (int) $validated['max_staff'] : null,
            'is_active' => (bool) ($request->has('is_active') ? $request->boolean('is_active') : true),
        ]);

        return redirect()->route('platform.plans')->with('status', "Paket langganan '{$plan->name}' berhasil diperbarui.");
    }

    public function toggleStatus(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        $statusStr = $plan->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return back()->with('status', "Paket '{$plan->name}' berhasil {$statusStr}.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Paket platform bersifat tetap dan tidak dapat dihapus, hanya dinonaktifkan
        $plan->update(['is_active' => false]);

        return back()->with('status', "Paket platform bersifat tetap dan tidak dapat dihapus, paket '{$plan->name}' telah dinonaktifkan.");
    }
}
