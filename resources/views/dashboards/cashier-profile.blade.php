@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
    $effectiveRole = $currentTenantCtx->role() ?? request()->attributes->get('current_role') ?? 'cashier';
@endphp

<x-layouts.app
    :title="'Profil Kasir — ' . ($cashierMembership?->display_name ?? $user?->name ?? 'Kasir')"
    subtitle="Informasi akun kasir, penugasan cabang, dan ringkasan performa transaksi Anda"
    :role="$effectiveRole"
>
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- 1. KARTU IDENTITAS PROFIL KASIR -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <!-- Avatar Inisial -->
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-zinc-950 grid place-items-center shrink-0 shadow-sm font-black text-xl border border-white/50">
                        {{ strtoupper(substr($cashierMembership?->display_name ?? ($user?->name ?? 'K'), 0, 1)) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-black text-lg text-zinc-950 truncate">
                                {{ $cashierMembership?->display_name ?? $user?->name }}
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 shrink-0">
                                Kasir Aktif
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 truncate mt-0.5">
                            {{ $user?->email }}
                        </p>
                        <div class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold truncate mt-1">
                            <x-ui.icon name="store" class="w-3.5 h-3.5 shrink-0 text-amber-600" />
                            <span class="truncate">{{ $tenant?->name ?? 'Barbershop' }} &bull; {{ $branch?->name ?? 'Semua Cabang' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tombol Edit Profil -->
                <div class="flex items-center gap-2 shrink-0">
                    <a
                        href="{{ route('cashier.profile.edit') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition active:scale-95 cursor-pointer shadow-xs"
                        title="Ubah Data Pribadi"
                    >
                        <x-ui.icon name="edit" class="w-3.5 h-3.5 text-amber-400" />
                        <span>Edit Profil</span>
                    </a>
                </div>
            </div>

            @if($user?->phone)
                <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-100 flex items-center justify-between text-xs">
                    <span class="text-zinc-500 flex items-center gap-2">
                        <x-ui.icon name="phone" class="w-4 h-4 text-zinc-400" />
                        <span>Nomor WhatsApp / Telepon</span>
                    </span>
                    <span class="font-bold text-zinc-800 font-mono">{{ $user->phone }}</span>
                </div>
            @endif
        </div>

        <!-- 2. KARTU STATISTIK PERFORMA KASIR -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs text-center space-y-1">
                <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Transaksi Diproses</span>
                <p class="text-2xl font-black text-zinc-950 num">{{ $ordersThisMonth }}</p>
                <div class="flex items-center justify-center gap-1 text-[11px] text-zinc-400">
                    <span>Bulan ini</span>
                </div>
            </div>

            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs text-center space-y-1">
                <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Omzet POS Kasir</span>
                <p class="text-xl font-black text-emerald-700 num truncate">
                    Rp{{ number_format($omzetThisMonth, 0, ',', '.') }}
                </p>
                <div class="flex items-center justify-center gap-1 text-[11px] text-zinc-400">
                    <span>Bulan ini</span>
                </div>
            </div>

            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs text-center space-y-1">
                <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Kehadiran Shift</span>
                <p class="text-2xl font-black text-blue-600 num">{{ $attendancesThisMonth }}</p>
                <div class="flex items-center justify-center gap-1 text-[11px] text-zinc-400">
                    <span>Hari shift bulan ini</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 3. DETAIL PENUGASAN & PENGATURAN KASIR -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-zinc-100">
                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="briefcase" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Detail Penugasan</h3>
                        <p class="text-[11px] text-zinc-400">Status kontrak dan wewenang operasional</p>
                    </div>
                </div>

                <div class="divide-y divide-zinc-100 text-xs">
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-zinc-500">Cabang Tugas</span>
                        <span class="font-bold text-zinc-900 text-right">{{ $branch?->name ?? 'Semua Cabang / Kantor Pusat' }}</span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-zinc-500">Bergabung Sejak</span>
                        <span class="font-semibold text-zinc-900">
                            {{ $cashierMembership?->joined_at ? $cashierMembership->joined_at->translatedFormat('d F Y') : '-' }}
                        </span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-zinc-500">Skema Gaji Pokok</span>
                        <span class="font-semibold text-zinc-900 text-right truncate max-w-[200px]" title="{{ $cashierMembership?->salaryScheme?->name ?? 'Gaji Pokok Bulanan' }}">
                            {{ $cashierMembership?->salaryScheme?->name ?? 'Gaji Pokok Kasir' }}
                        </span>
                    </div>
                    @if($baseSalary > 0)
                        <div class="py-2.5 flex items-center justify-between">
                            <span class="text-zinc-500">Nominal Gaji Pokok</span>
                            <span class="font-bold font-mono text-emerald-700">Rp{{ number_format($baseSalary, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-zinc-500">Wewenang Refund POS</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ ($cashierMembership?->can_refund ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600' }}">
                            {{ ($cashierMembership?->can_refund ?? false) ? 'Diizinkan' : 'Memerlukan Otorisasi Owner' }}
                        </span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-zinc-500">Akses Pendapatan</span>
                        <a href="{{ route('cashier.earnings') }}" class="font-bold text-amber-600 hover:text-amber-700 flex items-center gap-1">
                            <span>Lihat Rincian</span>
                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- 4. GANTI KATA SANDI -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-zinc-100">
                    <span class="w-8 h-8 rounded-xl bg-zinc-100 text-zinc-700 flex items-center justify-center shrink-0">
                        <x-ui.icon name="key" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Keamanan Akun</h3>
                        <p class="text-[11px] text-zinc-400">Perbarui kata sandi masuk kasir</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('cashier.profile.password') }}" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="block font-bold text-zinc-700 mb-1">
                            Kata Sandi Saat Ini <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="current_password"
                            id="current_password"
                            required
                            placeholder="Masukkan kata sandi lama Anda"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                        />
                        @error('current_password')
                            <p class="text-rose-600 text-[11px] font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block font-bold text-zinc-700 mb-1">
                            Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            minlength="6"
                            placeholder="Minimal 6 karakter"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                        />
                        @error('password')
                            <p class="text-rose-600 text-[11px] font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block font-bold text-zinc-700 mb-1">
                            Konfirmasi Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            required
                            minlength="6"
                            placeholder="Ulangi kata sandi baru"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full py-2.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-xs mt-2"
                    >
                        <x-ui.icon name="check" class="w-4 h-4 text-amber-400" />
                        <span>Simpan Kata Sandi Baru</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- 5. KARTU LOGOUT KHUSUS MODE MOBILE -->
        <div class="block md:hidden p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
            <div class="flex items-center gap-2.5 pb-2 border-b border-zinc-100">
                <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="log-out" class="w-4 h-4 text-rose-600" />
                </span>
                <div>
                    <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Keluar Akun Kasir</h3>
                    <p class="text-[11px] text-zinc-400">Pastikan seluruh transaksi kasir telah selesai</p>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" id="form-cashier-mobile-logout">
                @csrf
                <button
                    type="button"
                    onclick="document.getElementById('modal-confirm-logout-cashier').showModal()"
                    class="w-full py-3 px-4 rounded-2xl bg-rose-600 hover:bg-rose-500 active:scale-[0.99] text-white font-black text-xs flex items-center justify-center gap-2 shadow-xs transition cursor-pointer"
                >
                    <x-ui.icon name="log-out" class="w-4 h-4 text-white" />
                    <span>Keluar dari Akun Kasir</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Logout Mobile Kasir -->
    <dialog id="modal-confirm-logout-cashier" class="rounded-2xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-xs mx-auto my-auto border border-zinc-200 bg-white">
        <div class="p-5 space-y-2 text-center">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <x-ui.icon name="log-out" class="w-5 h-5 text-rose-600" />
            </div>
            <h3 class="font-black text-sm text-zinc-900">Keluar Akun Kasir?</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Apakah Anda yakin ingin keluar dari sistem kasir sekarang? Pastikan transaksi yang sedang berlangsung sudah tersimpan.
            </p>
        </div>
        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2 rounded-b-2xl">
            <button
                type="button"
                onclick="document.getElementById('modal-confirm-logout-cashier').close()"
                class="px-3.5 py-2 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-100 transition cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                onclick="document.getElementById('form-cashier-mobile-logout').submit()"
                class="px-4 py-2 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer"
            >
                Ya, Keluar
            </button>
        </div>
    </dialog>
</x-layouts.app>
