<x-layouts.guest title="Masuk ke Akun">
    <!-- Header Form: Icon & Title -->
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 shadow-md shadow-amber-500/20 mb-3.5">
            <x-ui.icon name="lock" class="w-5 h-5" />
        </div>
        <h1 class="text-2xl font-black text-zinc-900 tracking-tight leading-tight">Selamat datang</h1>
        <p class="text-xs text-zinc-500 mt-1 max-w-xs mx-auto">
            Masuk ke akun Anda untuk mengakses layanan, antrean, atau dashboard.
        </p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-ui.input
            label="Username / Email / No. HP"
            name="login"
            :value="old('login')"
            placeholder="nama@email.com / 0812..."
            required
            autofocus
        />

        <x-ui.input
            label="Kata Sandi"
            name="password"
            type="password"
            placeholder="Masukkan kata sandi"
            required
        />

        <div class="flex items-center text-xs pt-1">
            <label class="inline-flex items-center gap-2 text-zinc-600 font-medium cursor-pointer select-none">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 cursor-pointer"
                >
                <span>Ingat Saya</span>
            </label>
        </div>

        <div class="pt-2">
            <x-ui.button
                type="submit"
                variant="primary"
                size="lg"
                class="w-full !rounded-xl active:scale-[0.99] font-extrabold text-sm tracking-wide shadow-md shadow-amber-500/20 py-3"
            >
                <span>Login</span>
                <x-ui.icon name="arrow-right" class="w-4 h-4 ml-1" />
            </x-ui.button>
        </div>
    </form>

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100">
        <span>Belum punya akun?</span>
        <a href="{{ route('register') }}" class="font-bold text-amber-600 hover:text-amber-700 hover:underline ml-1">
            Daftar
        </a>
    </div>
</x-layouts.guest>
