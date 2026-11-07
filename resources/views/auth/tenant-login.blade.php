<x-layouts.guest :title="'Masuk ke ' . $tenant->name" :tenant="$tenant">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 shadow-md shadow-amber-500/20 mb-3.5">
            <x-ui.icon name="lock" class="w-5 h-5" />
        </div>
        <h1 class="text-2xl font-black tracking-tight text-zinc-900 leading-tight">Log In</h1>
        <p class="text-xs text-zinc-500 mt-1 max-w-xs mx-auto">
            Masuk dengan nomor WhatsApp atau email untuk layanan di <span class="font-bold text-zinc-800">{{ $tenant->name }}</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('tenant.login.store', $tenant->slug) }}" class="space-y-4">
        @csrf

        <x-ui.input
            label="Nomor WhatsApp atau Email"
            name="login"
            :value="old('login')"
            placeholder="0812... / nama@domain.com"
            required
            autofocus />

        <x-ui.input
            label="Kata Sandi"
            name="password"
            type="password"
            placeholder="Masukkan kata sandi"
            required />

        <div class="flex items-center text-xs pt-1">
            <label class="inline-flex items-center gap-2 text-zinc-600 font-medium cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 cursor-pointer">
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

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100 space-y-2">
        <div>
            <span>Belum punya akun di {{ $tenant->name }}?</span>
            <a
                href="{{ $tenant->registerUrl() }}"
                class="font-bold text-amber-600 hover:text-amber-700 hover:underline ml-1"
            >
                Daftar
            </a>
        </div>

        <div class="pt-2">
            <a
                href="{{ $tenant->customerPortalUrl() }}"
                class="inline-flex items-center gap-1.5 text-zinc-500 hover:text-zinc-900 font-medium transition text-xs"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>Kembali ke Halaman {{ $tenant->name }}</span>
            </a>
        </div>
    </div>
</x-layouts.guest>
