<x-layouts.guest :title="'Masuk ke ' . $tenant->name" :tenant="$tenant">
    <div class="space-y-1">
        <h1 class="text-2xl font-black tracking-tight text-zinc-900">Masuk ke Akun</h1>
        <p class="text-xs text-zinc-500">
            Masuk dengan nomor WhatsApp atau email untuk mengelola antrean &amp; booking di <span class="font-bold text-zinc-800">{{ $tenant->name }}</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('tenant.login.store', $tenant->slug) }}" class="mt-6 space-y-4">
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
            placeholder="••••••••"
            required />

        <div class="flex items-center justify-between text-xs">
            <label class="inline-flex items-center gap-2 text-zinc-600 font-medium cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500">
                <span>Ingat saya di perangkat ini</span>
            </label>
        </div>

        <button
            type="submit"
            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl text-white font-extrabold text-sm shadow-md transition hover:brightness-110 active:scale-[0.98] cursor-pointer"
            style="background-color: {{ $tenant->brand_color ?? '#D97706' }}; box-shadow: 0 4px 14px 0 {{ $tenant->brand_color ?? '#D97706' }}55;"
        >
            <span>Masuk Sekarang</span>
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
        </button>
    </form>

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100 space-y-2">
        <div>
            <span>Belum punya akun di {{ $tenant->name }}?</span>
            <a
                href="{{ route('tenant.register', $tenant->slug) }}"
                class="font-black hover:underline ml-1"
                style="color: {{ $tenant->brand_color ?? '#D97706' }};"
            >
                Daftar Akun Baru
            </a>
        </div>

        <div class="pt-2">
            <a
                href="{{ route('customer.portal', $tenant->slug) }}"
                class="inline-flex items-center gap-1.5 text-zinc-500 hover:text-zinc-900 font-medium transition text-xs"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>Kembali ke Halaman {{ $tenant->name }}</span>
            </a>
        </div>
    </div>

    <div class="mt-5 pt-3 border-t border-zinc-100 text-center">
        <a href="{{ route('login') }}" class="text-[11px] text-zinc-400 hover:text-zinc-600 transition inline-flex items-center gap-1">
            <span>Staff / Kasir / Owner Barbershop?</span>
            <span class="underline font-semibold">Masuk Portal Kerja</span>
        </a>
    </div>
</x-layouts.guest>
