@props([
    'title' => 'Masuk',
    'tenant' => null,
])

@php
    $platformColor = \App\Support\Platform\PlatformConfig::themeColor();
    $platformName = \App\Support\Platform\PlatformConfig::name();
    $platformTagline = \App\Support\Platform\PlatformConfig::tagline();
    $logoType = \App\Support\Platform\PlatformConfig::logoType();
    $logoIcon = \App\Support\Platform\PlatformConfig::logoIcon();
    $logoImageUrl = \App\Support\Platform\PlatformConfig::logoImageUrl();
    $isMaintenance = \App\Support\Platform\PlatformConfig::isMaintenance();
    $announcement = \App\Support\Platform\PlatformConfig::announcementBanner();

    // Hitung warna background gelap turunan dari warna tema aktif (dipergelap elegan)
    $rawColor = ($tenant && $tenant->hasFeature('brand_color'))
        ? ($tenant->brand_color ?? $platformColor)
        : $platformColor;
    $brandHex = ltrim((string) $rawColor, '#');
    if (strlen($brandHex) === 3) {
        $brandHex = $brandHex[0].$brandHex[0].$brandHex[1].$brandHex[1].$brandHex[2].$brandHex[2];
    }
    if (strlen($brandHex) !== 6 || !ctype_xdigit($brandHex)) {
        $brandHex = '0284c7';
    }
    $r = hexdec(substr($brandHex, 0, 2));
    $g = hexdec(substr($brandHex, 2, 2));
    $b = hexdec(substr($brandHex, 4, 2));

    // Pergelap warna tema: campuran 18% warna tema + 82% nuansa gelap murni
    $darkR1 = (int) round(($r * 0.18) + (6 * 0.82));
    $darkG1 = (int) round(($g * 0.18) + (9 * 0.82));
    $darkB1 = (int) round(($b * 0.18) + (16 * 0.82));
    $darkHex1 = sprintf('#%02x%02x%02x', $darkR1, $darkG1, $darkB1);

    $darkR2 = (int) round(($r * 0.08) + (4 * 0.92));
    $darkG2 = (int) round(($g * 0.08) + (6 * 0.92));
    $darkB2 = (int) round(($b * 0.08) + (11 * 0.92));
    $darkHex2 = sprintf('#%02x%02x%02x', $darkR2, $darkG2, $darkB2);
@endphp

