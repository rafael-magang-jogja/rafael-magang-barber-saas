<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class TenantAuthController extends Controller
{
    public function showLogin(Request $request, string $tenantSlug): View|RedirectResponse
    {
        $tenant = $this->resolveActiveTenant($tenantSlug);

        if (Auth::check()) {
            return $this->redirectAuthenticatedCustomer($request, $tenant);
        }

        return view('auth.tenant-login', [
            'tenant' => $tenant,
        ]);
    }

    public function login(LoginRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->resolveActiveTenant($tenantSlug);
        $credentials = $request->validated();

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$loginField => $credentials['login'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors([
                'login' => 'Email/nomor WhatsApp atau kata sandi tidak sesuai.',
            ])->onlyInput('login');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        // Pengecekan Mode Maintenance Platform
        if (\App\Support\Platform\PlatformConfig::isMaintenance() && ! $user->is_superadmin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'login' => 'Platform sedang dalam mode pemeliharaan berkala. Akses masuk sementara dibatasi.',
            ])->onlyInput('login');
        }

        $user->update(['last_login_at' => now()]);

        // Jika user memiliki membership staff di sistem (Owner, Admin/Kasir, Barber), arahkan ke dashboard kerja
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

        // Customer: Arahkan ke intended URL atau fallback ke portal tenant ini
        return redirect()->intended(route('customer.portal', $tenant->slug))
            ->with('status', "Selamat datang kembali di {$tenant->name}!");
    }

    public function showRegister(Request $request, string $tenantSlug): View|RedirectResponse
    {
        $tenant = $this->resolveActiveTenant($tenantSlug);

        if (Auth::check()) {
            return $this->redirectAuthenticatedCustomer($request, $tenant);
        }

        return view('auth.tenant-register', [
            'tenant' => $tenant,
        ]);
    }

    public function register(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->resolveActiveTenant($tenantSlug);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:25', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'phone.unique' => 'Nomor WhatsApp / HP ini sudah terdaftar. Silakan login.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = User::query()->create([
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
            'password' => Hash::make($validated['password']),
            'is_superadmin' => false,
            'last_login_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.portal', $tenant->slug))
            ->with('status', "Selamat datang di {$tenant->name}! Akun Anda berhasil didaftarkan.");
    }

    private function resolveActiveTenant(string $slug): Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Trial])
            ->firstOrFail();
    }

    private function redirectAuthenticatedCustomer(Request $request, Tenant $tenant): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

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

        return redirect()->route('customer.portal', $tenant->slug);
    }
}
