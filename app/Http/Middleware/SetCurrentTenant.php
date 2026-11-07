<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\QueueTicket;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 0. Superadmin Platform Area: route platform.* atau path platform/*
        // Superadmin adalah operator global SaaS platform dan sama sekali TIDAK BOLEH terikat ke tenant manapun.
        if ($request->routeIs('platform.*') || $request->is('platform*')) {
            $currentTenant = app(CurrentTenant::class);
            $currentTenant->set(null);
            $currentTenant->setRole('superadmin');
            $request->attributes->set('current_role', 'superadmin');

            return $next($request);
        }

        $tenant = null;
        $resolvedRole = null;
        $tenantFromPortal = false;

        // 0a. Jika request datang dari subdomain atau host asing (misal *.localhost atau custom host),
        // otomatis alihkan ke domain root platform yang normal (/b/{slug} jika cocok dengan barbershop, atau path normal).
        if ($this->isSubdomainOrForeignHost($request)) {
            $matchedTenant = $this->resolveTenantFromSubdomainHost($request);
            if ($matchedTenant !== null) {
                return redirect()->to($this->resolveFallbackPortalUrl($request, $matchedTenant));
            }

            return redirect()->to(\App\Http\Controllers\Auth\AuthenticatedSessionController::resolveRootUrl($request, $request->getRequestUri()));
        }

        // 1. Jika route memiliki parameter {tenant} (slug atau model Tenant, misal /b/{tenant})
        if ($tenant === null) {
            $routeTenant = $request->route('tenant');
            if ($routeTenant instanceof Tenant) {
                $tenant = $routeTenant;
            } elseif (is_string($routeTenant) && $routeTenant !== '') {
                $tenant = Tenant::query()->where('slug', $routeTenant)->first();
            }

            $tenantFromPortal = $tenant !== null;
        }

        // 1a. Resolusi dari parameter route {ticket} atau {booking} HANYA untuk tamu (guest) yang belum login,
        // dan TIDAK disimpan ke session agar customer yang belum scan QR tetap berada di mode platform default.
        if ($tenant === null && $user === null) {
            $routeTicket = $request->route('ticket');
            if ($routeTicket instanceof QueueTicket && $routeTicket->tenant !== null) {
                $tenant = $routeTicket->tenant;
            } elseif (is_numeric($routeTicket) || (is_string($routeTicket) && ctype_digit($routeTicket))) {
                $foundTicket = QueueTicket::query()->withoutGlobalScope('tenant')->with('tenant')->find((int) $routeTicket);
                if ($foundTicket?->tenant !== null) {
                    $tenant = $foundTicket->tenant;
                }
            }

            if ($tenant === null) {
                $routeBooking = $request->route('booking');
                if ($routeBooking instanceof Booking && $routeBooking->tenant !== null) {
                    $tenant = $routeBooking->tenant;
                } elseif (is_numeric($routeBooking) || (is_string($routeBooking) && ctype_digit($routeBooking))) {
                    $foundBooking = Booking::query()->withoutGlobalScope('tenant')->with('tenant')->find((int) $routeBooking);
                    if ($foundBooking?->tenant !== null) {
                        $tenant = $foundBooking->tenant;
                    }
                }
            }
        }

        // Simpan tenant ke session customer HANYA jika customer benar-benar masuk melalui scan QR / portal tenant (/b/{tenant} atau subdomain)
        if ($tenantFromPortal && $tenant !== null && $request->hasSession() && ! ($user?->is_superadmin)) {
            $request->session()->put('scanned_customer_tenant_id', $tenant->id);
            $request->session()->put('scanned_customer_tenant_slug', $tenant->slug);
        }

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
                } elseif ($user === null) {
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

        // 4. Untuk customer: hanya gunakan tenant jika sudah melakukan scan QR (scanned_customer_tenant_id di session)
        if ($tenant === null && $membership === null && $request->hasSession() && ! ($user?->is_superadmin)) {
            $customerTenantId = $request->session()->get('scanned_customer_tenant_id');
            if ($customerTenantId !== null) {
                $tenant = Tenant::query()->find($customerTenantId);
            }
        }

        $currentTenant = app(CurrentTenant::class);
        $currentTenant->set($tenant);
        $currentTenant->setRole($resolvedRole);
        $request->attributes->set('current_role', $resolvedRole);

        return $next($request);
    }

    private function isSubdomainOrForeignHost(Request $request): bool
    {
        $host = $request->getHost();
        if (empty($host) || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $appUrl = (string) (config('app.url') ?? 'http://localhost');
        $appHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');

        return ! in_array(strtolower($host), [strtolower($appHost), 'localhost', '127.0.0.1'], true);
    }

    private function resolveTenantFromSubdomainHost(Request $request): ?Tenant
    {
        $host = $request->getHost();
        if (empty($host) || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $appUrl = (string) (config('app.url') ?? 'http://localhost');
        $appHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');

        if (! \Illuminate\Support\Facades\Schema::hasTable('tenants')) {
            return null;
        }

        $subdomain = null;

        if ($appHost !== '' && str_ends_with($host, '.' . $appHost)) {
            $subdomain = substr($host, 0, -strlen('.' . $appHost));
        } elseif (str_ends_with($host, '.localhost')) {
            $subdomain = substr($host, 0, -strlen('.localhost'));
        } elseif (str_ends_with($host, '.test')) {
            $subdomain = substr($host, 0, -strlen('.test'));
        } else {
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $subdomain = $parts[0];
            }
        }

        if (empty($subdomain) || in_array(strtolower($subdomain), ['www', 'app', 'admin', 'api', 'mail', 'localhost', 'platform', 'root', 'superadmin', 'login', 'register', 'dashboard', 'public'], true)) {
            return Tenant::query()
                ->where('settings->custom_domain', $host)
                ->first();
        }

        $subdomain = strtolower($subdomain);

        return Tenant::query()
            ->where(function ($query) use ($subdomain): void {
                $query->where('settings->custom_subdomain', $subdomain)
                    ->orWhere('slug', $subdomain);
            })
            ->first();
    }

    /**
     * Bentuk URL tujuan di domain root platform normal (/b/{slug}).
     */
    private function resolveFallbackPortalUrl(Request $request, Tenant $tenant): string
    {
        $path = trim($request->path(), '/');

        if ($path === '' || $path === '/') {
            $relativePath = "b/{$tenant->slug}";
        } elseif (str_starts_with($path, "b/{$tenant->slug}")) {
            $relativePath = $path;
        } elseif (str_starts_with($path, 'tv/')) {
            $relativePath = "b/{$tenant->slug}/{$path}";
        } elseif ($path === 'customer/booking' || $path === 'booking') {
            $relativePath = "b/{$tenant->slug}?tab=booking";
        } elseif (in_array($path, ['login', 'register', 'available-slots', 'queue'], true)) {
            $relativePath = "b/{$tenant->slug}/{$path}";
        } else {
            $relativePath = "b/{$tenant->slug}";
        }

        $queryString = $request->getQueryString();
        $targetPath = ! empty($queryString) ? "{$relativePath}?{$queryString}" : $relativePath;

        return \App\Http\Controllers\Auth\AuthenticatedSessionController::resolveRootUrl($request, $targetPath);
    }
}
