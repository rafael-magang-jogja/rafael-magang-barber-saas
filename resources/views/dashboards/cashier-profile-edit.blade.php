@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
    $effectiveRole = $currentTenantCtx->role() ?? request()->attributes->get('current_role') ?? 'cashier';
@endphp

<x-layouts.app
    :title="'Edit Profil — ' . ($cashierMembership?->display_name ?? $user?->name ?? 'Kasir')"
    subtitle="Perbarui data kontak dan nama tampilan kasir di sistem"
    :role="$effectiveRole"
>
    <div class="max-w-2xl mx-auto space-y-6">
        <!-- Navigasi Kembali -->
        <div class="flex items-center justify-between">
            <a
                href="{{ route('cashier.profile') }}"
                class="inline-flex items-center gap-2 text-xs font-bold text-zinc-600 hover:text-zinc-950 transition active:scale-95"
            >
                <x-ui.icon name="arrow-left" class="w-4 h-4 text-zinc-500" />
                <span>Kembali ke Profil</span>
            </a>
            <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Ubah Data Kasir</span>
        </div>

        <!-- FORMULIR EDIT PROFIL KASIR -->
        <form method="POST" action="{{ route('cashier.profile.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Kartu 1: Data Identitas Kasir -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 border-b border-zinc-100 pb-3">
                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="edit" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Informasi Pribadi &amp; Akun</h3>
                        <p class="text-[11px] text-zinc-400">Data akun dan nama display Anda di sistem kasir POS</p>
                    </div>
                </div>

                <!-- Nama Lengkap Akun -->
                <div class="space-y-1">
                    <label for="name" class="block font-bold text-zinc-700 text-xs">
                        Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user?->name) }}"
                        required
                        maxlength="100"
                        placeholder="Nama lengkap sesuai KTP"
                        class="w-full px-3 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                    />
                    @error('name')
                        <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Nama Display Kasir -->
                <div class="space-y-1">
                    <label for="display_name" class="block font-bold text-zinc-700 text-xs">
                        Nama Tampilan Kasir <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="display_name"
                        name="display_name"
                        value="{{ old('display_name', $cashierMembership?->display_name ?? $user?->name) }}"
                        required
                        maxlength="50"
                        placeholder="Nama display kasir (cth: Kasir Dian)"
                        class="w-full px-3 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                    />
                    <p class="text-[11px] text-zinc-400">
                        Nama ini akan tercatat di log transaksi dan struk cetak pembayaran kasir.
                    </p>
                    @error('display_name')
                        <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email Login -->
                <div class="space-y-1">
                    <label for="email" class="block font-bold text-zinc-700 text-xs">
                        Email Login <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $user?->email) }}"
                            required
                            maxlength="150"
                            placeholder="nama@email.com"
                            class="w-full pl-9 pr-3 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                            <x-ui.icon name="mail" class="w-3.5 h-3.5" />
                        </div>
                    </div>
                    <p class="text-[11px] text-zinc-400">
                        Digunakan untuk masuk ke sistem dashboard kasir.
                    </p>
                    @error('email')
                        <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Telepon / WhatsApp -->
                <div class="space-y-1">
                    <label for="phone" class="block font-bold text-zinc-700 text-xs">
                        Nomor WhatsApp / HP
                    </label>
                    <div class="relative">
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $user?->phone) }}"
                            maxlength="20"
                            placeholder="081234567890"
                            class="w-full pl-9 pr-3 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition font-mono"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                            <x-ui.icon name="phone" class="w-3.5 h-3.5" />
                        </div>
                    </div>
                    <p class="text-[11px] text-zinc-400">
                        Kontak aktif untuk koordinasi jadwal dan pergantian shift.
                    </p>
                    @error('phone')
                        <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Tombol Aksi Simpan -->
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('cashier.profile') }}"
                    class="flex-1 py-2.5 px-4 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-xs text-center transition cursor-pointer"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="flex-2 py-2.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-xs"
                >
                    <x-ui.icon name="check" class="w-4 h-4 text-amber-400" />
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
