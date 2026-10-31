<x-layouts.guest title="Daftar Akun Pelanggan">
    <h1 class="text-2xl font-extrabold tracking-tight">Daftar Akun Baru</h1>
    <p class="text-sm text-zinc-500 mt-1">Daftar akun pelanggan untuk ambil antrean &amp; booking barbershop.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.input
            label="Nama Lengkap *"
            name="name"
            :value="old('name')"
            placeholder="Rafael Setyawan"
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
            placeholder="rafael@example.com"
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

        <x-ui.button type="submit" size="lg" class="w-full">
            <span>Daftar Sekarang</span>
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
        </x-ui.button>
    </form>

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100">
        <span>Sudah memiliki akun?</span>
        <a href="{{ route('login') }}" class="font-bold text-amber-600 hover:text-amber-700 hover:underline ml-1">
            Masuk di sini
        </a>
    </div>
</x-layouts.guest>
