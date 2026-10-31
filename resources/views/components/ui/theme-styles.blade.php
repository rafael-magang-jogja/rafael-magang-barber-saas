@props([
    'color' => null,
    'tenant' => null,
])

@php
    $brandColor = $color ?? $tenant?->brand_color;
    if (empty($brandColor)) {
        $currentTenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $brandColor = $currentTenant?->brand_color ?? \App\Support\Platform\PlatformConfig::themeColor();
    }

    $brandContrast = '#09090b';
    if (!empty($brandColor)) {
        $cleanHex = ltrim($brandColor, '#');
        if (strlen($cleanHex) === 3) {
            $cleanHex = $cleanHex[0].$cleanHex[0].$cleanHex[1].$cleanHex[1].$cleanHex[2].$cleanHex[2];
        }
        if (strlen($cleanHex) === 6 && ctype_xdigit($cleanHex)) {
            $r = hexdec(substr($cleanHex, 0, 2));
            $g = hexdec(substr($cleanHex, 2, 2));
            $b = hexdec(substr($cleanHex, 4, 2));
            $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
            $brandContrast = $luminance > 0.6 ? '#09090b' : '#ffffff';
        }
    }
@endphp

@if(!empty($brandColor))
<style>
    :root, html {
        --tenant-brand: {{ $brandColor }};
        --tenant-brand-contrast: {{ $brandContrast }};
        --color-brand: var(--tenant-brand);

        /* Transformasikan seluruh palet warna amber/kuning bawaan ke warna tema pilihan Owner */
        --color-amber-50: color-mix(in srgb, var(--tenant-brand) 12%, white);
        --color-amber-100: color-mix(in srgb, var(--tenant-brand) 22%, white);
        --color-amber-200: color-mix(in srgb, var(--tenant-brand) 38%, white);
        --color-amber-300: color-mix(in srgb, var(--tenant-brand) 58%, white);
        --color-amber-400: color-mix(in srgb, var(--tenant-brand) 78%, white);
        --color-amber-500: var(--tenant-brand);
        --color-amber-600: color-mix(in srgb, var(--tenant-brand) 82%, black);
        --color-amber-700: color-mix(in srgb, var(--tenant-brand) 68%, black);
        --color-amber-800: color-mix(in srgb, var(--tenant-brand) 52%, black);
        --color-amber-900: color-mix(in srgb, var(--tenant-brand) 38%, black);
        --color-amber-950: color-mix(in srgb, var(--tenant-brand) 24%, black);
    }

    /* Kontras otomatis untuk teks tombol / ikon / avatar bertema brand utama */
    .bg-amber-500,
    button.bg-amber-500,
    a.bg-amber-500 {
        color: {{ $brandContrast }} !important;
    }
    .bg-amber-500 svg,
    button.bg-amber-500 svg,
    a.bg-amber-500 svg {
        color: {{ $brandContrast }} !important;
        stroke: currentColor;
    }
</style>
@endif
