<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $resolvedRole = app(CurrentTenant::class)->role() ?? $request->attributes->get('current_role');

        // Jika route khusus customer portal (role:customer), blokir Superadmin, Owner, Admin, dan Barber
        if ($roles === ['customer']) {
            if ($user === null) {
                return $next($request);
            }

            if ($user->is_superadmin) {
                abort(403, 'Akun Superadmin tidak diizinkan membuka Portal Customer.');
            }

            if ($resolvedRole !== null) {
                if ($resolvedRole !== 'customer') {
                    abort(403, 'Akun internal (Owner/Admin/Barber) tidak dapat mengakses Portal Customer.');
                }

                return $next($request);
            }

            $isStaffOrOwner = $user->memberships()
                ->withoutGlobalScope('tenant')
                ->where('is_active', true)
                ->exists();

            if ($isStaffOrOwner) {
                abort(403, 'Akun internal (Owner/Admin/Barber) tidak dapat mengakses Portal Customer.');
            }

            return $next($request);
        }

        if ($user === null) {
            return redirect()->route('login');
        }

        if (in_array('superadmin', $roles, true) && $user->is_superadmin) {
            return $next($request);
        }

        $tenantRoles = array_values(array_diff($roles, ['superadmin', 'customer']));

        if ($tenantRoles !== []) {
            if (is_string($resolvedRole) && in_array($resolvedRole, $tenantRoles, true)) {
                $request->attributes->set('current_role', $resolvedRole);

                return $next($request);
            }

            $membership = $user->memberships()
                ->where('is_active', true)
                ->whereIn('role', $tenantRoles)
                ->first();

            if ($membership !== null) {
                $request->attributes->set('current_role', $membership->role->value);

                return $next($request);
            }
        }

        if (in_array('customer', $roles, true)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
