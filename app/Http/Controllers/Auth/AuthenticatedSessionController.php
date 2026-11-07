<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (self::isSubdomainRequest($request)) {
            return redirect()->to(self::officialLoginUrl($request));
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$loginField => $credentials['login'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors([
                'login' => 'Email/nomor HP atau kata sandi tidak sesuai.',
            ])->onlyInput('login');
        }

        $request->session()->regenerate();
        $request->session()->forget([
            'scanned_customer_tenant_id',
            'scanned_customer_tenant_slug',
            'active_customer_tenant_id',
            'active_customer_tenant_slug',
        ]);

        /** @var User $user */
        $user = $request->user();

        // Pengecekan Mode Maintenance Platform: Hanya izinkan Superadmin saat pemeliharaan aktif
        if (\App\Support\Platform\PlatformConfig::isMaintenance() && ! $user->is_superadmin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'login' => 'Platform sedang dalam mode pemeliharaan berkala. Akses masuk akun sementara dibatasi untuk peningkatan sistem.',
            ])->onlyInput('login');
        }

        $user->update(['last_login_at' => now()]);

        return $this->redirectByRole($request, $user);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(self::officialLoginUrl($request));
    }

    public function redirectAuthenticated(Request $request): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->to(self::officialLoginUrl($request));
        }

        return $this->redirectByRole($request, $user);
    }

    /**
     * Dapatkan URL di root domain platform normal (menghilangkan subdomain tenant jika ada).
     */
    public static function resolveRootUrl(Request $request, string $path = '/'): string
    {
        $appUrl = (string) (config('app.url') ?? 'http://localhost');
        $defaultHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        // Cari tahu apakah request saat ini memakai port non-standar (misal :8000)
        $portSuffix = '';
        if ($port && ! in_array((int) $port, [80, 443], true)) {
            $portSuffix = ":{$port}";
        }

        $rootHost = $host;

        // Jika host menggunakan .localhost (misal barber-kings-royal.localhost)
        if (str_ends_with(strtolower($host), '.localhost')) {
            $rootHost = 'localhost';
        } elseif (str_ends_with(strtolower($host), '.test')) {
            $rootHost = 'test';
        } elseif ($defaultHost !== '' && str_ends_with(strtolower($host), '.' . strtolower($defaultHost))) {
            $rootHost = $defaultHost;
        } else {
            // Jika host memiliki 3 bagian atau lebih (misal subdomain.domain.com) dan bukan IP
            if (! filter_var($host, FILTER_VALIDATE_IP)) {
                $parts = explode('.', $host);
                if (count($parts) >= 3) {
                    array_shift($parts);
                    $rootHost = implode('.', $parts);
                }
            }
        }

        $cleanPath = '/' . ltrim($path, '/');

        return "{$scheme}://{$rootHost}{$portSuffix}{$cleanPath}";
    }

    /**
     * Dapatkan URL login resmi platform normal.
     */
    public static function officialLoginUrl(Request $request): string
    {
        return self::resolveRootUrl($request, '/login');
    }

    /**
     * Cek apakah request saat ini menggunakan subdomain atau bukan root host platform.
     */
    public static function isSubdomainRequest(Request $request): bool
    {
        $currentHost = strtolower($request->getHost());
        $rootUrl = self::resolveRootUrl($request, '/');
        $rootHost = strtolower((string) parse_url($rootUrl, PHP_URL_HOST));

        return $currentHost !== $rootHost;
    }

    private function redirectByRole(Request $request, User $user): RedirectResponse
    {
        if ($user->is_superadmin) {
            return redirect()->route('platform.dashboard');
        }

        $membership = $user->memberships()
            ->withoutGlobalScope('tenant')
            ->where('is_active', true)
            ->first();

        if ($membership !== null) {
            $request->session()->put('active_tenant_id', $membership->tenant_id);

            return match ($membership->role) {
                Role::Owner => redirect()->route('owner.dashboard'),
                Role::Admin => redirect()->route('cashier.dashboard'),
                Role::Barber => redirect()->route('barber.dashboard'),
            };
        }

        return redirect()->intended(route('customer.dashboard'));
    }
}
