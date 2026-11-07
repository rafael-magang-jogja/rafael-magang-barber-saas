@php
    $tenant = $invoice->tenant;
    $plan = $invoice->subscription?->plan ?? $invoice->targetPlan;
    $isPaid = $invoice->status === 'paid';
    $previousUrl = url()->previous();
    $backUrl = ($previousUrl && $previousUrl !== request()->fullUrl() && $previousUrl !== request()->url())
        ? $previousUrl
        : (auth()->user()?->is_superadmin ? route('platform.subscriptions') : route('owner.subscription'));

    $platformName = \App\Support\Platform\PlatformConfig::name();
    $platformColor = \App\Support\Platform\PlatformConfig::themeColor();
    $platformFaviconUrl = \App\Support\Platform\PlatformConfig::faviconUrl();
    $platformSvgFaviconUrl = \App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null;
    $logoType = \App\Support\Platform\PlatformConfig::logoType();
    $logoImageUrl = \App\Support\Platform\PlatformConfig::logoImageUrl();
    $logoIcon = \App\Support\Platform\PlatformConfig::logoIcon();

    $cleanHex = ltrim($platformColor, '#');
    if (strlen($cleanHex) === 3) {
        $cleanHex = $cleanHex[0].$cleanHex[0].$cleanHex[1].$cleanHex[1].$cleanHex[2].$cleanHex[2];
    }
    $platformContrast = '#09090b';
    if (strlen($cleanHex) === 6 && ctype_xdigit($cleanHex)) {
        $r = hexdec(substr($cleanHex, 0, 2));
        $g = hexdec(substr($cleanHex, 2, 2));
        $b = hexdec(substr($cleanHex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $platformContrast = $luminance > 0.6 ? '#09090b' : '#ffffff';
    }
@endphp

<!DOCTYPE html>
<html lang="id" @style(['--tenant-brand: ' . $platformColor, '--tenant-brand-contrast: ' . $platformContrast])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Langganan {{ $invoice->number }} — {{ $platformName }}</title>
    <link rel="icon" type="image/x-icon" href="{{ $platformFaviconUrl }}">
    @if($platformSvgFaviconUrl)
        <link rel="icon" type="image/svg+xml" href="{{ $platformSvgFaviconUrl }}">
    @endif
    <x-ui.theme-styles :color="$platformColor" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: auto;
            margin: 0mm !important;
        }
        @media print {
            @page {
                size: auto;
                margin: 0mm !important;
            }
            html, body {
                background: #ffffff !important;
                padding: 12mm 15mm !important;
                margin: 0 !important;
                width: 100% !important;
            }
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print\:hidden {
                display: none !important;
            }
            .print\:shadow-none {
                box-shadow: none !important;
            }
            .print\:border-zinc-300 {
                border-color: #d4d4d8 !important;
            }
            .print-logo-box {
                background-color: var(--tenant-brand) !important;
                border-color: var(--tenant-brand) !important;
                color: var(--tenant-brand-contrast) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased py-8 px-4">
    <div class="max-w-xl mx-auto space-y-4">
        <!-- Toolbar Aksi (Disembunyikan saat Print) -->
        <div class="flex items-center justify-between gap-2 print:hidden">
            <a
                href="{{ $backUrl }}"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-700 hover:text-zinc-950 bg-white border border-zinc-200 px-3.5 py-2 rounded-xl shadow-2xs transition"
            >
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Kembali</span>
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-1.5 text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-zinc-950 px-4 py-2 rounded-xl shadow-xs cursor-pointer transition"
            >
                <x-ui.icon name="printer" class="w-4 h-4" />
                <span>Cetak / Simpan PDF</span>
            </button>
        </div>

        <!-- Kartu Tagihan Resmi SaaS -->
        <div class="bg-white rounded-3xl border border-zinc-200/90 shadow-xl overflow-hidden print:shadow-none print:border-zinc-300">
            <!-- Header Brand Pengelola Platform -->
            <div class="bg-zinc-950 text-white p-6 sm:p-7" style="background-color: #09090b !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        @if($logoType === 'image' && $logoImageUrl)
                            <img src="{{ $logoImageUrl }}" alt="{{ $platformName }}" class="w-12 h-12 rounded-2xl object-cover shrink-0 border border-white/20 shadow-md" />
                        @else
                            <div
                                class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 font-black text-xl shadow-md print-logo-box"
                                style="background-color: var(--tenant-brand) !important; border: 1.5px solid var(--tenant-brand) !important; color: var(--tenant-brand-contrast) !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;"
                            >
                                <x-ui.icon name="{{ $logoIcon }}" class="w-6 h-6" style="color: var(--tenant-brand-contrast) !important;" />
                            </div>
                        @endif
                        <div>
                            <h1 class="font-black text-lg tracking-tight">{{ $platformName }}</h1>
                            <p class="text-xs font-semibold tracking-wider uppercase" style="color: var(--tenant-brand) !important;">Faktur Pembayaran Layanan SaaS</p>
                        </div>
                    </div>

                    <div class="text-right">
                        @if($isPaid)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30" style="-webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                <x-ui.icon name="check" class="w-3 h-3 stroke-[3]" />
                                <span>LUNAS</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase" style="background-color: color-mix(in srgb, var(--tenant-brand) 20%, transparent) !important; color: var(--tenant-brand) !important; border: 1px solid color-mix(in srgb, var(--tenant-brand) 40%, transparent) !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                <span>PENDING</span>
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-zinc-800/80 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-500 font-semibold block text-[11px] uppercase tracking-wider">No. Tagihan / Invoice</span>
                        <span class="font-mono font-bold text-white text-sm sm:text-base mt-0.5 block">{{ $invoice->number }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-zinc-500 font-semibold block text-[11px] uppercase tracking-wider">Tanggal Diterbitkan</span>
                        <span class="font-bold text-white mt-0.5 block">{{ $invoice->created_at?->translatedFormat('d F Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Identitas Penerima Tagihan (Tenant & Owner) -->
            <div class="p-6 sm:p-7 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-zinc-50 border border-zinc-200/70 text-xs">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-zinc-400 block mb-1">Ditagihkan Kepada:</span>
                        <h3 class="font-black text-sm text-zinc-900">{{ $tenant?->name ?? 'Tenant' }}</h3>
                        <p class="text-zinc-600 mt-0.5">Owner: <strong>{{ $tenant?->owner?->name ?? '-' }}</strong></p>
                        <p class="text-zinc-500 font-mono text-[11px]">{{ $tenant?->owner?->email ?? '-' }}</p>
                        <p class="text-zinc-500 font-mono text-[11px]">{{ $tenant?->owner?->phone ?? '-' }}</p>
                    </div>

                    <div class="sm:text-right">
                        <span class="text-[10px] font-black uppercase tracking-wider text-zinc-400 block mb-1">Status &amp; Periode:</span>
                        <p class="font-bold text-zinc-900">Paket {{ $plan?->name ?? 'Custom' }}</p>
                        <p class="text-zinc-600 font-semibold text-[11px] mt-0.5">
                            Periode: {{ $invoice->period_starts_at?->translatedFormat('d M Y') ?? '-' }} s/d {{ $invoice->period_ends_at?->translatedFormat('d M Y') ?? '-' }}
                        </p>
                        <p class="text-zinc-500 text-[11px] mt-0.5">
                            Batas Jatuh Tempo: <strong class="text-zinc-700">{{ $invoice->due_at?->translatedFormat('d M Y') ?? '-' }}</strong>
                        </p>
                        @if($invoice->paid_at)
                            <p class="text-emerald-700 font-bold text-[11px] mt-0.5">Lunas Dibayar: {{ $invoice->paid_at->translatedFormat('d M Y, H:i') }} ({{ strtoupper($invoice->gateway ?: 'manual') }})</p>
                        @endif
                    </div>
                </div>

                <!-- Rincian Tagihan Layanan -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-zinc-400 mb-3">Rincian Paket &amp; Layanan</h4>
                    <div class="rounded-2xl border border-zinc-200/90 overflow-hidden">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-zinc-50 border-b border-zinc-200 text-zinc-500 font-extrabold uppercase text-[10px] tracking-wider">
                                    <th class="py-3 px-4">Deskripsi Layanan</th>
                                    <th class="py-3 px-4 text-center">Durasi</th>
                                    <th class="py-3 px-4 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr>
                                    <td class="py-3.5 px-4">
                                        <p class="font-black text-zinc-900">Langganan Software SaaS Paket {{ $plan?->name }}</p>
                                        <p class="text-zinc-500 text-[11px] mt-0.5">
                                            Akses portal owner, kuota {{ $plan?->max_branches ?? 1 }} cabang, POS kasir, jadwal kerja &amp; absensi
                                        </p>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-bold text-zinc-700">
                                        1 Bulan (30 Hari)
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-zinc-900">
                                        Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-zinc-50/80 border-t border-zinc-200 font-black">
                                    <td colspan="2" class="py-3.5 px-4 text-right text-zinc-900 uppercase tracking-wider text-xs">Total Pembayaran</td>
                                    <td class="py-3.5 px-4 text-right text-base font-black" style="color: var(--tenant-brand) !important;">
                                        Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Footer Catatan Pembayaran -->
                <div class="pt-4 border-t border-zinc-100 text-center text-zinc-400 text-[11px] space-y-1">
                    <p class="font-bold text-zinc-600">Terima kasih atas kepercayaan Anda menggunakan layanan {{ $platformName }}.</p>
                    <p>Faktur ini diterbitkan secara resmi melalui sistem platform terkomputerisasi.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
