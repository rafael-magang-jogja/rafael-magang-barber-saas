<x-layouts.guest :title="'Daftar Akun di ' . $tenant->name" :tenant="$tenant">
    <div class="space-y-1">
        <h1 class="text-2xl font-black tracking-tight text-zinc-900">Daftar Akun Baru</h1>
        <p class="text-xs text-zinc-500">
            Daftar sekali untuk kemudahan booking &amp; cek antrean di <span class="font-bold text-zinc-800">{{ $tenant->name }}</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('tenant.register.store', $tenant->slug) }}" class="mt-6 space-y-3.5">
        @csrf

        <x-ui.input
            label="Nama Lengkap"
            name="name"
            :value="old('name')"
            placeholder="Rafael"
            required
            autofocus />

        <x-ui.input
            label="Nomor WhatsApp / HP"
            name="phone"
            :value="old('phone')"
            placeholder="08123456789"
            required />

        <x-ui.input
            label="Alamat Email (Opsional)"
            name="email"
            type="email"
            :value="old('email')"
            placeholder="nama@domain.com" />

        <x-ui.input
            label="Kata Sandi (Min. 8 karakter)"
            name="password"
            type="password"
            placeholder="••••••••"
            required />

        <x-ui.input
            label="Konfirmasi Kata Sandi"
            name="password_confirmation"
            type="password"
            placeholder="••••••••"
            required />

        <div class="pt-2">
            <button
                type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl text-white font-extrabold text-sm shadow-md transition hover:brightness-110 active:scale-[0.98] cursor-pointer"
                style="background-color: {{ $tenant->brand_color ?? '#D97706' }}; box-shadow: 0 4px 14px 0 {{ $tenant->brand_color ?? '#D97706' }}55;"
            >
                <span>Daftar Akun Sekarang</span>
                <x-ui.icon name="arrow-right" class="w-4 h-4" />
            </button>
        </div>
    </form>

    <div class="mt-6 text-center text-xs text-zinc-500 pt-4 border-t border-zinc-100 space-y-2">
        <div>
            <span>Sudah memiliki akun?</span>
            <a
                href="{{ route('tenant.login', $tenant->slug) }}"
                class="font-black hover:underline ml-1"
                style="color: {{ $tenant->brand_color ?? '#D97706' }};"
            >
                Masuk ke Akun
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
</x-layouts.guest>
