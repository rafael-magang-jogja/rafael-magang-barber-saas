<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;
        $resolvedRole = null;

        // 1. Jika route memiliki parameter {tenant} (slug atau model Tenant)
        $routeTenant = $request->route('tenant');
        if ($routeTenant instanceof Tenant) {
            $tenant = $routeTenant;
        } elseif (is_string($routeTenant) && $routeTenant !== '') {
            $tenant = Tenant::query()->where('slug', $routeTenant)->first();
        }

        $user = $request->user();

        if ($user !== null && $user->is_superadmin) {
            $resolvedRole = 'superadmin';
        }

        $membership = null;

        // 2. Dari session active_tenant_id (diverifikasi terhadap membership user jika login)
        if ($tenant === null && $request->hasSession()) {
            $sessionTenantId = $request->session()->get('active_tenant_id');
            if ($sessionTenantId !== null) {
                if ($user !== null && ! $user->is_superadmin) {
                    $membership = $user->memberships()
                        ->withoutGlobalScope('tenant')
                        ->with('tenant')
                        ->where('tenant_id', $sessionTenantId)
                        ->where('is_active', true)
                        ->first();

                    if ($membership !== null) {
                        $tenant = $membership->tenant;
                        $resolvedRole = $membership->role->value;
                    }
                } else {
                    $tenant = Tenant::query()->find($sessionTenantId);
                }
            }
        }

        // 3. Dari membership pertama user yang sedang login (hanya jika belum ter-resolve di langkah 2)
        if ($user !== null && ! $user->is_superadmin && $membership === null) {
            $membership = $user->memberships()
                ->withoutGlobalScope('tenant')
                ->with('tenant')
                ->when($tenant !== null, fn ($q) => $q->where('tenant_id', $tenant->id))
                ->where('is_active', true)
                ->first();

            if ($membership === null && $tenant !== null) {
                $membership = $user->memberships()
                    ->withoutGlobalScope('tenant')
                    ->with('tenant')
                    ->where('is_active', true)
                    ->first();
            }

            if ($membership !== null) {
                $resolvedRole = $membership->role->value;

                if ($tenant === null) {
                    $tenant = $membership->tenant;
                    if ($tenant !== null && $request->hasSession()) {
                        $request->session()->put('active_tenant_id', $tenant->id);
                    }
                }
            } else {
                $resolvedRole = 'customer';
            }
        }

        $currentTenant = app(CurrentTenant::class);
        $currentTenant->set($tenant);
        $currentTenant->setRole($resolvedRole);
        $request->attributes->set('current_role', $resolvedRole);

        return $next($request);
    }
}
