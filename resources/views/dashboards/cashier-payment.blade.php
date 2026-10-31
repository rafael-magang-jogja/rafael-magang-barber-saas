@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
    $effectiveRole = $currentTenantCtx->role() ?? request()->attributes->get('current_role') ?? (auth()->user()?->isOwner() ? 'owner' : 'cashier');
    $isOrderPending = $order !== null && $order->payment_status->value === 'pending';
    $existingPayment = $order?->payments->first();
    $existingMethod = $existingPayment?->method->value ?? 'cash';
@endphp

<x-layouts.app :title="'Kasir POS — Pembayaran Antrean ' . $ticket->number" :subtitle="'Proses checkout dan pembayaran kasir untuk antrean ' . $ticket->number" :role="$effectiveRole">
    <div class="space-y-5 sm:space-y-6 max-w-6xl mx-auto pb-10">
        <!-- Notifikasi Flash -->
        <x-ui.flash-alert />

        <!-- ============================================================ -->
        <!-- HEADER POS: NAVIGASI & KARTU NOMOR ANTREAN UTAMA             -->
        <!-- ============================================================ -->
        <div class="p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Sisi Kiri: Tombol Kembali + Identitas Kasir -->
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <a
                    href="{{ route('cashier.dashboard') }}"
                    class="w-10 h-10 inline-flex items-center justify-center rounded-2xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 transition cursor-pointer shadow-2xs shrink-0 border border-zinc-200/70"
                    title="Kembali ke Papan Antrean"
                    aria-label="Kembali ke Papan Antrean"
                >
                    <x-ui.icon name="arrow-left" class="w-5 h-5 stroke-[2.5]" />
                </a>
                
                <div class="h-9 w-px bg-zinc-200 shrink-0"></div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-black text-zinc-950 tracking-tight truncate">
                            Kasir POS &amp; Pembayaran
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-[10px] uppercase tracking-wider shrink-0">
                            Siap Bayar
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500 truncate mt-0.5">
                        {{ $branch?->name ?? 'Cabang Outlet' }} &bull; {{ now()->translatedFormat('l, d F Y') }}
                    </p>
                </div>
            </div>

            <!-- Sisi Kanan: Kartu Nomor Antrean (Lega di Mode Tablet & Mobile) -->
            <div class="flex items-center justify-between lg:justify-end gap-3.5 bg-zinc-50/90 lg:bg-zinc-50/60 p-3 sm:px-4 sm:py-2.5 rounded-2xl border border-zinc-200/80 shrink-0">
                <div class="text-left lg:text-right">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Nomor Antrean</span>
                    <span class="text-xs font-semibold text-zinc-600">Pelanggan Selesai Pangkas</span>
                </div>
                <div class="px-4 py-2 rounded-2xl bg-amber-500 text-zinc-950 font-mono font-black text-xl sm:text-2xl shadow-sm shrink-0 tracking-wider">
                    {{ $ticket->number }}
                </div>
            </div>
        </div>

        @if($isOrderPending)
            <!-- ALERT KHUSUS: PESANAN DIBUAT DARI APLIKASI CUSTOMER -->
            <div class="p-4 sm:p-5 rounded-3xl bg-amber-50 border border-amber-300 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0">
                        <x-ui.icon name="clock" class="w-6 h-6 text-amber-600" />
                    </div>
                    <div class="space-y-0.5">
                        <h3 class="font-extrabold text-sm sm:text-base text-amber-950">
                            Menunggu Verifikasi Kasir (Metode: {{ strtoupper($existingMethod) }})
                        </h3>
                        <p class="text-xs text-amber-800/90 leading-relaxed">
                            Pelanggan telah memilih pembayaran <strong>{{ $existingMethod === 'qris' ? 'QRIS' : 'Tunai di Kasir' }}</strong>. Periksa penerimaan uang sebelum menekan tombol konfirmasi di bawah.
                        </p>
                    </div>
                </div>
                <div class="shrink-0 text-left sm:text-right bg-white/70 p-3 rounded-2xl border border-amber-200 sm:border-0 sm:bg-transparent sm:p-0">
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider block">Total Tagihan</span>
                    <span class="text-xl font-black text-amber-950 num">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</span>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- GRID UTAMA: 2 KOLOM (KIRI: RINCIAN, KANAN: FORM BAYAR)      -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-start">
            
            <!-- ======================================================== -->
            <!-- KOLOM KIRI (7/12): DETAIL PELANGGAN & DAFTAR LAYANAN    -->
            <!-- ======================================================== -->
            <div class="lg:col-span-7 space-y-5">
                
                <!-- 1. KARTU INFORMASI PELANGGAN & BARBER -->
                <div class="p-4 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between gap-2.5 border-b border-zinc-100 pb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="user" class="w-4 h-4" />
                            </span>
                            <h2 class="font-extrabold text-xs sm:text-sm text-zinc-900 tracking-tight truncate">
                                Informasi Pelanggan &amp; Petugas
                            </h2>
                        </div>
                        <span class="px-2.5 py-1 rounded-xl bg-zinc-100 text-zinc-700 border border-zinc-200/70 font-bold text-[10px] uppercase shrink-0 whitespace-nowrap">
                            {{ $ticket->type === 'booking' ? 'Booking' : 'Walk-In' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <!-- Pelanggan -->
                        <div class="p-3.5 rounded-2xl bg-zinc-50/80 border border-zinc-200/70 space-y-1">
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Pelanggan</span>
                            <p class="font-black text-zinc-950 text-sm truncate">
                                {{ $ticket->customerProfile?->name ?? 'Pelanggan Walk-In' }}
                            </p>
                            <p class="text-[11px] text-zinc-500 font-mono truncate">
                                {{ $ticket->customerProfile?->phone ?: 'Tidak ada nomor telepon' }}
                            </p>
                        </div>

                        <!-- Barber Bertugas -->
                        <div class="p-3.5 rounded-2xl bg-zinc-50/80 border border-zinc-200/70 space-y-1">
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Barber yang Melayani</span>
                            <p class="font-black text-amber-700 text-sm truncate flex items-center gap-1.5">
                                <x-ui.icon name="scissors" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                <span>{{ $ticket->barber?->display_name ?? $ticket->barber?->user?->name ?? 'Barber Bertugas' }}</span>
                            </p>
                            <p class="text-[11px] text-zinc-500 truncate">
                                {{ $branch?->name ?? 'Cabang Outlet' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2. KARTU DAFTAR ITEM LAYANAN / PRODUK -->
                <div class="p-4 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between gap-2.5 border-b border-zinc-100 pb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="scissors" class="w-4 h-4" />
                            </span>
                            <h2 class="font-extrabold text-xs sm:text-sm text-zinc-900 tracking-tight truncate">
                                Rincian Layanan Pangkas
                            </h2>
                        </div>
                        <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200/70 font-bold text-[10px] uppercase shrink-0 whitespace-nowrap">
                            {{ $ticket->items->count() }} Item Layanan
                        </span>
                    </div>

                    <!-- List Item Layanan -->
                    <div class="space-y-2.5">
                        @forelse($ticket->items as $idx => $item)
                            <div class="p-3.5 rounded-2xl bg-zinc-50/70 hover:bg-zinc-50 border border-zinc-200/70 flex items-center justify-between gap-3 text-xs transition">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-6 h-6 rounded-lg bg-zinc-200 text-zinc-700 font-mono font-bold text-[10px] flex items-center justify-center shrink-0">
                                        {{ $idx + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-black text-zinc-900 text-xs sm:text-sm truncate">
                                            {{ $item->service_name }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 font-medium">
                                            @if($item->service?->duration_minutes)
                                                Durasi &plusmn; {{ $item->service->duration_minutes }} menit
                                            @else
                                                Layanan Standar Barbershop
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <span class="font-black text-zinc-950 num text-sm sm:text-base shrink-0">
                                    Rp{{ number_format($item->price, 0, ',', '.') }}
                                </span>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-zinc-400 italic">
                                Belum ada rincian item layanan yang dicatat pada antrean ini.
                            </div>
                        @endforelse
                    </div>

                    <!-- Subtotal Rangkuman -->
                    <div class="pt-4 border-t-2 border-dashed border-zinc-200 flex items-center justify-between text-xs">
                        <span class="font-bold text-zinc-500">Subtotal Biaya Layanan:</span>
                        <span class="font-black text-zinc-900 num text-base">
                            Rp{{ number_format($subtotal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- KOLOM KANAN (5/12): FORM PEMBAYARAN & KALKULATOR KASIR   -->
            <!-- ======================================================== -->
            <div class="lg:col-span-5 lg:sticky lg:top-6 space-y-5">
                
                @if($isOrderPending)
                    <!-- ==================================================== -->
                    <!-- FORM KONFIRMASI PEMBAYARAN PENDING                   -->
                    <!-- ==================================================== -->
                    <form method="POST" action="{{ route('cashier.tickets.confirm-payment', $ticket) }}" class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-5">
                        @csrf
                        <div class="border-b border-zinc-100 pb-3 flex items-center justify-between">
                            <h2 class="font-black text-sm text-zinc-950 flex items-center gap-2">
                                <x-ui.icon name="coins" class="w-4 h-4 text-amber-500" />
                                <span>Konfirmasi Uang Masuk</span>
                            </h2>
                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider {{ $existingMethod === 'qris' ? 'bg-sky-100 text-sky-800 border border-sky-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                                {{ strtoupper($existingMethod) }}
                            </span>
                        </div>

                        <!-- Grand Total Banner Kasir Digital -->
                        <div class="p-5 rounded-3xl bg-zinc-950 text-white space-y-3 shadow-md">
                            <div class="flex items-center justify-between text-xs text-zinc-400">
                                <span>Tagihan Layanan</span>
                                <span class="font-bold num">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</span>
                            </div>
                            <div class="pt-3 border-t border-zinc-800 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Total Tagihan</span>
                                    <span class="text-xs font-black text-amber-400">GRAND TOTAL</span>
                                </div>
                                <span class="text-2xl sm:text-3xl font-black text-amber-400 num">
                                    Rp{{ number_format($order->grand_total, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        @if($existingMethod === 'cash')
                            <!-- Form Tunai Pending -->
                            <div class="space-y-3">
                                <label class="block text-xs font-extrabold text-zinc-800">
                                    Uang Tunai Diterima (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-zinc-400">Rp</span>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="cash_received"
                                        id="pending_cash_received"
                                        value="{{ number_format($order->grand_total, 0, ',', '.') }}"
                                        oninput="onPendingCashInput(this, {{ $order->grand_total }})"
                                        class="w-full pl-11 pr-3 py-3 rounded-2xl border-2 border-zinc-200 text-zinc-950 font-black text-base focus:outline-none focus:border-amber-500 transition num"
                                    />
                                </div>

                                <!-- Box Kembalian -->
                                <div id="pending_change_box" class="p-3.5 rounded-2xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 text-xs flex items-center justify-between font-bold">
                                    <span>Kembalian Pelanggan:</span>
                                    <span id="pending_change_amount" class="num font-black text-base text-emerald-700">Rp0</span>
                                </div>
                            </div>
                        @else
                            <!-- Keterangan QRIS Pending -->
                            <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-xs text-sky-950 space-y-2">
                                <p class="font-extrabold flex items-center gap-1.5 text-sky-900">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 text-sky-600 shrink-0" />
                                    <span>Cek Notifikasi Masuk QRIS:</span>
                                </p>
                                <p class="text-[11px] text-zinc-600 leading-relaxed">
                                    Pastikan dana sebesar <strong class="text-zinc-900">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</strong> telah berhasil masuk ke mutasi rekening / soundbox QRIS Anda sebelum menerbitkan struk.
                                </p>
                            </div>
                        @endif

                        <button
                            type="submit"
                            class="w-full py-4 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white font-black text-sm flex items-center justify-center gap-2.5 shadow-md shadow-emerald-950/20 transition cursor-pointer"
                        >
                            <x-ui.icon name="check" class="w-5 h-5 stroke-[3]" />
                            <span>Konfirmasi Pembayaran Lunas &amp; Cetak Struk</span>
                        </button>
                    </form>
                @else
                    <!-- ==================================================== -->
                    <!-- FORM CHECKOUT KASIR BARU (LENGKAP DENGAN HITUNGAN)   -->
                    <!-- ==================================================== -->
                    <form method="POST" action="{{ route('cashier.tickets.checkout', $ticket) }}" class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-5">
                        @csrf
                        
                        <div class="border-b border-zinc-100 pb-3">
                            <h2 class="font-black text-sm text-zinc-950 flex items-center gap-2">
                                <x-ui.icon name="credit-card" class="w-4 h-4 text-amber-500" />
                                <span>Metode &amp; Pembayaran Kasir</span>
                            </h2>
                            <p class="text-[11px] text-zinc-400 mt-0.5">Pilih metode bayar dan input nominal uang kasir</p>
                        </div>

                        <!-- 1. PILIHAN METODE PEMBAYARAN SOLID (GRID 2 KOLOM) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-extrabold text-zinc-800">
                                Metode Pembayaran <span class="text-rose-500">*</span>
                            </label>
                            
                            <div class="grid grid-cols-2 gap-2.5">
                                <!-- Opsi Tunai -->
                                <label class="payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-emerald-600 bg-emerald-50/60 cursor-pointer transition select-none" id="card_method_cash">
                                    <input
                                        type="radio"
                                        name="method"
                                        value="cash"
                                        checked
                                        onchange="switchPaymentMethod('cash')"
                                        class="sr-only"
                                    />
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                                            <x-ui.icon name="banknote" class="w-4 h-4" />
                                        </span>
                                        <span class="w-3.5 h-3.5 rounded-full border-2 border-emerald-600 bg-emerald-600 method-radio-dot"></span>
                                    </div>
                                    <span class="font-black text-xs text-zinc-950 block">Tunai (Cash)</span>
                                    <span class="text-[10px] text-zinc-500 block">Bayar uang fisik di kasir</span>
                                </label>

                                <!-- Opsi QRIS -->
                                <label class="payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-zinc-200 bg-zinc-50/60 hover:bg-zinc-50 cursor-pointer transition select-none" id="card_method_qris">
                                    <input
                                        type="radio"
                                        name="method"
                                        value="qris"
                                        onchange="switchPaymentMethod('qris')"
                                        class="sr-only"
                                    />
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0">
                                            <x-ui.icon name="qr-code" class="w-4 h-4" />
                                        </span>
                                        <span class="w-3.5 h-3.5 rounded-full border-2 border-zinc-300 method-radio-dot"></span>
                                    </div>
                                    <span class="font-black text-xs text-zinc-950 block">QRIS (Scan)</span>
                                    <span class="text-[10px] text-zinc-500 block">m-Banking / E-Wallet</span>
                                </label>
                            </div>
                        </div>

                        <!-- 2. DISKON & TIP (GRID 2 KOLOM RESPONSIF) -->
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-zinc-700">Diskon / Potongan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="discount_total"
                                        id="input_discount"
                                        value="0"
                                        oninput="onDiscountInput(this)"
                                        placeholder="0"
                                        class="w-full pl-8 pr-2.5 py-2.5 rounded-xl border border-zinc-200 text-xs font-bold text-zinc-900 focus:outline-none focus:border-amber-500 num"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-zinc-700">Tip Barber (Opsional)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="tip_amount"
                                        id="input_tip"
                                        value="0"
                                        oninput="onTipInput(this)"
                                        placeholder="0"
                                        class="w-full pl-8 pr-2.5 py-2.5 rounded-xl border border-zinc-200 text-xs font-bold text-zinc-900 focus:outline-none focus:border-amber-500 num"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- 3. GRAND TOTAL BANNER GAYA LAYAR KASIR POS -->
                        <div class="p-5 rounded-3xl bg-zinc-950 text-white space-y-2.5 shadow-md">
                            <div class="flex items-center justify-between text-xs text-zinc-400">
                                <span>Subtotal Layanan:</span>
                                <span class="font-bold num">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div id="row_discount_display" class="hidden items-center justify-between text-xs text-rose-400">
                                <span>Potongan Diskon:</span>
                                <span class="font-bold num" id="val_discount_display">-Rp0</span>
                            </div>
                            <div id="row_tip_display" class="hidden items-center justify-between text-xs text-amber-400">
                                <span>Tip Barber:</span>
                                <span class="font-bold num" id="val_tip_display">+Rp0</span>
                            </div>
                            <div class="pt-3 border-t border-zinc-800 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Total Tagihan Akhir</span>
                                    <span class="text-xs font-black text-amber-400">GRAND TOTAL</span>
                                </div>
                                <span class="text-2xl sm:text-3xl font-black text-amber-400 num" id="display_grand_total">
                                    Rp{{ number_format($subtotal, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <!-- 4. AREA PEMBAYARAN TUNAI (CASH) -->
                        <div id="box_cash_details" class="space-y-3 pt-1">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-extrabold text-zinc-800">
                                    Nominal Uang Tunai Diterima (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-zinc-400">Rp</span>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        name="cash_received"
                                        id="input_cash_received"
                                        value="{{ number_format($subtotal, 0, ',', '.') }}"
                                        oninput="onCashInput(this)"
                                        class="w-full pl-11 pr-3 py-3 rounded-2xl border-2 border-zinc-200 text-zinc-950 font-black text-base focus:outline-none focus:border-amber-500 transition num"
                                    />
                                </div>
                            </div>

                            <!-- Tombol Cepat Nominal Uang (Grid 4 Tombol Rapi & Responsif) -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button
                                    type="button"
                                    onclick="setQuickCash('exact')"
                                    class="py-2.5 px-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 font-extrabold text-xs transition cursor-pointer text-center border border-zinc-200/60"
                                >
                                    Uang Pas
                                </button>
                                <button
                                    type="button"
                                    onclick="setQuickCash(50000)"
                                    class="py-2.5 px-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 font-extrabold text-xs transition cursor-pointer text-center border border-zinc-200/60"
                                >
                                    Rp50.000
                                </button>
                                <button
                                    type="button"
                                    onclick="setQuickCash(100000)"
                                    class="py-2.5 px-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 font-extrabold text-xs transition cursor-pointer text-center border border-zinc-200/60"
                                >
                                    Rp100.000
                                </button>
                                <button
                                    type="button"
                                    onclick="setQuickCash(200000)"
                                    class="py-2.5 px-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 font-extrabold text-xs transition cursor-pointer text-center border border-zinc-200/60"
                                >
                                    Rp200.000
                                </button>
                            </div>

                            <!-- Box Hitungan Kembalian Realtime -->
                            <div id="box_change_result" class="p-4 rounded-2xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 text-xs flex items-center justify-between font-bold">
                                <span class="font-extrabold">Kembalian Pelanggan:</span>
                                <span id="display_change_amount" class="text-base sm:text-lg font-black text-emerald-700 num">Rp0</span>
                            </div>
                        </div>

                        <!-- 5. AREA PANDUAN QRIS -->
                        <div id="box_qris_details" class="hidden space-y-2.5 p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs text-zinc-700">
                            <p class="font-black text-amber-950 flex items-center gap-1.5">
                                <x-ui.icon name="qr-code" class="w-4 h-4 text-amber-600 shrink-0" />
                                <span>Panduan Scan QRIS di Kasir</span>
                            </p>
                            <p class="text-[11px] text-zinc-600 leading-relaxed">
                                Arahkan pelanggan untuk melakukan scan stiker QRIS outlet pada meja kasir dengan nominal tagihan yang tertera di layar.
                            </p>
                        </div>

                        <!-- Nomor Referensi Transaksi (Opsional) -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-zinc-700">No. Referensi / Catatan Struk (Opsional)</label>
                            <input
                                type="text"
                                name="reference"
                                placeholder="Contoh: No. Ref m-Banking atau nomor kartu"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-800 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <!-- Tombol Selesaikan Pembayaran Kasir -->
                        <button
                            type="submit"
                            id="btn_submit_checkout"
                            class="w-full py-4 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white font-black text-sm flex items-center justify-center gap-2.5 shadow-md shadow-emerald-950/20 transition cursor-pointer"
                        >
                            <x-ui.icon name="check" class="w-5 h-5 stroke-[3]" />
                            <span>Selesaikan Pembayaran &amp; Terbitkan Struk</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- SCRIPT KALKULATOR KASIR POS REALTIME & FORMAT RUPIAH RIBUAN -->
    <script>
        const initialSubtotal = {{ $subtotal }};
        let currentGrandTotal = initialSubtotal;

        function parseRawNumber(val) {
            if (!val) return 0;
            const clean = String(val).replace(/\D/g, '');
            return clean ? parseInt(clean, 10) : 0;
        }

        function formatNumberDisplay(num) {
            if (isNaN(num)) return '0';
            return Number(num).toLocaleString('id-ID');
        }

        function handleRupiahInput(input, maxVal = null) {
            let raw = parseRawNumber(input.value);
            if (maxVal !== null && raw > maxVal) {
                raw = maxVal;
            }
            input.value = raw > 0 ? formatNumberDisplay(raw) : (input.value === '' ? '' : '0');
            return raw;
        }

        function onDiscountInput(el) {
            handleRupiahInput(el, initialSubtotal);
            recalculateTotals();
        }

        function onTipInput(el) {
            handleRupiahInput(el);
            recalculateTotals();
        }

        function onCashInput(el) {
            handleRupiahInput(el);
            calculateCashChange();
        }

        function onPendingCashInput(el, grandTotal) {
            handleRupiahInput(el);
            calculatePendingChange(grandTotal);
        }

        function recalculateTotals() {
            const discountInput = document.getElementById('input_discount');
            const tipInput = document.getElementById('input_tip');

            let discount = parseRawNumber(discountInput?.value);
            let tip = parseRawNumber(tipInput?.value);

            if (discount > initialSubtotal) discount = initialSubtotal;

            currentGrandTotal = Math.max(0, (initialSubtotal - discount) + tip);

            // Update row displays
            const rowDiscount = document.getElementById('row_discount_display');
            const valDiscount = document.getElementById('val_discount_display');
            if (rowDiscount && valDiscount) {
                if (discount > 0) {
                    rowDiscount.classList.remove('hidden');
                    rowDiscount.classList.add('flex');
                    valDiscount.textContent = '-Rp' + formatNumberDisplay(discount);
                } else {
                    rowDiscount.classList.add('hidden');
                    rowDiscount.classList.remove('flex');
                }
            }

            const rowTip = document.getElementById('row_tip_display');
            const valTip = document.getElementById('val_tip_display');
            if (rowTip && valTip) {
                if (tip > 0) {
                    rowTip.classList.remove('hidden');
                    rowTip.classList.add('flex');
                    valTip.textContent = '+Rp' + formatNumberDisplay(tip);
                } else {
                    rowTip.classList.add('hidden');
                    rowTip.classList.remove('flex');
                }
            }

            const displayGrand = document.getElementById('display_grand_total');
            if (displayGrand) {
                displayGrand.textContent = 'Rp' + formatNumberDisplay(currentGrandTotal);
            }

            calculateCashChange();
        }

        function calculateCashChange() {
            const cashInput = document.getElementById('input_cash_received');
            const changeBox = document.getElementById('box_change_result');
            const changeDisplay = document.getElementById('display_change_amount');
            const submitBtn = document.getElementById('btn_submit_checkout');
            const selectedMethod = document.querySelector('input[name="method"]:checked')?.value || 'cash';

            if (!cashInput || selectedMethod !== 'cash') return;

            let received = parseRawNumber(cashInput.value);

            if (received >= currentGrandTotal) {
                const change = received - currentGrandTotal;
                if (changeBox) {
                    changeBox.className = 'p-4 rounded-2xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 text-xs flex items-center justify-between font-bold';
                    changeBox.firstElementChild.textContent = 'Kembalian Pelanggan:';
                }
                if (changeDisplay) {
                    changeDisplay.className = 'text-base sm:text-lg font-black text-emerald-700 num';
                    changeDisplay.textContent = 'Rp' + formatNumberDisplay(change);
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            } else {
                const deficit = currentGrandTotal - received;
                if (changeBox) {
                    changeBox.className = 'p-4 rounded-2xl bg-rose-50 border-2 border-rose-400 text-rose-950 text-xs flex items-center justify-between font-bold';
                    changeBox.firstElementChild.textContent = 'Uang Kurang:';
                }
                if (changeDisplay) {
                    changeDisplay.className = 'text-base sm:text-lg font-black text-rose-700 num';
                    changeDisplay.textContent = '-Rp' + formatNumberDisplay(deficit);
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        }

        function setQuickCash(amount) {
            const cashInput = document.getElementById('input_cash_received');
            if (!cashInput) return;

            if (amount === 'exact') {
                cashInput.value = formatNumberDisplay(currentGrandTotal);
            } else {
                cashInput.value = formatNumberDisplay(amount);
            }
            calculateCashChange();
        }

        function switchPaymentMethod(method) {
            const cashBox = document.getElementById('box_cash_details');
            const qrisBox = document.getElementById('box_qris_details');
            const submitBtn = document.getElementById('btn_submit_checkout');
            const cardCash = document.getElementById('card_method_cash');
            const cardQris = document.getElementById('card_method_qris');

            const dotCash = cardCash?.querySelector('.method-radio-dot');
            const dotQris = cardQris?.querySelector('.method-radio-dot');

            if (method === 'cash') {
                if (cashBox) cashBox.classList.remove('hidden');
                if (qrisBox) qrisBox.classList.add('hidden');

                if (cardCash) {
                    cardCash.className = 'payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-emerald-600 bg-emerald-50/60 cursor-pointer transition select-none';
                }
                if (dotCash) {
                    dotCash.className = 'w-3.5 h-3.5 rounded-full border-2 border-emerald-600 bg-emerald-600 method-radio-dot';
                }

                if (cardQris) {
                    cardQris.className = 'payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-zinc-200 bg-zinc-50/60 hover:bg-zinc-50 cursor-pointer transition select-none';
                }
                if (dotQris) {
                    dotQris.className = 'w-3.5 h-3.5 rounded-full border-2 border-zinc-300 method-radio-dot';
                }

                calculateCashChange();
            } else {
                if (cashBox) cashBox.classList.add('hidden');
                if (qrisBox) qrisBox.classList.remove('hidden');

                if (cardCash) {
                    cardCash.className = 'payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-zinc-200 bg-zinc-50/60 hover:bg-zinc-50 cursor-pointer transition select-none';
                }
                if (dotCash) {
                    dotCash.className = 'w-3.5 h-3.5 rounded-full border-2 border-zinc-300 method-radio-dot';
                }

                if (cardQris) {
                    cardQris.className = 'payment-method-card relative flex flex-col p-3.5 rounded-2xl border-2 border-amber-500 bg-amber-50/60 cursor-pointer transition select-none';
                }
                if (dotQris) {
                    dotQris.className = 'w-3.5 h-3.5 rounded-full border-2 border-amber-500 bg-amber-500 method-radio-dot';
                }

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
        }

        function calculatePendingChange(grandTotal) {
            const cashInput = document.getElementById('pending_cash_received');
            const changeAmount = document.getElementById('pending_change_amount');
            const changeBox = document.getElementById('pending_change_box');

            if (!cashInput) return;
            let received = parseRawNumber(cashInput.value);

            if (received >= grandTotal) {
                const change = received - grandTotal;
                if (changeBox) {
                    changeBox.className = 'p-3.5 rounded-2xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 text-xs flex items-center justify-between font-bold';
                    changeBox.firstElementChild.textContent = 'Kembalian Pelanggan:';
                }
                if (changeAmount) {
                    changeAmount.className = 'num font-black text-emerald-700 text-base';
                    changeAmount.textContent = 'Rp' + formatNumberDisplay(change);
                }
            } else {
                const deficit = grandTotal - received;
                if (changeBox) {
                    changeBox.className = 'p-3.5 rounded-2xl bg-rose-50 border-2 border-rose-400 text-rose-950 text-xs flex items-center justify-between font-bold';
                    changeBox.firstElementChild.textContent = 'Uang Kurang:';
                }
                if (changeAmount) {
                    changeAmount.className = 'num font-black text-rose-700 text-base';
                    changeAmount.textContent = '-Rp' + formatNumberDisplay(deficit);
                }
            }
        }

        // Pastikan nilai dikirim sebagai angka murni tanpa titik saat submit form
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                ['input_discount', 'input_tip', 'input_cash_received', 'pending_cash_received'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = parseRawNumber(el.value);
                    }
                });
            });
        });
    </script>
    </script>
</x-layouts.app>