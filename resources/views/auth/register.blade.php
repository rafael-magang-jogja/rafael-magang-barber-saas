<x-layouts.guest title="Daftar Akun">
    <!-- Header Form: Icon & Title -->
    <div class="text-center mb-5">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 shadow-md shadow-amber-500/20 mb-3.5">
            <x-ui.icon name="user" class="w-5 h-5" />
        </div>
        <h1 class="text-2xl font-black text-zinc-900 tracking-tight leading-tight">Daftar Akun</h1>
        <p class="text-xs text-zinc-500 mt-1 max-w-xs mx-auto">
            Buat akun untuk ambil antrean, reservasi, dan riwayat pangkas rambut.
        </p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-3.5">
        @csrf

        <x-ui.input
            label="Nama Lengkap *"
            name="name"
            :value="old('name')"
            placeholder="Contoh: Budi Santoso"
            required
            autofocus
        />

        <x-ui.input
            label="Nomor WhatsApp / HP *"
            name="phone"
            :value="old('phone')"
            placeholder="081234567890"
            required
        />

        <x-ui.input
            label="Email (Opsional)"
            name="email"
            type="email"
            :value="old('email')"
            placeholder="budi@example.com"
        />

        <x-ui.input
            label="Kata Sandi *"
            name="password"
            type="password"
            placeholder="Minimal 8 karakter"
            required
        />

        <x-ui.input
            label="Konfirmasi Kata Sandi *"
            name="password_confirmation"
            type="password"
            placeholder="Ulangi kata sandi"
            required
        />

        <div class="pt-2">
            <x-ui.button
                type="submit"
                variant="primary"
                size="lg"
                class="w-full !rounded-xl active:scale-[0.99] font-extrabold text-sm tracking-wide shadow-md shadow-amber-500/20 py-3"
            >
                <span>Daftar Sekarang</span>
                <x-ui.icon name="arrow-right" class="w-4 h-4 ml-1" />
            </x-ui.button>
        </div>
    </form>

    <div class="mt-5 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100">
        <span>Sudah memiliki akun?</span>
        <a href="{{ route('login') }}" class="font-bold text-amber-600 hover:text-amber-700 hover:underline ml-1">
            Login
        </a>
    </div>
</x-layouts.guest>
