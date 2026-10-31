<x-layouts.barber
    :title="'Edit Profil — ' . ($barberMembership?->display_name ?? 'Barber')"
    :barber-membership="$barberMembership"
>
    <!-- Navigasi Kembali -->
    <div class="flex items-center justify-between">
        <a
            href="{{ route('barber.profile') }}"
            class="inline-flex items-center gap-2 text-xs font-bold text-zinc-600 hover:text-zinc-950 transition active:scale-95"
        >
            <x-ui.icon name="arrow-left" class="w-4 h-4 text-zinc-500" />
            <span>Kembali ke Profil</span>
        </a>
        <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Ubah Data</span>
    </div>

    <!-- Notifikasi Flash -->
    <x-ui.flash-alert />

    <!-- FORMULIR EDIT PROFIL -->
    <form method="POST" action="{{ route('barber.profile.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <!-- Kartu 1: Data Identitas Barber -->
        <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
            <div class="flex items-center gap-2 border-b border-zinc-100 pb-3">
                <span class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="edit" class="w-3.5 h-3.5" />
                </span>
                <div>
                    <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Informasi Pribadi</h3>
                    <p class="text-[11px] text-zinc-400">Data akun dan nama display Anda di antrean</p>
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
                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                />
                @error('name')
                    <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama Panggilan / Antrean TV -->
            <div class="space-y-1">
                <label for="display_name" class="block font-bold text-zinc-700 text-xs">
                    Nama Panggilan (Layar Antrean & Tiket) <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    id="display_name"
                    name="display_name"
                    value="{{ old('display_name', $barberMembership?->display_name ?? $user?->name) }}"
                    required
                    maxlength="50"
                    placeholder="Nama singkat/panggilan yang dikenal pelanggan"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                />
                <p class="text-[11px] text-zinc-400">
                    Nama ini yang akan muncul di layar TV antrean, tiket pelanggan, dan struk kasir.
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
                        class="w-full pl-8 pr-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                    />
                    <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                        <x-ui.icon name="mail" class="w-3.5 h-3.5" />
                    </div>
                </div>
                <p class="text-[11px] text-zinc-400">
                    Digunakan untuk masuk ke aplikasi dashboard barber ini.
                </p>
                @error('email')
                    <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nomor WhatsApp / Telepon -->
            <div class="space-y-1">
                <label for="phone" class="block font-bold text-zinc-700 text-xs">
                    Nomor WhatsApp / HP
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', $user?->phone) }}"
                        maxlength="20"
                        placeholder="Contoh: 081234567890"
                        class="w-full pl-8 pr-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition font-mono"
                    />
                    <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                        <x-ui.icon name="phone" class="w-3.5 h-3.5" />
                    </div>
                </div>
                @error('phone')
                    <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Keahlian / Spesialisasi Potong -->
            <div class="space-y-1">
                <label for="specialty" class="block font-bold text-zinc-700 text-xs">
                    Keahlian / Spesialisasi Potong
                </label>
                <input
                    type="text"
                    id="specialty"
                    name="specialty"
                    value="{{ old('specialty', $barberMembership?->specialty) }}"
                    maxlength="100"
                    placeholder="Contoh: Fade, Pompadour, Beard Sculpting, Perm"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500 transition"
                />
                <p class="text-[11px] text-zinc-400">
                    Spesialisasi gaya pangkas Anda yang bisa dilihat oleh tim barbershop.
                </p>
                @error('specialty')
                    <p class="text-rose-600 text-[11px] font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Kartu 2: Informasi Penugasan (Read-Only) -->
        <div class="p-4 rounded-3xl bg-zinc-50 border border-zinc-200/80 space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-zinc-200 text-zinc-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="briefcase" class="w-3 h-3" />
                </span>
                <div>
                    <h4 class="font-black text-xs text-zinc-800">Informasi Penugasan</h4>
                    <p class="text-[10px] text-zinc-500">Dikelola dan diatur oleh Pemilik (Owner)</p>
                </div>
            </div>

            <div class="divide-y divide-zinc-200/60 text-xs bg-white rounded-2xl border border-zinc-200/70 p-3 space-y-2">
                <div class="flex items-center justify-between pb-1">
                    <span class="text-zinc-500">Cabang Penempatan</span>
                    <span class="font-bold text-zinc-900">{{ $branch?->name ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-zinc-500">Skema Gaji & Komisi</span>
                    <span class="font-semibold text-zinc-900 truncate max-w-[200px]" title="{{ $barberMembership?->salaryScheme?->name ?? 'Gaji Pokok & Komisi' }}">
                        {{ $barberMembership?->salaryScheme?->name ?? 'Gaji Pokok & Komisi' }}
                    </span>
                </div>
                <div class="flex items-center justify-between pt-2">
                    <span class="text-zinc-500">Barbershop (Tenant)</span>
                    <span class="font-semibold text-zinc-900">{{ $tenant?->name ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Simpan & Batal -->
        <div class="pt-2 space-y-2.5">
            <button
                type="submit"
                class="w-full py-3 px-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition cursor-pointer"
            >
                <x-ui.icon name="check" class="w-4 h-4 text-amber-400" />
                <span>Simpan Perubahan</span>
            </button>

            <a
                href="{{ route('barber.profile') }}"
                class="w-full py-3 px-4 rounded-2xl bg-white hover:bg-zinc-50 border border-zinc-300 active:scale-[0.99] text-zinc-700 font-bold text-xs flex items-center justify-center gap-2 shadow-2xs transition"
            >
                <x-ui.icon name="x" class="w-4 h-4 text-zinc-400" />
                <span>Batal &amp; Kembali ke Profil</span>
            </a>
        </div>
    </form>
</x-layouts.barber>
