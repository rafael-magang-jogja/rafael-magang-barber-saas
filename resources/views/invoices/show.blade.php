@php
    $payment = $order->payments->first();
    $methodLabel = match ($payment?->method?->value ?? 'cash') {
        'qris' => 'QRIS',
        'transfer' => 'Transfer Bank',
        default => 'Tunai (Cash)',
    };

    $currentRole = app(\App\Support\Tenancy\CurrentTenant::class)->role();
    $fallbackBackUrl = match ($currentRole) {
        'owner', 'admin', 'cashier' => route('cashier.dashboard'),
        'barber' => route('barber.dashboard'),
        'superadmin' => route('platform.dashboard'),
        default => $tenant ? route('customer.portal', $tenant->slug) : route('home'),
    };

    $previousUrl = url()->previous();
    $backUrl = ($previousUrl && $previousUrl !== request()->fullUrl() && $previousUrl !== request()->url())
        ? $previousUrl
        : $fallbackBackUrl;
@endphp

<!DOCTYPE html>
<html lang="id" style="--tenant-brand: {{ $tenant?->brand_color ?? '#D97706' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Invoice {{ $order->number }} — {{ $tenant?->name ?? config('app.name') }}</title>
    <x-ui.theme-styles :tenant="$tenant" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased py-8 px-4">
    <div class="max-w-lg mx-auto space-y-4">
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
                <x-ui.icon name="download" class="w-4 h-4" />
                <span>Cetak / Simpan PDF</span>
            </button>
        </div>

        <!-- Kartu Struk Digital / E-Invoice -->
        <div class="bg-white rounded-3xl border border-zinc-200/90 shadow-xl overflow-hidden print:shadow-none print:border-zinc-300">
            <!-- Header Brand -->
            <div class="bg-zinc-950 text-white p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        @if($tenant?->logo_url)
                            <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-11 h-11 rounded-2xl object-cover shrink-0 border border-white/20 shadow-sm" />
                        @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                            <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ \App\Support\Platform\PlatformConfig::name() }}" class="w-11 h-11 rounded-2xl object-cover shrink-0 border border-white/20 shadow-sm" />
                        @else
                            <div class="w-11 h-11 rounded-2xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 font-black">
                                <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-5 h-5" />
                            </div>
                        @endif
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-amber-400">E-Invoice Resmi</p>
                                @if(! ($tenant?->hasFeature('white_label') ?? false))
                                    <span class="text-zinc-600">·</span>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-zinc-300 bg-white/10 px-2 py-0.5 rounded-full border border-white/15">
                                        Powered by {{ config('app.name', 'BarberOS') }}
                                    </span>
                                @endif
                            </div>
                            <h1 class="text-lg font-extrabold leading-tight mt-0.5">{{ $tenant?->name }}</h1>
                            <p class="text-xs text-zinc-400">{{ $order->branch?->name }} · {{ $order->branch?->address ?? 'Outlet Resmi' }}</p>
                        </div>
                    </div>
                    @php
                        $statusVal = $order->payment_status instanceof \BackedEnum ? $order->payment_status->value : (string) $order->payment_status;
                        [$statusBadgeClass, $statusBadgeLabel] = match ($statusVal) {
                            'paid' => ['bg-emerald-500 text-white', 'Lunas'],
                            'refunded' => ['bg-rose-600 text-white', 'Refund'],
                            default => ['bg-amber-500 text-zinc-950', 'Belum Bayar'],
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold tracking-wide shadow-xs shrink-0 {{ $statusBadgeClass }}">
                        <x-ui.icon name="check" class="w-3.5 h-3.5" />
                        <span>{{ $statusBadgeLabel }}</span>
                    </span>
                </div>

                <div class="mt-5 pt-4 border-t border-zinc-800 flex items-center justify-between text-xs">
                    <div>
                        <p class="text-zinc-400">Nomor Invoice</p>
                        <p class="font-extrabold text-sm text-amber-400 num mt-0.5">{{ $order->number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-zinc-400">Waktu Pembayaran</p>
                        <p class="font-bold text-zinc-200 num mt-0.5">{{ $order->paid_at?->format('d M Y · H:i') ?? $order->created_at?->format('d M Y · H:i') }} WIB</p>
                    </div>
                </div>
            </div>

            <!-- Detail Pelanggan & Layanan -->
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-2 gap-3 rounded-2xl bg-zinc-50 border border-zinc-200/70 p-3.5 text-xs">
                    <div>
                        <p class="text-zinc-400 font-semibold uppercase tracking-wider text-[10px]">Pelanggan</p>
                        <p class="font-extrabold text-zinc-900 text-sm mt-0.5">{{ $order->customerProfile?->name ?? 'Walk-in Customer' }}</p>
                        @if($order->customerProfile?->phone)
                            <p class="text-[11px] text-zinc-500 font-mono mt-0.5">{{ $order->customerProfile->phone }}</p>
                        @endif
                        @if($order->queueTicket)
                            <p class="text-[11px] text-zinc-500 mt-1">No. Antrean: <b class="num text-zinc-900 bg-amber-100/80 px-1.5 py-0.5 rounded text-xs font-mono font-bold">{{ $order->queueTicket->number }}</b></p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-zinc-400 font-semibold uppercase tracking-wider text-[10px]">Petugas &amp; Metode</p>
                        <p class="font-bold text-zinc-900 text-sm mt-0.5">{{ $order->barber?->display_name ?? $order->barber?->user?->name ?? 'Barber Bertugas' }}</p>
                        @if($order->cashier)
                            <p class="text-[11px] text-zinc-500 mt-0.5">Kasir: {{ $order->cashier->name }}</p>
                        @endif
                        <span class="inline-flex items-center gap-1 mt-1.5 px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold uppercase">
                            <x-ui.icon name="{{ ($payment?->method?->value ?? 'cash') === 'qris' ? 'qr-code' : 'banknote' }}" class="w-3 h-3" />
                            <span>{{ $methodLabel }}</span>
                        </span>
                    </div>
                </div>

                <!-- Daftar Item Layanan -->
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2.5">Rincian Layanan</p>
                    <div class="divide-y divide-zinc-100 text-sm">
                        @foreach($order->items as $item)
                            <div class="py-2.5 flex items-center justify-between gap-2">
                                <div>
                                    <p class="font-bold text-zinc-900">{{ $item->name ?? $item->item_name }}</p>
                                    <p class="text-xs text-zinc-400 num">
                                        {{ $item->qty }}x @ Rp{{ number_format($item->unit_price, 0, ',', '.') }}
                                        @if($item->discount > 0)
                                            <span class="text-emerald-600 font-semibold">(Diskon -Rp{{ number_format($item->discount, 0, ',', '.') }})</span>
                                        @endif
                                    </p>
                                </div>
                                <p class="font-extrabold text-zinc-900 num">Rp{{ number_format($item->line_total, 0, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Ringkasan Pembayaran & Kasir -->
                <div class="pt-3 border-t border-zinc-200 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between text-zinc-500">
                        <span>Subtotal Layanan</span>
                        <span class="font-semibold num">Rp{{ number_format($order->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($order->discount_total > 0)
                        <div class="flex items-center justify-between text-rose-600">
                            <span>Potongan Diskon</span>
                            <span class="font-semibold num">-Rp{{ number_format($order->discount_total, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($order->tip_amount > 0)
                        <div class="flex items-center justify-between text-amber-700">
                            <span>Tip Barber</span>
                            <span class="font-semibold num">+Rp{{ number_format($order->tip_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="pt-2 border-t border-zinc-200 flex items-center justify-between text-base font-extrabold text-zinc-950">
                        <span>Total Tagihan ({{ $methodLabel }})</span>
                        <span class="num text-emerald-600">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</span>
                    </div>

                    @if(($payment?->method?->value ?? 'cash') === 'cash' && $payment?->cash_received !== null)
                        <div class="pt-2 border-t border-dashed border-zinc-200 space-y-1">
                            <div class="flex items-center justify-between text-zinc-600 font-medium">
                                <span>Uang Tunai Diterima</span>
                                <span class="font-bold num text-zinc-900">Rp{{ number_format($payment->cash_received, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-600 font-medium">
                                <span>Kembalian</span>
                                <span class="font-bold num text-emerald-700">Rp{{ number_format($payment->change_amount ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif

                    @if(!empty($payment?->reference))
                        <div class="pt-2 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-500">
                            <span>No. Referensi / Catatan:</span>
                            <span class="font-mono text-zinc-700 font-semibold">{{ $payment->reference }}</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl bg-amber-50/70 border border-amber-200/70 p-3 text-center text-xs text-amber-900">
                    Terima kasih telah berkunjung ke <b>{{ $tenant?->name }}</b>. Simpan E-Invoice ini sebagai bukti pembayaran resmi.
                </div>

                @if(! ($tenant?->hasFeature('white_label') ?? false))
                    <!-- Watermark Platform Resmi untuk Starter & Pro (Hilang di Paket Business / White-Label) -->
                    <div class="pt-3 border-t border-zinc-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-zinc-400 text-center sm:text-left">
                        <div class="flex items-center gap-1.5 justify-center sm:justify-start">
                            <span class="w-4 h-4 rounded-md bg-amber-500/15 text-amber-600 grid place-items-center shrink-0">
                                <x-ui.icon name="scissors" class="w-2.5 h-2.5 text-amber-600" />
                            </span>
                            <span>Diterbitkan via sistem POS &amp; Antrean resmi <strong class="text-zinc-700 font-bold">{{ config('app.name', 'BarberOS') }}</strong></span>
                        </div>
                        <span class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wider">Official Platform</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
