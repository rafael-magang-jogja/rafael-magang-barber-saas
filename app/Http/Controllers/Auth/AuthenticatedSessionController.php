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
    public function create(): View
    {
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

        return redirect()->route('login');
    }

    public function redirectAuthenticated(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return $this->redirectByRole($request, $user);
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
