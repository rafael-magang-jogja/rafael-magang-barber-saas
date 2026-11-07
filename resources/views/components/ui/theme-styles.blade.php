@props([
    'color' => null,
    'tenant' => null,
])

@php
    $isSuperadminArea = request()->routeIs('platform.*')
        || request()->is('platform*')
        || app(\App\Support\Tenancy\CurrentTenant::class)->role() === 'superadmin'
        || request()->attributes->get('current_role') === 'superadmin';

    $effectiveTenant = $isSuperadminArea ? null : ($tenant ?? app(\App\Support\Tenancy\CurrentTenant::class)->get());

    // Warna tema kustom hanya aktif untuk tenant dengan fitur brand_color (Pro & Business).
    // Untuk Superadmin, Starter, dan mode Official Platform, selalu gunakan warna tema resmi platform.
    if ($effectiveTenant && $effectiveTenant->hasFeature('brand_color')) {
        $brandColor = $color ?? $effectiveTenant->brand_color;
    } else {
        $brandColor = \App\Support\Platform\PlatformConfig::themeColor();
    }

    if (empty($brandColor)) {
        $brandColor = '#D97706';
    }

    $brandContrast = '#09090b';
    $luminance = 0.65;
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
            $brandContrast = $luminance > 0.58 ? '#09090b' : '#ffffff';
        }
    }

    // Adaptasi kecerahan shade 300-400 (untuk teks aksen di background gelap)
    // serta shade 600-950 (untuk teks aksen & badge di background terang) agar selalu nyaman di mata
    $mix300 = $luminance < 0.30 ? '32%' : '55%';
    $mix400 = $luminance < 0.30 ? '48%' : '76%';
    $mix600 = $luminance > 0.65 ? '62%' : '82%';
    $mix700 = $luminance > 0.65 ? '48%' : '68%';
    $mix800 = $luminance > 0.65 ? '36%' : '52%';
    $mix900 = $luminance > 0.65 ? '26%' : '38%';
    $mix950 = $luminance > 0.65 ? '18%' : '24%';

    // Warna hover tombol utama yang nyaman sesuai kontras teks
    $buttonHoverBg = $brandContrast === '#ffffff'
        ? 'color-mix(in srgb, var(--tenant-brand) 86%, black)'
        : 'color-mix(in srgb, var(--tenant-brand) 82%, white)';

    $themeCssTag = <<<CSS
<style>
    :root, html {
        --tenant-brand: {$brandColor};
        --tenant-brand-contrast: {$brandContrast};
        --color-brand: var(--tenant-brand);

        /* Transformasikan seluruh palet warna amber/kuning bawaan ke warna tema aktif dengan kurva kontras nyaman */
        --color-amber-50: color-mix(in srgb, var(--tenant-brand) 8%, white);
        --color-amber-100: color-mix(in srgb, var(--tenant-brand) 16%, white);
        --color-amber-200: color-mix(in srgb, var(--tenant-brand) 30%, white);
        --color-amber-300: color-mix(in srgb, var(--tenant-brand) {$mix300}, white);
        --color-amber-400: color-mix(in srgb, var(--tenant-brand) {$mix400}, white);
        --color-amber-500: var(--tenant-brand);
        --color-amber-600: color-mix(in srgb, var(--tenant-brand) {$mix600}, black);
        --color-amber-700: color-mix(in srgb, var(--tenant-brand) {$mix700}, black);
        --color-amber-800: color-mix(in srgb, var(--tenant-brand) {$mix800}, black);
        --color-amber-900: color-mix(in srgb, var(--tenant-brand) {$mix900}, black);
        --color-amber-950: color-mix(in srgb, var(--tenant-brand) {$mix950}, black);
    }

    ::selection {
        background-color: var(--tenant-brand) !important;
        color: {$brandContrast} !important;
    }

    /* Kontras otomatis untuk seluruh tombol, badge, tab, dan elemen interaktif bertema brand utama */
    .bg-amber-500,
    .\!bg-amber-500,
    button.bg-amber-500,
    a.bg-amber-500,
    .bg-amber-400,
    .\!bg-amber-400,
    button.bg-amber-400,
    a.bg-amber-400,
    .bg-amber-600,
    .\!bg-amber-600,
    button.bg-amber-600,
    a.bg-amber-600,
    .hover\:bg-amber-500:hover,
    .hover\:bg-amber-400:hover,
    .hover\:\!bg-amber-400:hover,
    .hover\:bg-amber-600:hover,
    .hover\:bg-amber-300:hover,
    .group:hover .group-hover\:bg-amber-500,
    .group:hover .group-hover\:bg-amber-600,
    .group:hover .group-hover\:bg-amber-400,
    .bg-amber-500 .text-zinc-950,
    .bg-amber-500 .text-zinc-900,
    .\!bg-amber-500 .text-zinc-950,
    .bg-amber-400 .text-zinc-950 {
        color: {$brandContrast} !important;
    }

    .bg-amber-500 svg,
    .\!bg-amber-500 svg,
    button.bg-amber-500 svg,
    a.bg-amber-500 svg,
    .bg-amber-400 svg,
    button.bg-amber-400 svg,
    a.bg-amber-400 svg,
    .bg-amber-600 svg,
    .hover\:bg-amber-500:hover svg,
    .hover\:bg-amber-400:hover svg,
    .hover\:bg-amber-600:hover svg,
    .group:hover .group-hover\:bg-amber-500 svg,
    .group:hover .group-hover\:bg-amber-600 svg {
        color: {$brandContrast} !important;
        stroke: currentColor;
    }

    /* Hover state tombol utama yang lembut dan menjaga keterbacaan teks */
    button.bg-amber-500.hover\:bg-amber-400:hover,
    a.bg-amber-500.hover\:bg-amber-400:hover,
    .\!bg-amber-500.hover\:\!bg-amber-400:hover {
        background-color: {$buttonHoverBg} !important;
    }

    /* Pastikan seluruh input, textarea, dan select tidak memiliki outline hitam bawaan browser dan selalu mengikuti warna tema */
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]):not([type="file"]):not([type="hidden"]):focus,
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]):not([type="file"]):not([type="hidden"]):focus-visible,
    textarea:focus,
    textarea:focus-visible,
    select:focus,
    select:focus-visible,
    [data-select-trigger]:focus,
    [data-select-trigger]:focus-visible {
        outline: none !important;
        border-color: var(--tenant-brand) !important;
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--tenant-brand) 25%, transparent) !important;
    }
</style>
CSS;
@endphp

@if(!empty($brandColor))
{!! $themeCssTag !!}
@endif
