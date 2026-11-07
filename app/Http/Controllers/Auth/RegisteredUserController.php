<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (AuthenticatedSessionController::isSubdomainRequest($request)) {
            return redirect()->to(AuthenticatedSessionController::resolveRootUrl($request, '/register'));
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:25', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'phone.unique' => 'Nomor WhatsApp / HP ini sudah terdaftar.',
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
        $request->session()->forget([
            'scanned_customer_tenant_id',
            'scanned_customer_tenant_slug',
            'active_customer_tenant_id',
            'active_customer_tenant_slug',
        ]);

        return redirect()->intended(route('customer.dashboard'))->with('status', 'Selamat datang di BarberOS! Akun Anda berhasil didaftarkan.');
    }
}
