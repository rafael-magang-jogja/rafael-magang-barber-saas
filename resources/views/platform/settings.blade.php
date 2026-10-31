<x-layouts.app title="Konfigurasi Global Platform" subtitle="Pengaturan branding, tema warna, logo, favicon, durasi trial, banner & helpdesk" role="superadmin" :fullWidth="true">
    <div class="w-full">
        <form method="POST" action="{{ route('platform.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Header Info & Breadcrumb Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-zinc-200">
                <div>
                    <h2 class="text-lg font-black text-zinc-900 tracking-tight flex items-center gap-2">
                        <span>Konfigurasi Global Platform</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            Superadmin Master
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Kelola identitas SaaS, tema warna dasar, logo platform, favicon, pemeliharaan &amp; rekening bank resmi.</p>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        type="submit"
                        class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs shadow-xs transition cursor-pointer inline-flex items-center gap-2"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Perubahan Konfigurasi</span>
                    </button>
                </div>
            </div>

            <!-- Responsive Desktop Grid (Left: 7/12, Right: 5/12) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- KOLOM KIRI (7 Kolom pada Desktop) -->
                <div class="lg:col-span-7 xl:col-span-8 space-y-6">

                    <!-- Card 1: Identitas & Kontak Platform -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs">
                        <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-zinc-100">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center">
                                <x-ui.icon name="store" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Identitas Platform SaaS</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Nama dan identitas resmi platform yang tampil pada portal tenant &amp; sistem</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Nama Platform <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="platform_name"
                                    id="platform_name_input"
                                    value="{{ old('platform_name', $settings['platform_name']) }}"
                                    required
                                    maxlength="100"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition font-semibold"
                                />
                                <p class="text-[11px] text-zinc-400 mt-1">Nama branding utama sistem platform ini.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Tagline / Slogan Singkat
                                </label>
                                <input
                                    type="text"
                                    name="platform_tagline"
                                    value="{{ old('platform_tagline', $settings['platform_tagline']) }}"
                                    maxlength="255"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                                />
                                <p class="text-[11px] text-zinc-400 mt-1">Slogan pada portal login &amp; header sistem.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    Email Bantuan (Helpdesk)
                                </label>
                                <input
                                    type="email"
                                    name="support_email"
                                    value="{{ old('support_email', $settings['support_email']) }}"
                                    maxlength="150"
                                    placeholder="support@barberos.id"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 mb-1">
                                    WhatsApp / Call Center Dukungan
                                </label>
                                <input
                                    type="text"
                                    name="support_phone"
                                    value="{{ old('support_phone', $settings['support_phone']) }}"
                                    maxlength="30"
                                    placeholder="081100000001"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Pengaturan Tema Warna, Logo & Favicon Platform -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-6">
                        <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                                <x-ui.icon name="sparkles" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Tema Warna, Logo &amp; Favicon Platform</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Kustomisasi palet warna platform dasar, ikon badge / logo gambar, dan berkas favicon .ico</p>
                            </div>
                        </div>

                        <!-- 1. Pilihan Warna Tema Platform -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-zinc-800">
                                    Tema Warna Platform Dasar <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <span class="text-[11px] text-zinc-400">Default: #D97706 (Amber)</span>
                            </div>

                            @php
                                $currentColor = old('platform_theme_color', $settings['platform_theme_color'] ?? '#D97706');
                                $presetColors = [
                                    ['name' => 'Amber (Default)', 'hex' => '#D97706'],
                                    ['name' => 'Emerald Green', 'hex' => '#059669'],
                                    ['name' => 'Royal Indigo', 'hex' => '#4F46E5'],
                                    ['name' => 'Rose Crimson', 'hex' => '#E11D48'],
                                    ['name' => 'Sky Blue', 'hex' => '#0284C7'],
                                    ['name' => 'Vibrant Violet', 'hex' => '#7C3AED'],
                                    ['name' => 'Sunset Orange', 'hex' => '#EA580C'],
                                    ['name' => 'Zinc Modern', 'hex' => '#27272A'],
                                ];
                            @endphp

                            <!-- Preset Palettes Swatches -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                @foreach($presetColors as $colorPreset)
                                    <button
                                        type="button"
                                        onclick="selectPresetColor('{{ $colorPreset['hex'] }}')"
                                        class="preset-color-btn flex items-center gap-2.5 p-2 rounded-xl border border-zinc-200 hover:border-zinc-300 bg-white transition cursor-pointer text-left group"
                                        data-hex="{{ $colorPreset['hex'] }}"
                                    >
                                        <span class="w-6 h-6 rounded-lg shrink-0 shadow-2xs border border-black/10 transition group-hover:scale-105" style="background-color: {{ $colorPreset['hex'] }}"></span>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-bold text-zinc-800 truncate">{{ $colorPreset['name'] }}</p>
                                            <p class="text-[10px] text-zinc-400 font-mono">{{ $colorPreset['hex'] }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            <!-- Custom Hex & Color Picker Input -->
                            <div class="flex items-center gap-3 pt-1">
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-zinc-200 bg-zinc-50/50">
                                    <input
                                        type="color"
                                        id="platform_theme_color_picker"
                                        value="{{ $currentColor }}"
                                        oninput="syncColorFromPicker(this.value)"
                                        class="w-7 h-7 rounded-lg border-0 cursor-pointer p-0 bg-transparent"
                                        title="Pilih warna bebas"
                                    />
                                    <input
                                        type="text"
                                        name="platform_theme_color"
                                        id="platform_theme_color_input"
                                        value="{{ $currentColor }}"
                                        required
                                        maxlength="7"
                                        oninput="syncColorFromText(this.value)"
                                        class="w-24 text-xs font-mono font-bold text-zinc-900 bg-transparent focus:outline-none uppercase"
                                    />
                                </div>
                                <span class="text-[11px] text-zinc-500">Warna ini diterapkan sebagai tema default portal login, kasir &amp; dashboard.</span>
                            </div>
                        </div>

                        <hr class="border-zinc-100" />

                        <!-- 2. Pilihan Logo Platform: Icon Bawaan vs Upload Gambar -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-800 mb-1">
                                    Format Logo Platform <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <p class="text-[11px] text-zinc-500">Pilih menggunakan icon bawaan platform atau unggah file gambar logo resmi Anda.</p>
                            </div>

                            @php
                                $logoType = old('logo_type', $settings['logo_type'] ?? 'icon');
                                $selectedIcon = old('logo_icon', $settings['logo_icon'] ?? 'scissors');
                                $logoImageUrl = \App\Support\Platform\PlatformConfig::logoImageUrl();
                                
                                $curatedIcons = [
                                    ['id' => 'scissors', 'label' => 'Gunting Barber'],
                                    ['id' => 'razor', 'label' => 'Pisau Cukur'],
                                    ['id' => 'mustache', 'label' => 'Kumis Gentlemen'],
                                ];
                            @endphp

                            <!-- Segmented Toggle -->
                            <div class="inline-flex p-1 rounded-xl bg-zinc-100 border border-zinc-200 text-xs font-bold">
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="logo_type"
                                        value="icon"
                                        class="sr-only"
                                        {{ $logoType === 'icon' ? 'checked' : '' }}
                                        onchange="switchLogoMode('icon')"
                                    />
                                    <span id="tab-btn-icon" class="px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition {{ $logoType === 'icon' ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                                        <x-ui.icon name="star" class="w-3.5 h-3.5" />
                                        <span>Pilih Icon Bawaan</span>
                                    </span>
                                </label>

                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="logo_type"
                                        value="image"
                                        class="sr-only"
                                        {{ $logoType === 'image' ? 'checked' : '' }}
                                        onchange="switchLogoMode('image')"
                                    />
                                    <span id="tab-btn-image" class="px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition {{ $logoType === 'image' ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                                        <x-ui.icon name="image" class="w-3.5 h-3.5" />
                                        <span>Upload File Logo Gambar</span>
                                    </span>
                                </label>
                            </div>

                            <!-- Panel 1: Pilihan Icon Bawaan (Hanya 3 Pilihan Terpilih) -->
                            <div id="panel-logo-icon" class="{{ $logoType === 'icon' ? 'block' : 'hidden' }} space-y-3">
                                <p class="text-xs font-bold text-zinc-700">Pilih salah satu icon resmi khas Barbershop:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    @foreach($curatedIcons as $iconItem)
                                        <label class="cursor-pointer">
                                            <input
                                                type="radio"
                                                name="logo_icon"
                                                value="{{ $iconItem['id'] }}"
                                                class="sr-only logo-icon-radio"
                                                {{ $selectedIcon === $iconItem['id'] ? 'checked' : '' }}
                                                onchange="updateIconPreview('{{ $iconItem['id'] }}')"
                                            />
                                            <div class="icon-card p-3 rounded-xl border {{ $selectedIcon === $iconItem['id'] ? 'border-amber-500 bg-amber-50/40 ring-2 ring-amber-500/20' : 'border-zinc-200 bg-zinc-50/50 hover:bg-zinc-100/70' }} flex items-center gap-3 transition">
                                                <div class="w-9 h-9 rounded-lg bg-zinc-900 text-amber-400 flex items-center justify-center shrink-0">
                                                    <x-ui.icon name="{{ $iconItem['id'] }}" class="w-5 h-5" />
                                                </div>
                                                <span class="text-xs font-bold text-zinc-800">{{ $iconItem['label'] }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Panel 2: Upload File Logo Gambar (Desain Modern Dropzone & Validasi Ketat 2MB) -->
                            <div id="panel-logo-image" class="{{ $logoType === 'image' ? 'block' : 'hidden' }} space-y-3">
                                <!-- Modern Interactive Dropzone Card -->
                                <div
                                    id="logo-dropzone"
                                    onclick="document.getElementById('logo_image_input').click()"
                                    class="p-6 sm:p-7 rounded-3xl border-2 border-dashed border-zinc-300 hover:border-amber-500 bg-zinc-50/70 hover:bg-amber-50/15 transition-all duration-200 cursor-pointer text-center group relative shadow-2xs"
                                >
                                    <input
                                        type="file"
                                        name="logo_image"
                                        id="logo_image_input"
                                        accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"
                                        onchange="validateAndPreviewLogo(this)"
                                        class="hidden"
                                    />

                                    <div class="w-13 h-13 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center mx-auto mb-3 shadow-inner group-hover:scale-110 group-hover:bg-amber-500/20 transition-all duration-200">
                                        <x-ui.icon name="upload-cloud" class="w-6 h-6 text-amber-600" />
                                    </div>

                                    <p class="text-xs sm:text-sm font-black text-zinc-900 group-hover:text-amber-600 transition">
                                        <span class="underline decoration-amber-500/50 underline-offset-2">Klik untuk pilih logo</span> atau seret file ke sini
                                    </p>
                                    <p class="text-[11px] text-zinc-500 mt-1">
                                        Mendukung berkas <span class="font-bold text-zinc-700">PNG, JPG, WEBP, SVG</span> (Batas Maksimal <span class="font-extrabold text-amber-600">2MB</span>)
                                    </p>

                                    <div class="mt-3.5 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-zinc-900 group-hover:bg-amber-600 text-white text-xs font-bold shadow-xs transition">
                                        <x-ui.icon name="upload" class="w-3.5 h-3.5" />
                                        <span>Pilih Berkas dari Komputer</span>
                                    </div>
                                </div>

                                <!-- Status Berkas Logo Terpilih / Aktif -->
                                <div id="logo-preview-card" class="p-3.5 rounded-2xl bg-white border border-zinc-200/90 shadow-2xs flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div id="logo-image-preview-container" class="w-12 h-12 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center shrink-0 overflow-hidden p-1 shadow-xs">
                                            @if($logoImageUrl)
                                                <img src="{{ $logoImageUrl }}" id="logo-image-preview-img" alt="Logo Platform" class="w-full h-full object-contain" />
                                            @else
                                                <div id="logo-image-preview-placeholder" class="text-zinc-500 text-xs text-center font-bold flex flex-col items-center">
                                                    <x-ui.icon name="image" class="w-5 h-5 text-zinc-500" />
                                                    <span class="text-[8px] text-zinc-400 mt-0.5">Kosong</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span id="logo-active-status-badge" class="px-2 py-0.5 rounded text-[10px] font-black {{ $logoImageUrl ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-100 text-zinc-600' }} uppercase tracking-wider">
                                                    {{ $logoImageUrl ? 'Logo Aktif' : 'Belum Ada Berkas' }}
                                                </span>
                                            </div>
                                            <p id="logo-file-display-name" class="text-xs font-bold text-zinc-900 truncate mt-0.5">
                                                {{ $logoImageUrl ? basename($settings['logo_image_path'] ?? 'logo.png') : 'Belum ada logo kustom dipilih' }}
                                            </p>
                                            <p id="logo-file-display-size" class="text-[11px] text-zinc-400">
                                                {{ $logoImageUrl ? 'Tersimpan di server platform' : 'Batas ukuran file maksimal 2MB' }}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onclick="document.getElementById('logo_image_input').click()"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-zinc-100 hover:bg-zinc-200 text-zinc-800 border border-zinc-200/80 transition shrink-0 cursor-pointer"
                                    >
                                        Ganti Berkas
                                    </button>
                                </div>

                                <div id="logo-file-status" class="hidden text-xs font-semibold text-emerald-800 bg-emerald-50 px-3 py-2 rounded-xl border border-emerald-200 flex items-center gap-2">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                    <span id="logo-file-status-text" class="truncate"></span>
                                </div>

                                <div id="logo-file-error" class="hidden text-xs font-bold text-rose-800 bg-rose-50 px-3 py-2 rounded-xl border border-rose-300 flex items-center gap-2 animate-shake">
                                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                                    <span id="logo-file-error-text"></span>
                                </div>

                                @error('logo_image')
                                    <div class="text-xs font-bold text-rose-800 bg-rose-50 px-3 py-2 rounded-xl border border-rose-300 flex items-center gap-2">
                                        <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <!-- Live Logo & Theme Preview Card -->
                            <div class="p-3.5 rounded-xl border border-zinc-200 bg-zinc-950 text-white flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div id="live-logo-badge" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-md font-black transition-all" style="background-color: {{ $currentColor }}; color: #09090b;">
                                        <span id="live-icon-render">
                                            <x-ui.icon name="{{ $selectedIcon }}" class="w-5 h-5" />
                                        </span>
                                    </div>
                                    <div class="min-w-0 leading-tight">
                                        <p class="text-xs font-black text-white truncate" id="live-preview-name">{{ $settings['platform_name'] }}</p>
                                        <p class="text-[10px] text-zinc-400">Pratinjau tampilan logo platform</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-mono font-bold text-amber-400 bg-zinc-900 px-2 py-1 rounded-md border border-zinc-800 shrink-0">
                                    Live Preview
                                </span>
                            </div>
                        </div>

                        <hr class="border-zinc-100" />

                        <!-- 3. Upload & Konversi Favicon (.ico & .svg - Batas Maksimal 2MB) -->
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-zinc-800 mb-0.5">
                                    Favicon Platform (.ico &amp; .svg)
                                </label>
                                <p class="text-[11px] text-zinc-500">
                                    Favicon akan otomatis dikonversi dengan rendering anti-aliasing berkualitas tinggi ke berkas <code class="text-[10px] bg-zinc-100 px-1 py-0.5 rounded text-zinc-700 font-mono">.ico</code> dan <code class="text-[10px] bg-zinc-100 px-1 py-0.5 rounded text-zinc-700 font-mono">.svg</code> di browser.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 items-stretch">
                                <!-- Card 1: Simulasi Tab Browser Favicon Nyata -->
                                <div class="p-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 flex flex-col justify-between space-y-3">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2.5">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Pratinjau Tab Browser</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-emerald-100 text-emerald-800 uppercase tracking-wider">Aktif</span>
                                        </div>

                                        <!-- Browser Tab Simulation -->
                                        <div class="bg-zinc-200/70 p-2 rounded-xl border border-zinc-300/80">
                                            <div class="bg-white rounded-lg px-2.5 py-1.5 shadow-2xs flex items-center gap-2 border border-zinc-200/90 max-w-full">
                                                <div class="w-4 h-4 rounded-xs shrink-0 overflow-hidden flex items-center justify-center">
                                                    @if(\App\Support\Platform\PlatformConfig::hasSvgFavicon())
                                                        <img src="{{ \App\Support\Platform\PlatformConfig::svgFaviconUrl() }}" id="favicon-preview-img" alt="Favicon Active" class="w-full h-full object-contain" />
                                                    @else
                                                        <img src="{{ \App\Support\Platform\PlatformConfig::faviconUrl() }}" id="favicon-preview-img" alt="Favicon Active" class="w-full h-full object-contain" />
                                                    @endif
                                                </div>
                                                <span class="text-[11px] font-bold text-zinc-800 truncate flex-1">
                                                    {{ $settings['platform_name'] }} &bull; Sistem POS
                                                </span>
                                                <span class="text-zinc-400 text-[10px] hover:text-zinc-600 cursor-default">&times;</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Link Download/Buka Berkas Favicon -->
                                    <div class="pt-2 border-t border-zinc-200/80 flex items-center justify-between text-xs">
                                        <span class="text-[11px] text-zinc-500">Berkas Terbit:</span>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ \App\Support\Platform\PlatformConfig::faviconUrl() }}" target="_blank" class="text-[11px] font-mono text-amber-700 hover:underline font-bold inline-flex items-center gap-1">
                                                <span>favicon.ico</span>
                                                <x-ui.icon name="external-link" class="w-3 h-3" />
                                            </a>
                                            <span class="text-zinc-300">&bull;</span>
                                            <a href="{{ \App\Support\Platform\PlatformConfig::svgFaviconUrl() }}" target="_blank" class="text-[11px] font-mono text-amber-700 hover:underline font-bold inline-flex items-center gap-1">
                                                <span>favicon.svg</span>
                                                <x-ui.icon name="external-link" class="w-3 h-3" />
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: Modern Favicon Dropzone (Batas 2MB) -->
                                <div
                                    id="favicon-dropzone"
                                    onclick="document.getElementById('favicon_input').click()"
                                    class="p-4 sm:p-5 rounded-2xl border-2 border-dashed border-zinc-300 hover:border-amber-500 bg-white hover:bg-amber-50/15 transition-all duration-200 cursor-pointer flex flex-col items-center justify-center text-center group relative shadow-2xs"
                                >
                                    <input
                                        type="file"
                                        name="favicon"
                                        id="favicon_input"
                                        accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml"
                                        onchange="validateAndPreviewFavicon(this)"
                                        class="hidden"
                                    />

                                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center mb-2 shadow-inner group-hover:scale-110 group-hover:bg-amber-500/20 transition-all duration-200">
                                        <x-ui.icon name="upload-cloud" class="w-5 h-5 text-amber-600" />
                                    </div>

                                    <p class="text-xs font-black text-zinc-900 group-hover:text-amber-600 transition">
                                        <span class="underline decoration-amber-500/50 underline-offset-2">Upload Favicon Khusus</span>
                                    </p>
                                    <p class="text-[10px] text-zinc-500 mt-1">
                                        Format: <span class="font-bold text-zinc-700">.ICO, .PNG, .SVG</span> &bull; Maks. <span class="font-extrabold text-amber-600">2MB</span>
                                    </p>

                                    <div id="favicon-file-status" class="hidden mt-2 text-[11px] font-semibold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 flex items-center gap-1.5 w-full justify-center">
                                        <x-ui.icon name="check" class="w-3 h-3 text-emerald-600 shrink-0" />
                                        <span id="favicon-file-status-text" class="truncate"></span>
                                    </div>

                                    <div id="favicon-file-error" class="hidden mt-2 text-[11px] font-bold text-rose-800 bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-300 flex items-center gap-1.5 w-full justify-center animate-shake">
                                        <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                        <span id="favicon-file-error-text"></span>
                                    </div>

                                    @error('favicon')
                                        <div class="mt-2 text-[11px] font-bold text-rose-800 bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-300 flex items-center gap-1.5 w-full justify-center">
                                            <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                            <span>{{ $message }}</span>
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        </div>

                    </div>

                    <!-- Card 3: Rekening Resmi Pembayaran Platform -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                    <x-ui.icon name="building-2" class="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-zinc-900 tracking-tight">Rekening Resmi Pembayaran Platform</h3>
                                    <p class="text-xs text-zinc-500 mt-0.5">Daftar rekening bank tujuan yang ditampilkan pada form tagihan &amp; invoice transfer owner</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                onclick="addBankAccountRow()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition cursor-pointer shrink-0"
                            >
                                <x-ui.icon name="plus" class="w-3.5 h-3.5" />
                                <span>Tambah Bank</span>
                            </button>
                        </div>

                        <div class="space-y-3" id="bank-accounts-form-list">
                            @forelse($bankAccounts as $index => $acc)
                                <div class="bank-row p-3.5 rounded-xl border border-zinc-200 bg-zinc-50/60 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end transition">
                                    <div class="sm:col-span-3">
                                        <label class="block text-[11px] font-bold text-zinc-700 mb-1">Nama Bank <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                                        <input
                                            type="text"
                                            name="bank_accounts[{{ $index }}][bank]"
                                            value="{{ old("bank_accounts.{$index}.bank", $acc['bank']) }}"
                                            placeholder="Contoh: BCA / Mandiri"
                                            required
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white font-bold text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label class="block text-[11px] font-bold text-zinc-700 mb-1">Nomor Rekening <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                                        <input
                                            type="text"
                                            name="bank_accounts[{{ $index }}][account_number]"
                                            value="{{ old("bank_accounts.{$index}.account_number", $acc['account_number']) }}"
                                            placeholder="Contoh: 8830123456"
                                            required
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white font-mono font-bold text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label class="block text-[11px] font-bold text-zinc-700 mb-1">Atas Nama (a.n) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                                        <input
                                            type="text"
                                            name="bank_accounts[{{ $index }}][account_name]"
                                            value="{{ old("bank_accounts.{$index}.account_name", $acc['account_name']) }}"
                                            placeholder="Contoh: PT Barber King Digital"
                                            required
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                        <input
                                            type="hidden"
                                            name="bank_accounts[{{ $index }}][logo_color]"
                                            value="{{ $acc['logo_color'] ?? 'bg-zinc-800' }}"
                                        />
                                    </div>

                                    <div class="sm:col-span-1 flex justify-end">
                                        <button
                                            type="button"
                                            onclick="removeBankAccountRow(this)"
                                            class="w-8 h-8 rounded-lg bg-white hover:bg-rose-50 text-zinc-400 hover:text-rose-600 border border-zinc-200 flex items-center justify-center transition cursor-pointer"
                                            title="Hapus Rekening Ini"
                                        >
                                            <x-ui.icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-2">Belum ada rekening bank yang diatur.</p>
                            @endforelse
                        </div>
                    </div>

                </div>

                <!-- KOLOM KANAN (5 Kolom pada Desktop) -->
                <div class="lg:col-span-5 xl:col-span-4 space-y-6">

                    <!-- Card 4: Status & Mode Pemeliharaan Platform -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                <x-ui.icon name="alert-triangle" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Status Mode Pemeliharaan</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Kontrol aktifasi perbaikan sistem berkala</p>
                            </div>
                        </div>

                        <!-- Live Status Badge -->
                        @php
                            $isMaint = old('is_maintenance', $settings['is_maintenance']);
                        @endphp
                        <div id="maintenance-status-badge" class="p-4 rounded-xl border {{ $isMaint ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-emerald-50 border-emerald-200 text-emerald-900' }} transition">
                            <div class="flex items-center gap-2.5">
                                <span id="maint-indicator-dot" class="w-3 h-3 rounded-full {{ $isMaint ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                                <p id="maint-status-title" class="font-black text-xs">
                                    {{ $isMaint ? 'MODE PEMELIHARAAN AKTIF' : 'PLATFORM BEROPERASI NORMAL' }}
                                </p>
                            </div>
                            <p id="maint-status-desc" class="text-[11px] text-zinc-600 mt-1.5 leading-relaxed">
                                {{ $isMaint ? 'Akses login umum dan operasional kasir sedang dibatasi dengan peringatan pemeliharaan. Hanya Superadmin yang dapat mengakses sistem.' : 'Seluruh portal login, kasir POS, dan reservasi beroperasi dengan lancar tanpa pembatasan.' }}
                            </p>
                        </div>

                        <!-- Toggle Checkbox Switch -->
                        <div class="p-3.5 rounded-xl border border-zinc-200 bg-zinc-50/50 flex items-start gap-3">
                            <input
                                type="checkbox"
                                id="is_maintenance"
                                name="is_maintenance"
                                value="1"
                                @checked(old('is_maintenance', $settings['is_maintenance']))
                                onchange="toggleMaintenanceUI(this.checked)"
                                class="w-4 h-4 mt-0.5 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 cursor-pointer shrink-0"
                            />
                            <label for="is_maintenance" class="text-xs text-zinc-700 cursor-pointer select-none">
                                <span class="font-bold text-zinc-900 block">Aktifkan Mode Maintenance Platform</span>
                                <span class="text-[11px] text-zinc-500">Jika dicentang, portal login publik dan POS kasir akan menampilkan peringatan dan membatasi akses non-superadmin.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Card 5: Banner Broadcast Pengumuman Global -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                <x-ui.icon name="megaphone" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Banner Pengumuman Global</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Siarkan pesan ke seluruh dashboard pengguna</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">
                                Isi Pesan Pengumuman (Opsional)
                            </label>
                            <textarea
                                name="announcement_banner"
                                rows="3"
                                maxlength="500"
                                placeholder="Contoh: Pemeliharaan server dijadwalkan pada hari Minggu pukul 02:00 - 04:00 WIB..."
                                class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                            >{{ old('announcement_banner', $settings['announcement_banner']) }}</textarea>
                            <p class="text-[11px] text-zinc-400 mt-1">Muncul sebagai notifikasi banner di bagian atas seluruh dashboard.</p>
                        </div>
                    </div>

                    <!-- Card 6: Kebijakan Masa Uji Coba (Trial) -->
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <x-ui.icon name="coins" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Kebijakan Onboarding &amp; Trial</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Konfigurasi otomatis saat mendaftarkan tenant baru</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">
                                Durasi Default Masa Uji Coba <span class="text-rose-500 font-bold ml-0.5">*</span>
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="number"
                                    name="default_trial_days"
                                    value="{{ old('default_trial_days', $settings['default_trial_days']) }}"
                                    required
                                    min="1"
                                    max="90"
                                    class="w-32 px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition font-bold"
                                />
                                <span class="text-xs text-zinc-500 font-semibold">Hari Kalender</span>
                            </div>
                            <p class="text-[11px] text-zinc-400 mt-1">Tenant baru yang didaftarkan otomatis mendapatkan durasi uji coba ini.</p>
                        </div>
                    </div>

                    <!-- Card 7: Aksi Simpan -->
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 shadow-2xs space-y-3">
                        <div class="flex items-center gap-2 text-amber-900 font-bold text-xs">
                            <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Perubahan Langsung Aktif</span>
                        </div>
                        <p class="text-[11px] text-amber-800 leading-relaxed">
                            Tema warna, logo, favicon, dan status maintenance akan diperbarui seketika di seluruh aplikasi setelah disimpan.
                        </p>
                        <button
                            type="submit"
                            class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs shadow-xs transition cursor-pointer flex items-center justify-center gap-2"
                        >
                            <x-ui.icon name="check" class="w-4 h-4" />
                            <span>Simpan Perubahan Konfigurasi</span>
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <!-- Client-side Interactive Scripts -->
    <script>
        // 1. Pilihan Warna Tema
        function selectPresetColor(hex) {
            document.getElementById('platform_theme_color_picker').value = hex;
            document.getElementById('platform_theme_color_input').value = hex;
            applyThemePreview(hex);
        }

        function syncColorFromPicker(hex) {
            document.getElementById('platform_theme_color_input').value = hex.toUpperCase();
            applyThemePreview(hex);
        }

        function syncColorFromText(val) {
            if (/^#[0-9A-Fa-f]{6}$/i.test(val)) {
                document.getElementById('platform_theme_color_picker').value = val;
                applyThemePreview(val);
            }
        }

        function applyThemePreview(hex) {
            const badge = document.getElementById('live-logo-badge');
            if (badge) {
                badge.style.backgroundColor = hex;
                // Hitung kontras warna teks/ikon
                const clean = hex.replace('#', '');
                const r = parseInt(clean.substring(0, 2), 16);
                const g = parseInt(clean.substring(2, 4), 16);
                const b = parseInt(clean.substring(4, 6), 16);
                const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                badge.style.color = lum > 0.65 ? '#09090b' : '#ffffff';
            }
        }

        // 2. Switch Mode Logo (Icon vs Image)
        function switchLogoMode(mode) {
            const panelIcon = document.getElementById('panel-logo-icon');
            const panelImage = document.getElementById('panel-logo-image');
            const btnIcon = document.getElementById('tab-btn-icon');
            const btnImage = document.getElementById('tab-btn-image');

            if (mode === 'icon') {
                panelIcon.classList.remove('hidden');
                panelImage.classList.add('hidden');
                btnIcon.className = 'px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition bg-white text-zinc-900 shadow-2xs';
                btnImage.className = 'px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition text-zinc-600 hover:text-zinc-900';
            } else {
                panelIcon.classList.add('hidden');
                panelImage.classList.remove('hidden');
                btnIcon.className = 'px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition text-zinc-600 hover:text-zinc-900';
                btnImage.className = 'px-3.5 py-1.5 rounded-lg inline-flex items-center gap-1.5 transition bg-white text-zinc-900 shadow-2xs';
            }
        }

        // 3. Update Icon Selection & Live Preview (Hanya 3 Pilihan Terpilih)
        const iconSvgs = {
            scissors: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/></svg>',
            razor: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M4.8 4.2c-.8.5-1.1 1.4-.7 2.2l8.4 7.6 2.5-1.8L6.8 4c-.5-.4-1.3-.3-2 .2z"/><path d="M19 14.8c-.5 0-1 .2-1.4.6L2.5 15.5c-1.2 0-1.5.8-1.5 1.7 0 .9.5 1.8 1.8 1.8 5.4.3 11-.4 16.2-1.8l3.6 2.6c.5.4 1.3.2 1.7-.3.4-.6.2-1.3-.3-1.7l-2.4-1.7c.3-.4.5-.8.5-1.3 0-1.1-.9-2-2-2z"/></svg>',
            mustache: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 11c2-2.5 6-3 8-1.2 1.5 1.2 2.5 2 3.8-.3-.3 2-2.3 5.5-6.3 5.7-2.7.1-4.5-1.4-5.5-2.7-1 1.3-2.8 2.8-5.5 2.7-4-.2-6-3.7-6.3-5.7 1.3 2.3 2.3 1.5 3.8.3 2-1.8 6-1.3 8 1.2z"/></svg>',
        };

        function updateIconPreview(iconId) {
            // Update live badge icon
            const target = document.getElementById('live-icon-render');
            if (target && iconSvgs[iconId]) {
                target.innerHTML = iconSvgs[iconId];
            }

            // Update card borders
            document.querySelectorAll('.logo-icon-radio').forEach(r => {
                const card = r.closest('label').querySelector('.icon-card');
                if (r.value === iconId) {
                    card.className = 'icon-card p-3 rounded-xl border border-amber-500 bg-amber-50/40 ring-2 ring-amber-500/20 flex items-center gap-3 transition';
                } else {
                    card.className = 'icon-card p-3 rounded-xl border border-zinc-200 bg-zinc-50/50 hover:bg-zinc-100/70 flex items-center gap-3 transition';
                }
            });
        }

        // 4. Validasi & Live Logo Upload Preview
        function validateAndPreviewLogo(input) {
            const statusBox = document.getElementById('logo-file-status');
            const statusText = document.getElementById('logo-file-status-text');
            const errorBox = document.getElementById('logo-file-error');
            const errorText = document.getElementById('logo-file-error-text');
            const previewContainer = document.getElementById('logo-image-preview-container');
            const liveBadge = document.getElementById('live-logo-badge');

            // Reset pesan sebelumnya
            if (statusBox) statusBox.classList.add('hidden');
            if (errorBox) errorBox.classList.add('hidden');

            if (!input.files || !input.files[0]) {
                return;
            }

            const file = input.files[0];
            const maxSizeBytes = 2 * 1024 * 1024; // 2MB
            const validExtensions = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            const fileExt = file.name.split('.').pop().toLowerCase();

            // Cek ekstensi
            if (!validExtensions.includes(fileExt)) {
                input.value = '';
                if (errorText) errorText.textContent = `Format berkas .${fileExt} tidak didukung. Harap pilih gambar PNG, JPG, WEBP, atau SVG.`;
                if (errorBox) errorBox.classList.remove('hidden');
                return;
            }

            // Cek batas ukuran maksimal 2MB
            if (file.size > maxSizeBytes) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                input.value = '';
                if (errorText) errorText.textContent = `Ukuran berkas (${sizeMb} MB) melebihi batas maksimal 2 MB. Silakan pilih berkas lain yang lebih kecil.`;
                if (errorBox) errorBox.classList.remove('hidden');
                return;
            }

            // Berkas valid - tampilkan status & ukuran
            const readableSize = file.size >= 1024 * 1024
                ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                : Math.round(file.size / 1024) + ' KB';

            if (statusText) statusText.textContent = `Berkas siap diunggah: ${file.name} (${readableSize})`;
            if (statusBox) statusBox.classList.remove('hidden');

            // Update kartu info berkas terpilih
            const nameElem = document.getElementById('logo-file-display-name');
            if (nameElem) nameElem.textContent = file.name;

            const sizeElem = document.getElementById('logo-file-display-size');
            if (sizeElem) sizeElem.textContent = `${readableSize} (Siap Disimpan)`;

            const badgeElem = document.getElementById('logo-active-status-badge');
            if (badgeElem) {
                badgeElem.textContent = 'Berkas Baru Dipilih';
                badgeElem.className = 'px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 uppercase tracking-wider';
            }

            // Render live preview gambar
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewContainer) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-contain" />`;
                }
                if (liveBadge) {
                    liveBadge.innerHTML = `<img src="${e.target.result}" class="w-full h-full rounded-xl object-contain" />`;
                }
            };
            reader.readAsDataURL(file);
        }

        // 5. Validasi & Live Favicon Upload Preview (Batas Maksimal 2MB)
        function validateAndPreviewFavicon(input) {
            const statusBox = document.getElementById('favicon-file-status');
            const statusText = document.getElementById('favicon-file-status-text');
            const errorBox = document.getElementById('favicon-file-error');
            const errorText = document.getElementById('favicon-file-error-text');
            const faviconImg = document.getElementById('favicon-preview-img');

            if (statusBox) statusBox.classList.add('hidden');
            if (errorBox) errorBox.classList.add('hidden');

            if (!input.files || !input.files[0]) {
                return;
            }

            const file = input.files[0];
            const maxSizeBytes = 2 * 1024 * 1024; // 2MB
            const validExtensions = ['ico', 'png', 'svg'];
            const fileExt = file.name.split('.').pop().toLowerCase();

            // Cek ekstensi
            if (!validExtensions.includes(fileExt)) {
                input.value = '';
                if (errorText) errorText.textContent = `Format .${fileExt} tidak didukung. Harap pilih berkas .ICO, .PNG, atau .SVG.`;
                if (errorBox) errorBox.classList.remove('hidden');
                return;
            }

            // Cek batas ukuran maksimal 2MB
            if (file.size > maxSizeBytes) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                input.value = '';
                if (errorText) errorText.textContent = `Ukuran berkas (${sizeMb} MB) melebihi batas maksimal 2 MB.`;
                if (errorBox) errorBox.classList.remove('hidden');
                return;
            }

            // Berkas valid - tampilkan status
            const readableSize = file.size >= 1024 * 1024
                ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                : Math.round(file.size / 1024) + ' KB';

            if (statusText) statusText.textContent = `Favicon siap: ${file.name} (${readableSize})`;
            if (statusBox) statusBox.classList.remove('hidden');

            // Render live preview jika format gambar PNG/SVG/ICO
            if (faviconImg) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    faviconImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // Setup Drag & Drop Handlers untuk Logo & Favicon Dropzone
        function setupDropzone(dropzoneId, inputId, validateFn) {
            const dropzone = document.getElementById(dropzoneId);
            const input = document.getElementById(inputId);
            if (!dropzone || !input) return;

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-amber-500', 'bg-amber-50/30', 'scale-[1.01]');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-amber-500', 'bg-amber-50/30', 'scale-[1.01]');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    input.files = files;
                    validateFn(input);
                }
            }, false);
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupDropzone('logo-dropzone', 'logo_image_input', validateAndPreviewLogo);
            setupDropzone('favicon-dropzone', 'favicon_input', validateAndPreviewFavicon);
        });

        // Live sync platform name in preview
        const nameInput = document.getElementById('platform_name_input');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                const previewText = document.getElementById('live-preview-name');
                if (previewText) {
                    previewText.textContent = this.value || 'BarberOS Platform';
                }
            });
        }

        // 5. Maintenance UI Switch
        function toggleMaintenanceUI(checked) {
            const badge = document.getElementById('maintenance-status-badge');
            const dot = document.getElementById('maint-indicator-dot');
            const title = document.getElementById('maint-status-title');
            const desc = document.getElementById('maint-status-desc');

            if (checked) {
                badge.className = 'p-4 rounded-xl border bg-rose-50 border-rose-200 text-rose-900 transition';
                dot.className = 'w-3 h-3 rounded-full bg-rose-500 animate-pulse';
                title.textContent = 'MODE PEMELIHARAAN AKTIF';
                desc.textContent = 'Akses login umum dan operasional kasir sedang dibatasi dengan peringatan pemeliharaan. Hanya Superadmin yang dapat mengakses sistem.';
            } else {
                badge.className = 'p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-900 transition';
                dot.className = 'w-3 h-3 rounded-full bg-emerald-500';
                title.textContent = 'PLATFORM BEROPERASI NORMAL';
                desc.textContent = 'Seluruh portal login, kasir POS, dan reservasi beroperasi dengan lancar tanpa pembatasan.';
            }
        }

        // 6. Dynamic Bank Accounts Row Add & Remove
        function addBankAccountRow() {
            const list = document.getElementById('bank-accounts-form-list');
            const newIndex = Date.now();
            const row = document.createElement('div');
            row.className = 'bank-row p-3.5 rounded-xl border border-zinc-200 bg-zinc-50/60 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end transition';
            row.innerHTML = `
                <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-zinc-700 mb-1">Nama Bank <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <input
                        type="text"
                        name="bank_accounts[${newIndex}][bank]"
                        placeholder="Contoh: BCA / Mandiri"
                        required
                        class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white font-bold text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />
                </div>
                <div class="sm:col-span-4">
                    <label class="block text-[11px] font-bold text-zinc-700 mb-1">Nomor Rekening <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <input
                        type="text"
                        name="bank_accounts[${newIndex}][account_number]"
                        placeholder="Contoh: 8830123456"
                        required
                        class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white font-mono font-bold text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />
                </div>
                <div class="sm:col-span-4">
                    <label class="block text-[11px] font-bold text-zinc-700 mb-1">Atas Nama (a.n) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <input
                        type="text"
                        name="bank_accounts[${newIndex}][account_name]"
                        placeholder="Contoh: PT Barber King Digital"
                        required
                        class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />
                    <input
                        type="hidden"
                        name="bank_accounts[${newIndex}][logo_color]"
                        value="bg-zinc-800"
                    />
                </div>
                <div class="sm:col-span-1 flex justify-end">
                    <button
                        type="button"
                        onclick="removeBankAccountRow(this)"
                        class="w-8 h-8 rounded-lg bg-white hover:bg-rose-50 text-zinc-400 hover:text-rose-600 border border-zinc-200 flex items-center justify-center transition cursor-pointer"
                        title="Hapus Rekening Ini"
                    >
                        <x-ui.icon name="trash" class="w-4 h-4" />
                    </button>
                </div>
            `;
            list.appendChild(row);
        }

        function removeBankAccountRow(button) {
            const row = button.closest('.bank-row');
            if (row) {
                row.remove();
            }
        }
    </script>
</x-layouts.app>
