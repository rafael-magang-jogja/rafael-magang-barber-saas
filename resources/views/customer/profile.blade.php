<x-layouts.customer title="Profil Saya" :backUrl="route('customer.dashboard')">
    <div class="space-y-4">
        <!-- Kartu Ringkasan Akun Mobile -->
        <div class="p-5 rounded-2xl bg-zinc-950 text-white shadow-xl flex items-center justify-between gap-3 border border-zinc-800">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center font-extrabold text-xl shadow-md shadow-amber-500/25 shrink-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-base font-extrabold tracking-tight truncate">{{ $user->name }}</h1>
                    <p class="text-xs text-zinc-400 truncate">{{ $user->phone ?? 'Belum ada nomor HP' }}</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-extrabold mt-1">
                        <x-ui.icon name="users" class="w-3 h-3" />
                        <span>Pelanggan BarberOS</span>
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-zinc-800 hover:bg-rose-600 text-zinc-300 hover:text-white font-bold text-xs transition cursor-pointer"
                    title="Keluar Akun"
                >
                    <x-ui.icon name="log-out" class="w-3.5 h-3.5" />
                    <span>Keluar</span>
                </button>
            </form>
        </div>

        <!-- Form Edit Profil Mobile -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-800 flex items-center gap-1.5">
                    <x-ui.icon name="edit" class="w-3.5 h-3.5 text-amber-600" />
                    <span>Ubah Informasi Akun</span>
                </h2>
            </x-slot:header>

            <form method="POST" action="{{ route('customer.profile.update') }}" class="space-y-3.5">
                @csrf
                @method('PUT')

                <x-ui.input
                    label="Nama Lengkap *"
                    name="name"
                    :value="old('name', $user->name)"
                    required
                />

                <x-ui.input
                    label="Nomor WhatsApp / HP *"
                    name="phone"
                    :value="old('phone', $user->phone)"
                    placeholder="0812..."
                    required
                />

                <x-ui.input
                    label="Email (Opsional)"
                    name="email"
                    type="email"
                    :value="old('email', $user->email)"
                    placeholder="nama@domain.com"
                />

                <div class="pt-3 border-t border-zinc-100 space-y-3">
                    <div>
                        <h3 class="font-extrabold text-xs text-zinc-800 uppercase tracking-wider">Ubah Kata Sandi (Opsional)</h3>
                        <p class="text-[11px] text-zinc-400">Kosongkan bila tidak ingin mengubah kata sandi.</p>
                    </div>

                    <x-ui.input
                        label="Kata Sandi Saat Ini"
                        name="current_password"
                        type="password"
                        placeholder="••••••••"
                    />

                    <x-ui.input
                        label="Kata Sandi Baru"
                        name="password"
                        type="password"
                        placeholder="Minimal 8 karakter"
                    />

                    <x-ui.input
                        label="Konfirmasi Kata Sandi Baru"
                        name="password_confirmation"
                        type="password"
                        placeholder="Ulangi kata sandi baru"
                    />
                </div>

                <div class="pt-2">
                    <x-ui.button type="submit" size="lg" class="w-full">
                        <span>Simpan Perubahan</span>
                        <x-ui.icon name="check" class="w-4 h-4" />
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.customer>
