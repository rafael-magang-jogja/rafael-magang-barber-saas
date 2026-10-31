@php
    $tenant = $invoice->tenant;
    $plan = $invoice->subscription?->plan;
    $isPaid = $invoice->status === 'paid';
    $previousUrl = url()->previous();
    $backUrl = ($previousUrl && $previousUrl !== request()->fullUrl() && $previousUrl !== request()->url())
        ? $previousUrl
        : route('platform.subscriptions');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Langganan {{ $invoice->number }} — {{ config('app.name', 'BarberOS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                class="inline-flex items-center gap-1.5 text-xs font-extrabold bg-zinc-950 hover:bg-zinc-800 text-amber-400 px-4 py-2 rounded-xl shadow-xs cursor-pointer transition"
            >
                <x-ui.icon name="printer" class="w-4 h-4" />
                <span>Cetak / Simpan PDF</span>
            </button>
        </div>

        <!-- Kartu Tagihan Resmi SaaS -->
        <div class="bg-white rounded-3xl border border-zinc-200/90 shadow-xl overflow-hidden print:shadow-none print:border-zinc-300">
            <!-- Header Brand Pengelola Platform -->
            <div class="bg-zinc-950 text-white p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 font-black text-xl shadow-md">
                            <x-ui.icon name="scissors" class="w-6 h-6 text-zinc-950" />
                        </div>
                        <div>
                            <h1 class="font-black text-lg tracking-tight">{{ config('app.name', 'BarberOS Platform') }}</h1>
                            <p class="text-xs text-amber-400 font-semibold tracking-wider uppercase">Faktur Pembayaran Layanan SaaS</p>
                        </div>
                    </div>

                    <div class="text-right">
                        @if($isPaid)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                <x-ui.icon name="check" class="w-3 h-3 stroke-[3]" />
                                <span>LUNAS</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
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
                            Periode: {{ $invoice->subscription?->starts_at?->translatedFormat('d M Y') ?? '-' }} s/d {{ $invoice->subscription?->ends_at?->translatedFormat('d M Y') ?? '-' }}
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
                                    <td class="py-3.5 px-4 text-right text-base text-amber-600">
                                        Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Footer Catatan Pembayaran -->
                <div class="pt-4 border-t border-zinc-100 text-center text-zinc-400 text-[11px] space-y-1">
                    <p class="font-bold text-zinc-600">Terima kasih atas kepercayaan Anda menggunakan layanan {{ config('app.name', 'BarberOS') }}.</p>
                    <p>Faktur ini diterbitkan secara resmi melalui sistem platform terkomputerisasi.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
