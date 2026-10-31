<x-layouts.app title="Pengaturan Barbershop" subtitle="Profil usaha, identitas brand, reservasi booking, dan QRIS" role="owner">
    @php
        $settings = $tenant->settings ?? [];
        $hasBrandColor = $tenant->hasFeature('brand_color');
        $plan = $tenant->plan();
        $planName = $plan?->name ?? 'Starter';
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Kolom Kiri (Form Pengaturan Utama & Akun Pemilik) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- 1. Form Profil & Identitas Usaha Barbershop -->
            <form method="POST" action="{{ route('owner.settings.update') }}" enctype="multipart/form-data" id="form-barbershop-settings">
                @csrf
                @method('PUT')

                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-bold flex items-center gap-2">
                            <x-ui.icon name="settings" class="w-4 h-4 text-amber-600" />
                            <span>Profil &amp; Identitas Usaha Barbershop</span>
                        </h2>
                    </x-slot:header>

                    <div class="space-y-4">
                        @if(session('status'))
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2">
                                <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>{{ session('status') }}</span>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">Nama Barbershop <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $tenant->name) }}"
                                    required
                                    class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">WhatsApp / Telepon Resmi</label>
                                <input
                                    type="text"
                                    name="phone"
                                    value="{{ old('phone', $tenant->phone) }}"
                                    placeholder="Contoh: 081234567890"
                                    class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">Email Resmi</label>
                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email', $tenant->email) }}"
                                    placeholder="Contoh: info@barbershop.id"
                                    class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <label class="block text-xs font-bold text-zinc-700">Warna Brand Utama (Hex)</label>
                                    @if(! $hasBrandColor)
                                        <a href="{{ route('owner.subscription') }}" class="text-[10px] font-black uppercase text-amber-700 hover:underline flex items-center gap-1">
                                            <x-ui.icon name="lock" class="w-3 h-3 text-amber-600" />
                                            <span>Fitur Pro</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($hasBrandColor)
                                        <input
                                            type="color"
                                            name="brand_color"
                                            value="{{ old('brand_color', $tenant->brand_color ?? '#f59e0b') }}"
                                            class="w-10 h-9 p-0.5 rounded-xl border border-zinc-200 cursor-pointer bg-white"
                                        />
                                        <input
                                            type="text"
                                            value="{{ old('brand_color', $tenant->brand_color ?? '#f59e0b') }}"
                                            readonly
                                            class="w-full text-xs font-mono font-semibold px-3 py-2 rounded-xl bg-zinc-100 border border-zinc-200 text-zinc-600"
                                        />
                                    @else
                                        <input
                                            type="color"
                                            value="{{ $tenant->brand_color ?? '#f59e0b' }}"
                                            disabled
                                            class="w-10 h-9 p-0.5 rounded-xl border border-zinc-200 cursor-not-allowed bg-zinc-100 opacity-60"
                                            title="Warna tema default pada Paket Starter. Upgrade ke Pro untuk mengubah warna brand."
                                        />
                                        <input
                                            type="hidden"
                                            name="brand_color"
                                            value="{{ $tenant->brand_color ?? '#f59e0b' }}"
                                        />
                                        <input
                                            type="text"
                                            value="{{ $tenant->brand_color ?? '#f59e0b' }} (Default Starter)"
                                            readonly
                                            class="w-full text-xs font-mono font-semibold px-3 py-2 rounded-xl bg-zinc-100 border border-zinc-200 text-zinc-500 cursor-not-allowed"
                                        />
                                    @endif
                                </div>
                                @if(! $hasBrandColor)
                                    <p class="text-[11px] text-zinc-500 mt-1">
                                        Warna tema brand aktif mulai paket Pro. <a href="{{ route('owner.subscription') }}" class="font-bold text-amber-600 hover:underline">Upgrade Paket →</a>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">Alamat Kantor Pusat / Operasional</label>
                            <textarea
                                name="address"
                                rows="2"
                                class="w-full text-xs font-medium px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                            >{{ old('address', $tenant->address) }}</textarea>
                        </div>

                        <hr class="border-zinc-100 my-4" />

                        <div class="space-y-4">
                            <h3 class="font-bold text-xs uppercase tracking-wider text-zinc-500">Operasional &amp; QRIS</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">Maks. Booking (Hari)</label>
                                    <input
                                        type="number"
                                        name="booking_advance_days"
                                        min="1"
                                        max="30"
                                        value="{{ old('booking_advance_days', $settings['booking_advance_days'] ?? 7) }}"
                                        class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">Durasi Cukur (Menit)</label>
                                    <input
                                        type="number"
                                        name="default_duration_minutes"
                                        min="10"
                                        max="180"
                                        step="5"
                                        value="{{ old('default_duration_minutes', $settings['default_duration_minutes'] ?? 30) }}"
                                        class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">Nama Akun QRIS</label>
                                    <input
                                        type="text"
                                        name="qris_account"
                                        value="{{ old('qris_account', $settings['qris_account'] ?? '') }}"
                                        placeholder="Contoh: QRIS Barbershop Pusat"
                                        class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">Pengumuman Pelanggan</label>
                            <textarea
                                name="announcement"
                                rows="2"
                                placeholder="Contoh: Buka setiap hari 10.00 - 21.00 WIB."
                                class="w-full text-xs font-medium px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                            >{{ old('announcement', $settings['announcement'] ?? '') }}</textarea>
                        </div>

                        <div class="pt-3 flex items-center justify-end">
                            <x-ui.button type="submit" size="md" variant="primary">
                                <x-ui.icon name="check" class="w-4 h-4" />
                                <span>Simpan Pengaturan Barbershop</span>
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </form>

            <!-- 2. Form Profil Akun Pemilik (Owner) -->
            <form method="POST" action="{{ route('owner.profile.update') }}">
                @csrf
                @method('PUT')

                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-1 w-full">
                            <h2 class="font-bold flex items-center gap-2">
                                <x-ui.icon name="user" class="w-4 h-4 text-amber-600" />
                                <span>Profil Akun Pemilik (Owner)</span>
                            </h2>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-zinc-100 text-zinc-700 border border-zinc-200">
                                Akun Login
                            </span>
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        @if(session('status_profile'))
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2">
                                <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>{{ session('status_profile') }}</span>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Nama Lengkap Pemilik <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', auth()->user()?->name) }}"
                                    required
                                    maxlength="100"
                                    placeholder="Nama Lengkap Pemilik"
                                    class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                                @error('name')
                                    <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Email Akun Login <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email', auth()->user()?->email) }}"
                                    required
                                    maxlength="150"
                                    placeholder="Contoh: owner@barberking.id"
                                    class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                                @error('email')
                                    <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">
                                WhatsApp / Telepon Pribadi
                            </label>
                            <input
                                type="text"
                                name="phone"
                                value="{{ old('phone', auth()->user()?->phone) }}"
                                placeholder="Contoh: 081234567890"
                                maxlength="30"
                                class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                            />
                            @error('phone')
                                <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-[10px] text-zinc-400 mt-1">Digunakan untuk keperluan kontak langsung dan verifikasi keamanan sistem.</p>
                        </div>

                        <div class="pt-3 flex items-center justify-end">
                            <x-ui.button type="submit" size="md" variant="primary">
                                <x-ui.icon name="check" class="w-4 h-4" />
                                <span>Simpan Profil Pemilik</span>
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </form>

            <!-- 3. Form Keamanan & Reset Kata Sandi (Password) -->
            <form method="POST" action="{{ route('owner.password.update') }}">
                @csrf
                @method('PUT')

                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-1 w-full">
                            <h2 class="font-bold flex items-center gap-2">
                                <x-ui.icon name="lock" class="w-4 h-4 text-amber-600" />
                                <span>Keamanan &amp; Ganti Kata Sandi</span>
                            </h2>
                            <span class="text-[10px] text-zinc-500 font-semibold">
                                Minimal 6 karakter
                            </span>
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        @if(session('status_password'))
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2">
                                <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>{{ session('status_password') }}</span>
                            </div>
                        @endif

                        <!-- Password Saat Ini -->
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">
                                Kata Sandi Saat Ini <span class="text-rose-500 font-bold ml-0.5">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    name="current_password"
                                    id="current_password"
                                    required
                                    placeholder="Masukkan kata sandi saat ini"
                                    class="w-full text-xs font-semibold px-3 py-2 pr-10 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                />
                                <button
                                    type="button"
                                    onclick="togglePasswordVisibility('current_password', this)"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 focus:outline-none cursor-pointer p-1"
                                    title="Tampilkan / Sembunyikan"
                                >
                                    <x-ui.icon name="eye" class="w-4 h-4" />
                                </button>
                            </div>
                            @error('current_password')
                                <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password Baru & Konfirmasi Password Baru -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Kata Sandi Baru <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="password"
                                        name="password"
                                        id="new_password"
                                        required
                                        minlength="6"
                                        placeholder="Minimal 6 karakter"
                                        class="w-full text-xs font-semibold px-3 py-2 pr-10 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                    />
                                    <button
                                        type="button"
                                        onclick="togglePasswordVisibility('new_password', this)"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 focus:outline-none cursor-pointer p-1"
                                        title="Tampilkan / Sembunyikan"
                                    >
                                        <x-ui.icon name="eye" class="w-4 h-4" />
                                    </button>
                                </div>
                                @error('password')
                                    <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Konfirmasi Kata Sandi Baru <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="password"
                                        name="password_confirmation"
                                        id="password_confirmation"
                                        required
                                        minlength="6"
                                        placeholder="Ulangi kata sandi baru"
                                        class="w-full text-xs font-semibold px-3 py-2 pr-10 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                                    />
                                    <button
                                        type="button"
                                        onclick="togglePasswordVisibility('password_confirmation', this)"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 focus:outline-none cursor-pointer p-1"
                                        title="Tampilkan / Sembunyikan"
                                    >
                                        <x-ui.icon name="eye" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-zinc-400">
                            Gunakan perpaduan huruf besar, angka, atau simbol agar kata sandi akun Anda tidak mudah ditebak.
                        </p>

                        <div class="pt-3 flex items-center justify-end">
                            <x-ui.button type="submit" size="md" variant="primary">
                                <x-ui.icon name="key" class="w-4 h-4" />
                                <span>Perbarui Kata Sandi</span>
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </form>
        </div>

            <!-- Kolom Kanan: 1. Logo Barbershop, 2. Daftar Cabang Aktif -->
            <div class="space-y-5">
                <!-- Card 1: Logo Barbershop -->
                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-1 w-full">
                            <h2 class="font-bold flex items-center gap-2">
                                <x-ui.icon name="image" class="w-4 h-4 text-amber-600" />
                                <span>Logo Barbershop</span>
                            </h2>
                            @if(! $hasBrandColor)
                                <a href="{{ route('owner.subscription') }}" class="text-[10px] font-black uppercase text-amber-700 hover:underline flex items-center gap-1">
                                    <x-ui.icon name="lock" class="w-3 h-3 text-amber-600" />
                                    <span>Fitur Pro</span>
                                </a>
                            @endif
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        <div class="flex flex-col items-center text-center p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80">
                            <!-- Preview Box -->
                            <div class="relative w-24 h-24 rounded-2xl overflow-hidden shrink-0 border-2 border-white shadow-md bg-white grid place-items-center mb-3">
                                @if($tenant->logo_url)
                                    <img
                                        id="logo-preview-img"
                                        src="{{ $tenant->logo_url }}"
                                        alt="{{ $tenant->name }}"
                                        class="w-full h-full object-cover"
                                    />
                                @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                                    <img
                                        id="logo-preview-img"
                                        src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}"
                                        alt="{{ \App\Support\Platform\PlatformConfig::name() }}"
                                        class="w-full h-full object-cover"
                                    />
                                @else
                                    <img
                                        id="logo-preview-img"
                                        src=""
                                        alt="Preview"
                                        class="w-full h-full object-cover hidden"
                                    />
                                    <div
                                        id="logo-placeholder-icon"
                                        class="w-full h-full grid place-items-center text-white font-black"
                                        style="background-color: {{ $tenant->brand_color ?? \App\Support\Platform\PlatformConfig::themeColor() }};"
                                    >
                                        <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-10 h-10" />
                                    </div>
                                @endif
                            </div>

                            @if($hasBrandColor)
                                <label class="w-full cursor-pointer inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-white hover:bg-zinc-100 text-zinc-800 border border-zinc-300 shadow-2xs transition">
                                    <x-ui.icon name="upload" class="w-4 h-4 text-zinc-600" />
                                    <span>Pilih Gambar Logo</span>
                                    <input
                                        type="file"
                                        name="logo"
                                        id="logo-input"
                                        form="form-barbershop-settings"
                                        accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"
                                        class="hidden"
                                    />
                                </label>

                                <!-- Teks Error Validasi Ukuran & Format File (Di bawah button upload) -->
                                <p id="logo-error" class="text-[11px] font-semibold text-rose-600 mt-2 hidden text-center"></p>
                                @error('logo')
                                    <p class="text-[11px] font-semibold text-rose-600 mt-2 text-center">{{ $message }}</p>
                                @enderror

                                <p id="logo-file-name" class="text-[11px] text-zinc-500 mt-2 truncate w-full text-center">Belum ada file baru dipilih</p>

                                <p class="text-[10px] text-zinc-400 mt-2 leading-relaxed">
                                    Format: PNG, JPG, WEBP, SVG (Maks. 2MB). Ditampilkan di portal publik, struk, dan TV.
                                </p>

                                @if($tenant->logo_path)
                                    <div class="pt-3 w-full border-t border-zinc-200/80 mt-2">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:text-rose-700 cursor-pointer">
                                            <input type="checkbox" name="remove_logo" value="1" form="form-barbershop-settings" class="rounded border-zinc-300 text-rose-600 focus:ring-rose-500">
                                            <span>Hapus logo saat ini</span>
                                        </label>
                                    </div>
                                @endif
                            @else
                                <div class="w-full p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-center space-y-2">
                                    <div class="flex items-center justify-center gap-1.5 text-amber-900 font-extrabold text-xs">
                                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-700" />
                                        <span>Kustom Logo Terkunci</span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 leading-snug">
                                        Kustomisasi logo barbershop aktif mulai paket Pro.
                                    </p>
                                    <a
                                        href="{{ route('owner.subscription') }}"
                                        class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold transition shadow-2xs"
                                    >
                                        <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                                        <span>Upgrade Paket</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                <!-- Card 2: Favicon Barbershop -->
                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-1 w-full">
                            <h2 class="font-bold flex items-center gap-2">
                                <x-ui.icon name="star" class="w-4 h-4 text-amber-600" />
                                <span>Favicon Barbershop</span>
                            </h2>
                            @if($tenant->hasFeature('custom_favicon'))
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    Paket Business
                                </span>
                            @elseif($hasBrandColor)
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300">
                                    Paket Pro
                                </span>
                            @else
                                <a href="{{ route('owner.subscription') }}" class="text-[10px] font-black uppercase text-amber-700 hover:underline flex items-center gap-1">
                                    <x-ui.icon name="lock" class="w-3 h-3 text-amber-600" />
                                    <span>Fitur Pro / Business</span>
                                </a>
                            @endif
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        @if($tenant->hasFeature('custom_favicon'))
                            <!-- Business Tier: Compact Custom Favicon Control -->
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white border border-zinc-200 shadow-2xs flex items-center justify-center shrink-0 overflow-hidden p-1.5">
                                        <img src="{{ $tenant->faviconUrl() }}" alt="Favicon Barbershop" class="w-full h-full object-contain" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-zinc-900 truncate">Favicon Subdomain</p>
                                        <p class="text-[11px] text-zinc-500">Khusus subdomain barbershop Anda</p>
                                    </div>
                                </div>

                                <label class="w-full cursor-pointer inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-bold bg-white hover:bg-zinc-100 text-zinc-800 border border-zinc-300 shadow-2xs transition">
                                    <x-ui.icon name="upload" class="w-3.5 h-3.5 text-zinc-600" />
                                    <span>Upload Berkas Favicon (.ico / .svg)</span>
                                    <input
                                        type="file"
                                        name="favicon"
                                        id="favicon-input"
                                        form="form-barbershop-settings"
                                        accept=".ico,.png,.svg"
                                        class="hidden"
                                    />
                                </label>
                                @error('favicon')
                                    <p class="text-[11px] font-semibold text-rose-600 text-center">{{ $message }}</p>
                                @enderror

                                @if(! empty($tenant->settings['favicon_path']))
                                    <div class="pt-2 border-t border-zinc-200/80">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:text-rose-700 cursor-pointer">
                                            <input type="checkbox" name="remove_favicon" value="1" form="form-barbershop-settings" class="rounded border-zinc-300 text-rose-600 focus:ring-rose-500">
                                            <span>Kembalikan ke favicon otomatis</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                        @elseif($hasBrandColor)
                            <!-- Pro Tier: Simpel, Bersih, dan Kompak -->
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-2.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white border border-zinc-200 shadow-2xs flex items-center justify-center shrink-0 overflow-hidden p-1.5">
                                        <img src="{{ $tenant->faviconUrl() }}" alt="Favicon Barbershop" class="w-full h-full object-contain" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-zinc-900 truncate">Favicon Otomatis</p>
                                        <p class="text-[11px] text-zinc-500">Sinkron dengan logo &amp; warna brand</p>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-zinc-200/60 flex items-center justify-between text-[11px]">
                                    <span class="text-zinc-500">Upload berkas tersendiri?</span>
                                    <a href="{{ route('owner.subscription') }}" class="font-bold text-amber-600 hover:text-amber-700 hover:underline inline-flex items-center gap-0.5">
                                        <span>Paket Business</span>
                                        <x-ui.icon name="chevron-right" class="w-3 h-3" />
                                    </a>
                                </div>
                            </div>
                        @else
                            <!-- Starter Tier: Locked -->
                            <div class="w-full p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-center space-y-2">
                                <div class="flex items-center justify-center gap-1.5 text-amber-900 font-extrabold text-xs">
                                    <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-700" />
                                    <span>Favicon Barbershop Terkunci</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 leading-snug">
                                    Favicon kustom dengan warna brand aktif mulai paket Pro, dan upload berkas favicon kustom tersedia di paket Business.
                                </p>
                                <a
                                    href="{{ route('owner.subscription') }}"
                                    class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold transition shadow-2xs"
                                >
                                    <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                                    <span>Upgrade Paket</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                <!-- Card 3: Daftar Cabang Aktif -->
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-bold flex items-center gap-2">
                            <x-ui.icon name="store" class="w-4 h-4 text-zinc-600" />
                            <span>Daftar Cabang Aktif</span>
                        </h2>
                    </x-slot:header>

                    <div class="space-y-2">
                        @forelse($branches as $b)
                            @php
                                $isBranchLocked = $tenant?->isBranchLocked($b) ?? false;
                            @endphp
                            <div class="flex items-center justify-between p-2.5 rounded-xl {{ $isBranchLocked ? 'bg-rose-50/60 border border-rose-200' : 'bg-zinc-50 border border-zinc-100' }} text-xs">
                                <div class="min-w-0 pr-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="font-bold text-zinc-900 truncate">{{ $b->name }}</p>
                                        @if($isBranchLocked)
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-300">
                                                Terkunci
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-zinc-400 mt-0.5">Kode: {{ $b->code }} · {{ $b->phone ?: 'Tanpa nomor' }}</p>
                                </div>
                                <div class="shrink-0">
                                    @if($isBranchLocked)
                                        <a
                                            href="{{ route('owner.subscription') }}"
                                            class="p-1.5 rounded-lg bg-rose-100 text-rose-600 hover:bg-rose-200 transition inline-flex items-center justify-center"
                                            title="Layar TV dikunci (Cabang melebihi kuota paket {{ $planName }}). Klik untuk upgrade paket."
                                        >
                                            <x-ui.icon name="lock" class="w-3.5 h-3.5" />
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('display.queue', [$tenant->slug, $b->code]) }}"
                                            target="_blank"
                                            class="p-1.5 rounded-lg bg-zinc-200/60 hover:bg-zinc-200 text-zinc-700 transition inline-flex items-center justify-center"
                                            title="Buka Layar TV Cabang {{ $b->name }}"
                                        >
                                            <x-ui.icon name="tv" class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-zinc-400 italic">Belum ada cabang terdaftar.</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>
        </div>

    <script>
        // Toggle visibilitas kata sandi (lihat / sembunyikan)
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (isPassword) {
                btn.innerHTML = `<svg class="w-4 h-4 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" /><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" /><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" /><path d="m2 2 20 20" /></svg>`;
            } else {
                btn.innerHTML = `<svg class="w-4 h-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle cx="12" cy="12" r="3" /></svg>`;
            }
        }

        (function() {
            const logoInput = document.getElementById('logo-input');
            const logoFileName = document.getElementById('logo-file-name');
            const logoError = document.getElementById('logo-error');
            const logoPreview = document.getElementById('logo-preview-img');
            const logoPlaceholder = document.getElementById('logo-placeholder-icon');
            const removeLogoCheckbox = document.querySelector('input[name="remove_logo"]');

            const initialSrc = logoPreview ? logoPreview.src : '';
            const initialHadPreview = logoPreview && !logoPreview.classList.contains('hidden') && initialSrc !== '';

            logoInput?.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // Reset status error
                if (logoError) {
                    logoError.textContent = '';
                    logoError.classList.add('hidden');
                }

                // 1. Ketentuan Ukuran: Maksimal 2MB (2 * 1024 * 1024 = 2,097,152 bytes)
                const MAX_SIZE_BYTES = 2 * 1024 * 1024;
                if (file.size > MAX_SIZE_BYTES) {
                    this.value = ''; // Tolak dan reset input file
                    if (logoFileName) logoFileName.textContent = 'Belum ada file baru dipilih';
                    if (logoError) {
                        logoError.textContent = 'Ukuran file melebihi 2MB! Maksimal 2MB.';
                        logoError.classList.remove('hidden');
                    }
                    // Kembalikan preview ke kondisi semula
                    if (logoPreview) {
                        logoPreview.src = initialSrc;
                        if (!initialHadPreview) logoPreview.classList.add('hidden');
                    }
                    if (logoPlaceholder && !initialHadPreview) {
                        logoPlaceholder.classList.remove('hidden');
                    }
                    return;
                }

                // 2. Ketentuan Format: PNG, JPG, WEBP, SVG
                const allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
                const allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const isFormatValid = allowedMimes.includes(file.type) || allowedExts.includes(ext);

                if (!isFormatValid) {
                    this.value = ''; // Tolak dan reset input file
                    if (logoFileName) logoFileName.textContent = 'Belum ada file baru dipilih';
                    if (logoError) {
                        logoError.textContent = 'Format tidak didukung! Pilih file PNG, JPG, WEBP, atau SVG.';
                        logoError.classList.remove('hidden');
                    }
                    if (logoPreview) {
                        logoPreview.src = initialSrc;
                        if (!initialHadPreview) logoPreview.classList.add('hidden');
                    }
                    if (logoPlaceholder && !initialHadPreview) {
                        logoPlaceholder.classList.remove('hidden');
                    }
                    return;
                }

                // Lolos semua ketentuan
                if (removeLogoCheckbox) {
                    removeLogoCheckbox.checked = false;
                }

                if (logoFileName) {
                    logoFileName.textContent = file.name;
                }

                const reader = new FileReader();
                reader.onload = function(evt) {
                    if (logoPreview) {
                        logoPreview.src = evt.target.result;
                        logoPreview.classList.remove('hidden');
                    }
                    if (logoPlaceholder) {
                        logoPlaceholder.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
            });
        })();
    </script>
</x-layouts.app>
