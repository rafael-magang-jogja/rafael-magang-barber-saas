<x-layouts.barber
    :title="'Profil Staff — ' . ($barberMembership?->display_name ?? 'Barber')"
    :barber-membership="$barberMembership"
>
    <!-- Notifikasi Flash -->
    <x-ui.flash-alert />

    <!-- 1. KARTU IDENTITAS PROFIL STAFF -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3.5">
        <div class="flex items-center gap-3">
            <!-- Avatar Inisial -->
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-zinc-950 grid place-items-center shrink-0 shadow-sm font-black text-lg border border-white/50">
                {{ strtoupper(substr($barberMembership?->display_name ?? ($user?->name ?? 'B'), 0, 1)) }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <h2 class="font-black text-sm text-zinc-950 truncate">
                        {{ $barberMembership?->display_name ?? $user?->name }}
                    </h2>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 shrink-0">
                        Aktif
                    </span>
                </div>
                <p class="text-[11px] text-zinc-500 truncate">
                    {{ $user?->email }}
                </p>
                <div class="flex items-center gap-1 text-[11px] text-amber-600 font-semibold truncate mt-0.5">
                    <x-ui.icon name="store" class="w-3 h-3 shrink-0 text-amber-600" />
                    <span class="truncate">{{ $tenant?->name ?? 'Barbershop' }} &bull; {{ $branch?->name ?? 'Cabang' }}</span>
                </div>
            </div>

            <!-- Tombol Edit Profil -->
            <a
                href="{{ route('barber.profile.edit') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition active:scale-95 cursor-pointer shrink-0"
                title="Ubah Info Pribadi"
            >
                <x-ui.icon name="edit" class="w-3.5 h-3.5 text-zinc-600" />
                <span>Edit</span>
            </a>
        </div>

        @if($user?->phone)
            <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 flex items-center justify-between text-xs">
                <span class="text-zinc-500 flex items-center gap-1.5">
                    <x-ui.icon name="phone" class="w-3.5 h-3.5 text-zinc-400" />
                    <span>WhatsApp / Telepon</span>
                </span>
                <span class="font-bold text-zinc-800 font-mono">{{ $user->phone }}</span>
            </div>
        @endif
    </div>

    <!-- 2. KARTU STATISTIK BULAN INI -->
    <div class="grid grid-cols-3 gap-2">
        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Pangkas</span>
            <p class="text-lg font-black text-zinc-950 num mt-0.5">{{ $completedThisMonth }}</p>
            <span class="text-[10px] text-zinc-400">bulan ini</span>
        </div>

        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Komisi</span>
            <p class="text-xs font-black text-emerald-700 num mt-1 truncate">
                Rp{{ number_format($commissionThisMonth, 0, ',', '.') }}
            </p>
            <span class="text-[10px] text-zinc-400">bulan ini</span>
        </div>

        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Hadir</span>
            <p class="text-lg font-black text-blue-600 num mt-0.5">{{ $attendancesThisMonth }}</p>
            <span class="text-[10px] text-zinc-400">hari shift</span>
        </div>
    </div>

    <!-- 3. DETAIL PENUGASAN -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                <x-ui.icon name="briefcase" class="w-3.5 h-3.5" />
            </span>
            <h3 class="font-black text-xs uppercase tracking-wider text-zinc-800">Detail Penugasan</h3>
        </div>

        <div class="divide-y divide-zinc-100 text-xs">
            <div class="py-2 flex items-center justify-between">
                <span class="text-zinc-500">Cabang</span>
                <span class="font-bold text-zinc-900 text-right">{{ $branch?->name ?? '-' }}</span>
            </div>
            <div class="py-2 flex items-center justify-between">
                <span class="text-zinc-500">Bergabung</span>
                <span class="font-semibold text-zinc-900">
                    {{ $barberMembership?->joined_at ? $barberMembership->joined_at->translatedFormat('d M Y') : '-' }}
                </span>
            </div>
            <div class="py-2 flex items-center justify-between">
                <span class="text-zinc-500">Skema Gaji</span>
                <span class="font-semibold text-zinc-900 text-right truncate max-w-[200px]" title="{{ $barberMembership?->salaryScheme?->name ?? 'Gaji Pokok / Komisi' }}">
                    {{ $barberMembership?->salaryScheme?->name ?? 'Gaji Pokok & Komisi' }}
                </span>
            </div>
            <div class="py-2 flex items-center justify-between">
                <span class="text-zinc-500">Spesialisasi</span>
                <span class="font-semibold text-zinc-900 text-right truncate max-w-[200px]" title="{{ $barberMembership?->specialty ?? 'Styling & Potong' }}">
                    {{ $barberMembership?->specialty ?? 'Styling & Potong Rambut' }}
                </span>
            </div>
            <div class="py-2 flex items-center justify-between">
                <span class="text-zinc-500">Total Selesai</span>
                <span class="font-mono font-bold text-zinc-900">{{ $totalCompleted }} Pelanggan</span>
            </div>
        </div>
    </div>

    <!-- 4. GANTI PASSWORD -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-xl bg-zinc-100 text-zinc-700 flex items-center justify-center shrink-0">
                <x-ui.icon name="key" class="w-3.5 h-3.5" />
            </span>
            <h3 class="font-black text-xs uppercase tracking-wider text-zinc-800">Ganti Kata Sandi</h3>
        </div>

        <form method="POST" action="{{ route('barber.profile.password') }}" class="space-y-2.5 text-xs">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block font-bold text-zinc-700 mb-1">
                    Password Lama <span class="text-rose-500">*</span>
                </label>
                <input
                    type="password"
                    name="current_password"
                    id="current_password"
                    required
                    placeholder="Masukkan password saat ini"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                />
                @error('current_password')
                    <p class="text-rose-600 text-[11px] font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block font-bold text-zinc-700 mb-1">
                    Password Baru <span class="text-rose-500">*</span>
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
                    Konfirmasi Password Baru <span class="text-rose-500">*</span>
                </label>
                <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    required
                    minlength="6"
                    placeholder="Ulangi password baru"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                />
            </div>

            <button
                type="submit"
                class="w-full py-2.5 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer"
            >
                <x-ui.icon name="check" class="w-3.5 h-3.5 text-amber-400" />
                <span>Simpan Password</span>
            </button>
        </form>
    </div>

    <!-- 5. KARTU LOGOUT (RINGKAS & BERSIH) -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <x-ui.icon name="log-out" class="w-3.5 h-3.5" />
            </span>
            <div>
                <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Keluar Akun</h3>
                <p class="text-[11px] text-zinc-400">Pastikan antrean selesai sebelum keluar</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" id="form-barber-logout">
            @csrf
            <button
                type="button"
                onclick="document.getElementById('modal-confirm-logout').showModal()"
                class="w-full py-3 px-4 rounded-2xl bg-rose-600 hover:bg-rose-500 active:scale-[0.99] text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition cursor-pointer"
            >
                <x-ui.icon name="log-out" class="w-3.5 h-3.5 text-white" />
                <span>Keluar (Logout)</span>
            </button>
        </form>
    </div>

    <!-- Modal Konfirmasi Logout (Ringkas & Sederhana) -->
    <dialog id="modal-confirm-logout" class="rounded-2xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-xs mx-auto my-auto border border-zinc-200 bg-white">
        <div class="p-5 space-y-2 text-center">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <x-ui.icon name="log-out" class="w-5 h-5 text-rose-600" />
            </div>

            <h3 class="font-black text-sm text-zinc-900">Keluar Akun?</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Pastikan seluruh antrean telah selesai sebelum keluar.
            </p>
        </div>

        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2 rounded-b-2xl">
            <button
                type="button"
                onclick="document.getElementById('modal-confirm-logout').close()"
                class="px-3.5 py-2 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-100 transition cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                onclick="document.getElementById('form-barber-logout').submit()"
                class="px-4 py-2 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer"
            >
                Keluar
            </button>
        </div>
    </dialog>
</x-layouts.barber>
