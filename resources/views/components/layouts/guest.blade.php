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
@endphp

<!DOCTYPE html>
<html lang="id" style="--tenant-brand: {{ $tenant?->brand_color ?? $platformColor }}">
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
</head>
<body class="min-h-screen bg-zinc-950 text-white antialiased flex items-center justify-center p-4 grain">
    <div class="w-full max-w-md">
        @if($tenant)
            <!-- Header Branded Tenant untuk Pelanggan -->
            <div class="flex items-center justify-center gap-3 mb-6">
                @if($tenant->logo_url)
                    <img
                        src="{{ $tenant->logo_url }}"
                        alt="{{ $tenant->name }}"
                        class="w-12 h-12 rounded-2xl object-cover shadow-lg border border-white/20"
                    >
                @elseif($logoType === 'image' && $logoImageUrl)
                    <img
                        src="{{ $logoImageUrl }}"
                        alt="{{ $tenant->name }}"
                        class="w-12 h-12 rounded-2xl object-cover shadow-lg border border-white/20"
                    >
                @else
                    <div
                        class="w-12 h-12 rounded-2xl grid place-items-center shadow-lg text-white shrink-0 font-extrabold text-base"
                        style="background-color: {{ $tenant->brand_color ?? $platformColor }}; box-shadow: 0 10px 25px -5px {{ $tenant->brand_color ?? $platformColor }}40;"
                    >
                        <x-ui.icon name="{{ $logoIcon }}" class="w-6 h-6" />
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <p class="font-black text-xl tracking-tight text-white truncate">{{ $tenant->name }}</p>
                    </div>
                    <p class="text-xs text-zinc-400">Portal Pelanggan &amp; Reservasi</p>
                </div>
            </div>
        @else
            <!-- Header Platform Global (Mengikuti Tema Warna & Pilihan Logo) -->
            <div class="flex items-center justify-center gap-3 mb-6">
                @if($logoType === 'image' && $logoImageUrl)
                    <img
                        src="{{ $logoImageUrl }}"
                        alt="{{ $platformName }}"
                        class="w-12 h-12 rounded-2xl object-cover shadow-lg border border-white/10 shrink-0"
                    />
                @else
                    <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center shadow-lg shadow-amber-500/30 shrink-0">
                        <x-ui.icon name="{{ $logoIcon }}" class="w-6 h-6" />
                    </div>
                @endif
                <div>
                    <p class="font-black text-xl tracking-tight text-white">{{ $platformName }}</p>
                    <p class="text-xs text-zinc-400">{{ $platformTagline }}</p>
                </div>
            </div>
        @endif

        <!-- Peringatan Mode Maintenance / Broadcast Platform -->
        @if($isMaintenance)
            <div class="mb-4 p-4 rounded-2xl bg-amber-500/15 border border-amber-500/40 text-amber-200 text-xs shadow-lg backdrop-blur-xs flex items-start gap-3">
                <span class="w-6 h-6 rounded-lg bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 mt-0.5 font-bold">
                    <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5" />
                </span>
                <div class="leading-relaxed">
                    <p class="font-black text-amber-300 text-xs">Platform Sedang Dalam Pemeliharaan</p>
                    <p class="mt-0.5 text-zinc-300 text-[11px]">
                        {{ $announcement ?: 'Peningkatan sistem sedang berlangsung. Login non-superadmin dan transaksi kasir dibatasi sementara.' }}
                    </p>
                </div>
            </div>
        @elseif($announcement)
            <div class="mb-4 p-3.5 rounded-2xl bg-blue-500/15 border border-blue-500/30 text-blue-200 text-xs flex items-center gap-3">
                <span class="w-6 h-6 rounded-lg bg-blue-500 text-white flex items-center justify-center shrink-0">
                    <x-ui.icon name="megaphone" class="w-3.5 h-3.5" />
                </span>
                <p class="text-[11px] leading-snug">{{ $announcement }}</p>
            </div>
        @endif

        <div class="rounded-3xl bg-white text-zinc-900 p-6 sm:p-8 shadow-2xl">
            {{ $slot }}
        </div>
    </div>
    @livewireScripts
</body>
</html>
