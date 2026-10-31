<x-layouts.guest title="Masuk ke Akun">
    <h1 class="text-2xl font-extrabold tracking-tight">Selamat datang</h1>
    <p class="text-sm text-zinc-500 mt-1">Masuk dengan email atau nomor HP Anda.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.input
            label="Email atau Nomor HP"
            name="login"
            :value="old('login')"
            placeholder="email@domain.com / 0812..."
            required
            autofocus />

        <x-ui.input
            label="Kata Sandi"
            name="password"
            type="password"
            placeholder="••••••••"
            required />

        <div class="flex items-center justify-between text-xs">
            <label class="inline-flex items-center gap-2 text-zinc-600 font-medium">
                <input type="checkbox" name="remember" class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500">
                Ingat saya
            </label>
        </div>

        <x-ui.button type="submit" size="lg" class="w-full">
            <span>Login</span>
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
        </x-ui.button>
    </form>

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100">
        <span>Belum punya akun?</span>
        <a href="{{ route('register') }}" class="font-bold text-amber-600 hover:text-amber-700 hover:underline ml-1">
            Daftar Akun Baru
        </a>
    </div>
</x-layouts.guest>