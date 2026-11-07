<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Superadmin selalu memiliki akses penuh tanpa pembatasan langganan
        if ($user !== null && $user->is_superadmin) {
            return $next($request);
        }

        /** @var CurrentTenant $currentTenant */
        $currentTenant = app(CurrentTenant::class);
        $tenant = $currentTenant->get();

        // Jika tidak berada dalam konteks tenant, lanjutkan
        if ($tenant === null) {
            return $next($request);
        }

        // 2. Cek apakah masa langganan tenant telah habis atau disuspend
        if ($currentTenant->isSubscriptionLocked()) {
            $role = $currentTenant->role() ?? $request->attributes->get('current_role');

            // Jika Owner: arahkan ke halaman perpanjangan langganan
            if ($role === 'owner') {
                // Rute yang diizinkan untuk owner saat terkunci:
                if ($request->routeIs('owner.subscription*', 'logout')) {
                    return $next($request);
                }

                return redirect()->route('owner.subscription')
                    ->with('subscription_locked', true)
                    ->with('warning', 'Masa aktif langganan barbershop Anda telah berakhir. Seluruh fitur operasional dikunci sementara hingga pembayaran perpanjangan paket diselesaikan.');
            }

            // Jika Staf (Admin/Kasir/Barber): tampilkan halaman informasi sistem terkunci
            if (in_array($role, ['admin', 'cashier', 'barber'], true)) {
                if ($request->routeIs('logout')) {
                    return $next($request);
                }

                return response()->view('errors.subscription-expired', [
                    'tenant' => $tenant,
                ], 403);
            }
        }

        return $next($request);
    }
}