<!DOCTYPE html>
<html lang="id" @style(['--tenant-brand: ' . ($tenant?->brand_color ?? $platformColor), '--auth-dark-1: ' . $darkHex1, '--auth-dark-2: ' . $darkHex2])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $tenant?->name ?? $platformName }}</title>
    @php
        $activeFaviconUrl = $tenant ? $tenant->faviconUrl() : \App\Support\Platform\PlatformConfig::faviconUrl();
        $activeSvgFaviconUrl = $tenant ? $tenant->svgFaviconUrl() : (\App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null);
    @endphp
    <link rel="icon" type="image/x-icon" href="{{ $activeFaviconUrl }}">
    @if($activeSvgFaviconUrl)
        <link rel="icon" type="image/svg+xml" href="{{ $activeSvgFaviconUrl }}">
    @endif
    <x-ui.theme-styles :tenant="$tenant" :color="$tenant?->brand_color ?? $platformColor" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* =========================================================================
           RESPONSIVE AUTH LAYOUT:
           - Mobile (< 768px): Mode default (centered single-column card di background gelap)
           - Tablet Portrait (768px - 1023px portrait): Mode default
           - Desktop (>= 1024px) & Tablet Landscape (>= 768px landscape): Mode Split 2-Kolom Modern
           ========================================================================= */
        .auth-split-wrapper {
            min-height: 100vh;
            background-color: #09090b;
            background-image:
                radial-gradient(circle at 20% 0%, color-mix(in srgb, var(--tenant-brand, #F59E0B) 35%, transparent), transparent 45%),
                radial-gradient(circle at 100% 100%, color-mix(in srgb, var(--tenant-brand, #F59E0B) 18%, transparent), transparent 40%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            position: relative;
            overflow-x: hidden;
            width: 100%;
        }
        .auth-split-left {
            display: none !important;
        }
        .auth-mobile-header {
            display: flex !important;
        }
        .auth-split-right {
            width: 100%;
            max-width: 28rem;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .auth-form-card {
            width: 100%;
            border-radius: 1.5rem;
            background-color: #ffffff;
            color: #18181b;
            padding: 2rem 1.75rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.45);
        }

        /* Aktif pada Desktop (>= 1024px) ATAU Tablet Landscape (>= 768px landscape) */
        @media (min-width: 1024px), (min-width: 768px) and (orientation: landscape) {
            .auth-split-wrapper {
                min-height: 100vh;
                height: 100vh;
                width: 100%;
                overflow: hidden;
                background: #ffffff !important;
                background-color: #ffffff !important;
                background-image: none !important;
                display: flex;
                flex-direction: row;
                align-items: stretch;
                justify-content: stretch;
                padding: 0;
            }
            .auth-split-left {
                display: flex !important;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
                width: 44%;
                max-width: 540px;
                min-width: 340px;
                height: 100%;
                background: linear-gradient(165deg, var(--auth-dark-1, #071925) 0%, var(--auth-dark-2, #040d14) 100%);
                color: #ffffff;
                border-top-right-radius: 56px;
                border-bottom-right-radius: 56px;
                position: relative;
                z-index: 10;
                box-shadow: 10px 0 30px -10px rgba(0, 0, 0, 0.35);
                border: none;
                padding: 4rem 3rem 2.5rem 3rem;
                flex-shrink: 0;
                overflow: hidden;
            }
            .auth-mobile-header {
                display: none !important;
            }
            .auth-split-right {
                flex: 1;
                height: 100%;
                max-width: none;
                display: flex;
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                background: #ffffff !important;
                background-color: #ffffff !important;
                padding: 0;
                position: relative;
                overflow-y: auto;
            }
            .auth-form-card {
                width: 100%;
                max-width: 440px;
                border-radius: 0;
                background-color: transparent;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body class="min-h-screen antialiased bg-zinc-950 text-zinc-900 m-0 p-0 selection:bg-amber-500 selection:text-white">
    <div class="auth-split-wrapper">
        <!-- 1. LEFT COLUMN: Hero Brand Showcase (HANYA MUNCUL DI DESKTOP & TABLET LANDSCAPE) -->
        <div class="auth-split-left">
            <div class="w-full"></div>

            <!-- Single Brand Logo & Identity (Center) -->
            <div class="flex flex-col items-center justify-center text-center space-y-4 my-auto px-4 relative z-10 w-full max-w-sm">
                <!-- 1 Logo Utama Saja -->
                @if($tenant)
                    @if($tenant->logo_url)
                        <div class="w-24 h-24 rounded-3xl overflow-hidden p-2 bg-white/10 border border-white/15 shadow-2xl backdrop-blur-md flex items-center justify-center">
                            <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-full h-full object-contain rounded-2xl" />
                        </div>
                    @else
                        <div class="w-24 h-24 rounded-3xl bg-amber-500 text-zinc-950 grid place-items-center shadow-2xl font-black text-3xl border border-white/20">
                            <x-ui.icon name="{{ $logoIcon }}" class="w-12 h-12" />
                        </div>
                    @endif

                    <div class="space-y-1.5 pt-2">
                        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
                            {{ $tenant->name }}
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-300 font-medium leading-relaxed">
                            Portal Layanan &amp; Reservasi Barbershop
                        </p>
                    </div>
                @else
                    @if($logoType === 'image' && $logoImageUrl)
                        <div class="w-24 h-24 rounded-3xl overflow-hidden p-2 bg-white/10 border border-white/15 shadow-2xl backdrop-blur-md flex items-center justify-center">
                            <img src="{{ $logoImageUrl }}" alt="{{ $platformName }}" class="w-full h-full object-contain rounded-2xl" />
                        </div>
                    @else
                        <div class="w-24 h-24 rounded-3xl bg-amber-500 text-zinc-950 grid place-items-center shadow-2xl border border-white/20">
                            <x-ui.icon name="{{ $logoIcon }}" class="w-12 h-12" />
                        </div>
                    @endif

                    <div class="space-y-1.5 pt-2">
                        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
                            {{ $platformName }}
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-300 font-medium leading-relaxed">
                            {{ $platformTagline }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Left Footer Copyright -->
            <div class="w-full text-center relative z-10 pt-4">
                <p class="text-[11px] text-zinc-400 font-medium tracking-wide">
                    &copy; {{ date('Y') }} {{ $tenant?->name ?? $platformName }}. Hak Cipta Dilindungi.
                </p>
            </div>
        </div>

        <!-- 2. RIGHT COLUMN: Form Container -->
        <div class="auth-split-right">
            <div class="w-full min-h-full flex flex-col items-center py-8 px-6 sm:px-10 lg:px-12">
                <!-- Mobile Header (Hanya tampil di Mobile & Tablet Portrait) -->
                <div class="auth-mobile-header flex-col items-center justify-center gap-3 mb-6 text-center w-full max-w-sm">
                    @if($tenant)
                        @if($tenant->logo_url)
                            <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-12 h-12 rounded-2xl object-cover shadow-lg border border-white/20 mx-auto" />
                        @else
                            <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center shadow-lg mx-auto font-extrabold">
                                <x-ui.icon name="{{ $logoIcon }}" class="w-6 h-6" />
                            </div>
                        @endif
                        <div>
                            <p class="font-black text-xl tracking-tight text-white">{{ $tenant->name }}</p>
                            <p class="text-xs text-zinc-400">Portal Layanan &amp; Reservasi</p>
                        </div>
                    @else
                        @if($logoType === 'image' && $logoImageUrl)
                            <img src="{{ $logoImageUrl }}" alt="{{ $platformName }}" class="w-12 h-12 rounded-2xl object-cover shadow-lg border border-white/10 mx-auto" />
                        @else
                            <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center shadow-lg shadow-amber-500/30 mx-auto">
                                <x-ui.icon name="{{ $logoIcon }}" class="w-6 h-6" />
                            </div>
                        @endif
                        <div>
                            <p class="font-black text-xl tracking-tight text-white">{{ $platformName }}</p>
                            <p class="text-xs text-zinc-400">{{ $platformTagline }}</p>
                        </div>
                    @endif
                </div>

                <!-- Wrapper Card with Safe Vertical Auto Margin (mencegah clipping saat layar pendek) -->
                <div class="w-full max-w-[440px] my-auto flex flex-col items-center">
                    <!-- Peringatan Mode Maintenance / Broadcast Platform -->
                    @if($isMaintenance)
                        <div class="mb-5 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs shadow-sm flex items-start gap-3 w-full">
                            <span class="w-6 h-6 rounded-lg bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 mt-0.5 font-bold">
                                <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5" />
                            </span>
                            <div class="leading-relaxed">
                                <p class="font-black text-amber-900 text-xs">Platform Sedang Dalam Pemeliharaan</p>
                                <p class="mt-0.5 text-zinc-600 text-[11px]">
                                    {{ $announcement ?: 'Peningkatan sistem sedang berlangsung. Login non-superadmin dan transaksi kasir dibatasi sementara.' }}
                                </p>
                            </div>
                        </div>
                    @elseif($announcement)
                        <div class="mb-5 p-3.5 rounded-2xl bg-blue-50 border border-blue-200 text-blue-950 text-xs flex items-center gap-3 w-full">
                            <span class="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0">
                                <x-ui.icon name="megaphone" class="w-3.5 h-3.5" />
                            </span>
                            <p class="text-[11px] leading-snug">{{ $announcement }}</p>
                        </div>
                    @endif

                    <!-- Form Card Slot Container -->
                    <div class="auth-form-card">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
