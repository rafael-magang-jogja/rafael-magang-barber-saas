<x-layouts.app title="Pembayaran Langganan" subtitle="Rincian tagihan paket barbershop dan pembayaran instan online" role="owner">
    @php
        $targetPlan = $invoice->targetPlan ?? $invoice->subscription?->plan;
        $isPaid = $invoice->status === 'paid';
        $isCancelled = $invoice->status === 'cancelled';
        $isUnpaid = $invoice->status === 'unpaid';
        $type = $invoice->type ?? 'renewal';
        $hasPaymentUrl = ! empty($invoice->xendit_payment_url);
    @endphp

    <div class="w-full space-y-4 sm:space-y-6 pb-8">
        <!-- Top Navigation & Tagihan No -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:p-0 rounded-2xl bg-zinc-50/80 sm:bg-transparent border border-zinc-200/70 sm:border-0 shadow-2xs sm:shadow-none">
            <a
                href="{{ route('owner.subscription') }}"
                class="inline-flex items-center gap-2 text-xs font-bold text-zinc-600 hover:text-amber-600 transition group self-start cursor-pointer py-0.5"
            >
                <span class="w-7 h-7 rounded-lg bg-white sm:bg-zinc-100 group-hover:bg-amber-100 group-hover:text-amber-700 flex items-center justify-center transition border border-zinc-200/80 sm:border-0 shadow-2xs sm:shadow-none">
                    <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 text-zinc-600 group-hover:text-amber-700 group-hover:-translate-x-0.5 transition" />
                </span>
                <span>Kembali ke Katalog Paket</span>
            </a>

            <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto pt-2.5 sm:pt-0 border-t border-zinc-200/60 sm:border-t-0">
                <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">No. Invoice:</span>
                <span class="inline-flex items-center gap-1.5 font-mono font-black text-xs bg-white text-zinc-800 px-3 py-1.5 rounded-xl border border-zinc-200/90 shadow-2xs">
                    <x-ui.icon name="receipt" class="w-3.5 h-3.5 text-amber-500" />
                    <span class="select-all">{{ $invoice->number }}</span>
                </span>
            </div>
        </div>

        <!-- Banner Status Lunas -->
        @if($isPaid)
            <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-emerald-50 border-2 border-emerald-400 text-emerald-950 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3.5">
                <div class="flex items-start sm:items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <x-ui.icon name="check" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-black text-sm sm:text-base text-zinc-900 leading-snug">Pembayaran Terverifikasi Lunas</h3>
                        <p class="text-xs text-zinc-600 mt-0.5">
                            Tagihan telah lunas pada <strong class="text-zinc-900">{{ $invoice->paid_at?->translatedFormat('d M Y, H:i') ?? '-' }}</strong>. Paket <strong>{{ $targetPlan?->name ?? 'Barbershop' }}</strong> telah aktif!
                        </p>
                    </div>
                </div>
                <span class="text-xs font-black uppercase tracking-wider text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full border border-emerald-300 shrink-0 self-start sm:self-auto">
                    Lunas
                </span>
            </div>
        @elseif($isCancelled)
            <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-zinc-100 border-2 border-zinc-300 text-zinc-700 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start sm:items-center gap-3">
                        <span class="w-10 h-10 rounded-2xl bg-zinc-200 text-zinc-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="x" class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-black text-sm sm:text-base text-zinc-900">Tagihan Dibatalkan</h3>
                                <span class="text-[10px] font-black uppercase tracking-wider text-zinc-500 bg-zinc-200/80 px-2 py-0.5 rounded-md">
                                    {{ $invoice->cancelled_by === 'superadmin' ? 'Oleh Superadmin' : 'Oleh Owner' }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                Dibatalkan pada {{ $invoice->cancelled_at?->translatedFormat('d M Y, H:i') ?? $invoice->updated_at?->translatedFormat('d M Y, H:i') }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('owner.subscription') }}" class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 transition inline-flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
                        <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Pilih Paket Baru</span>
                    </a>
                </div>

                @if($invoice->cancellation_reason)
                    <div class="p-3 rounded-xl bg-white border border-zinc-200 text-xs space-y-1">
                        <span class="font-bold text-zinc-800 block text-[11px]">Alasan Pembatalan:</span>
                        <p class="text-zinc-600 italic leading-relaxed">"{{ $invoice->cancellation_reason }}"</p>
                    </div>
                @endif
            </div>
        @endif

        <!-- 1. Kartu Rincian Paket & Tagihan -->
        <div class="rounded-2xl sm:rounded-3xl bg-white border border-zinc-200/90 shadow-xs overflow-hidden">
            <!-- Header Card -->
            <div class="p-4 sm:p-5 border-b border-zinc-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <x-ui.icon name="receipt" class="w-4 h-4 text-amber-500" />
                    </span>
                    <h3 class="font-bold text-sm sm:text-base text-zinc-900">Rincian Paket Langganan</h3>
                </div>
                <span class="text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full border
                    @if($isPaid) bg-emerald-50 text-emerald-700 border-emerald-200
                    @elseif($isCancelled) bg-zinc-100 text-zinc-600 border-zinc-300
                    @else bg-amber-50 text-amber-800 border-amber-300 @endif
                ">
                    {{ $isPaid ? 'Lunas' : ($isCancelled ? 'Dibatalkan' : 'Menunggu Pembayaran') }}
                </span>
            </div>

            <div class="p-4 sm:p-6 space-y-4">
                <!-- Banner Paket & Nominal -->
                <div class="p-4 sm:p-5 lg:p-6 rounded-2xl bg-zinc-50/90 border border-zinc-200/80 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-2 min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-black text-lg sm:text-xl text-zinc-900 tracking-tight">{{ $targetPlan?->name ?? 'Paket Barbershop' }}</span>
                            <x-ui.plan-badge :code="$targetPlan?->code ?? 'starter'" />
                            <span class="text-xs font-bold {{ $invoice->type_text_color }}">
                                ({{ $invoice->type_label }})
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 leading-relaxed max-w-xl">
                            {{ $targetPlan?->description ?? 'Paket langganan aplikasi barbershop multi-cabang & payroll.' }}
                        </p>
                    </div>

                    <div class="p-3.5 sm:p-4 rounded-xl bg-white border border-zinc-200/80 shrink-0 flex items-center justify-between lg:flex-col lg:items-end lg:justify-center gap-2">
                        <span class="text-[11px] font-bold text-zinc-400 block uppercase tracking-wider">Total Tagihan</span>
                        <span class="text-2xl sm:text-3xl font-black text-zinc-900 num tracking-tight">
                            Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Info Masa Aktif -->
                @if($type === 'upgrade' || $type === 'downgrade')
                    <div class="p-3 rounded-xl bg-amber-50/80 border border-amber-200/80 flex items-center gap-2 text-xs text-amber-900">
                        <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0" />
                        <span class="leading-relaxed">Sisa hari paket lama tidak ditumpuk, langsung diganti 30 hari kalender paket baru.</span>
                    </div>
                @else
                    <div class="p-3 rounded-xl bg-emerald-50/80 border border-emerald-200/80 flex items-center gap-2 text-xs text-emerald-900">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span class="leading-relaxed">Masa aktif bertambah +30 hari kalender dari masa aktif saat ini.</span>
                    </div>
                @endif

                <!-- Meta Data Grid (Responsif 1 kolom di HP kecil, 2 kolom di Tablet dengan sidebar, 4 kolom di Layar Lebar) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2.5 sm:gap-3.5 p-3.5 sm:p-4.5 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 text-xs">
                    <div class="p-2.5 sm:p-3 rounded-xl bg-white border border-zinc-200/60 flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="receipt" class="w-4 h-4 text-amber-600" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] text-zinc-400 block font-bold uppercase tracking-wider">Nomor Tagihan</span>
                            <span class="font-mono font-black text-zinc-900 truncate block text-xs" title="{{ $invoice->number }}">
                                {{ $invoice->number }}
                            </span>
                        </div>
                    </div>

                    <div class="p-2.5 sm:p-3 rounded-xl bg-white border border-zinc-200/60 flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-zinc-100 text-zinc-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="calendar" class="w-4 h-4 text-zinc-600" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] text-zinc-400 block font-bold uppercase tracking-wider">Tanggal Terbit</span>
                            <span class="font-bold text-zinc-900 block text-xs">
                                {{ $invoice->created_at->translatedFormat('d M Y, H:i') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-2.5 sm:p-3 rounded-xl bg-white border border-zinc-200/60 flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="clock" class="w-4 h-4 text-rose-600" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] text-zinc-400 block font-bold uppercase tracking-wider">Batas Pembayaran</span>
                            <span class="font-bold text-rose-600 block text-xs">
                                {{ $invoice->due_at ? $invoice->due_at->translatedFormat('d M Y, H:i') : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-2.5 sm:p-3 rounded-xl bg-white border border-zinc-200/60 flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="credit-card" class="w-4 h-4 text-emerald-600" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="text-[10px] text-zinc-400 block font-bold uppercase tracking-wider">Metode Pembayaran</span>
                            <span class="font-bold text-zinc-900 block text-xs">
                                QRIS &amp; Virtual Account
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Box Pembayaran Instan Online (Tampil jika belum lunas) -->
        @if(! $isPaid && ! $isCancelled)
            <div class="p-5 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-950 text-white shadow-xl border border-zinc-800 space-y-5 sm:space-y-6">
                <!-- Header Box Bayar -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 sm:gap-5 pb-5 border-b border-zinc-800/80">
                    <div class="space-y-2 min-w-0 flex-1">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] sm:text-xs font-black uppercase tracking-wider border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Aktivasi Otomatis 24/7</span>
                        </div>
                        <h3 class="text-lg sm:text-xl lg:text-2xl font-black text-white tracking-tight">
                            Bayar Instan Tanpa Upload Struk
                        </h3>
                        <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                            Pilih QRIS (m-Banking &amp; E-Wallet apa saja) atau Virtual Account resmi. Setelah transaksi selesai, sistem langsung aktif detik itu juga.
                        </p>
                    </div>

                    <div class="p-3.5 sm:p-4.5 rounded-xl sm:rounded-2xl bg-zinc-800/90 border border-zinc-700/80 shrink-0 flex items-center justify-between lg:flex-col lg:items-end lg:justify-center gap-2">
                        <span class="text-[10px] sm:text-xs text-zinc-400 block font-bold uppercase tracking-wider">Nominal Tepat</span>
                        <span class="text-xl sm:text-2xl lg:text-3xl font-black text-amber-400 num tracking-tight">
                            Rp{{ number_format($invoice->amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Channel Pembayaran Yang Didukung -->
                <div class="space-y-2.5">
                    <span class="text-[11px] sm:text-xs font-bold text-zinc-300 uppercase tracking-wider block">
                        Pilihan Pembayaran Nasional:
                    </span>
                    <div class="grid grid-cols-1 md:grid-cols-1 xl:grid-cols-2 gap-2.5 sm:gap-3 text-xs">
                        <div class="p-3 sm:p-3.5 rounded-xl bg-zinc-800/80 border border-zinc-700/80 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                <x-ui.icon name="qr-code" class="w-4 h-4 text-emerald-400" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <span class="font-extrabold text-zinc-100 block text-xs">QRIS Instan</span>
                                <span class="text-[11px] text-zinc-400 block leading-tight">BCA, Mandiri, BRI, BNI, GoPay, OVO, ShopeePay, DANA</span>
                            </div>
                        </div>

                        <div class="p-3 sm:p-3.5 rounded-xl bg-zinc-800/80 border border-zinc-700/80 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
                                <x-ui.icon name="building-2" class="w-4 h-4 text-sky-400" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <span class="font-extrabold text-zinc-100 block text-xs">Virtual Account (VA)</span>
                                <span class="text-[11px] text-zinc-400 block leading-tight">BCA, Mandiri, BNI, BRI, Permata Bank</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi Pembayaran Utama -->
                <div class="space-y-3 pt-1">
                    @if($hasPaymentUrl)
                        <a
                            href="{{ $invoice->xendit_payment_url }}"
                            target="_blank"
                            class="w-full py-3.5 sm:py-4 px-6 rounded-xl sm:rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 hover:to-amber-300 active:scale-[0.99] text-zinc-950 font-black text-sm sm:text-base shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 transition flex items-center justify-center gap-2 cursor-pointer group"
                        >
                            <span>Buka Layar Pembayaran Sekarang</span>
                            <x-ui.icon name="external-link" class="w-4 h-4 sm:w-5 sm:h-5 text-zinc-950 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                        </a>
                    @else
                        <button
                            type="button"
                            onclick="window.location.reload()"
                            class="w-full py-3.5 sm:py-4 px-6 rounded-xl sm:rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-sm sm:text-base transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <x-ui.icon name="refresh-cw" class="w-4 h-4 sm:w-5 sm:h-5 animate-spin text-zinc-950" />
                            <span>Menyiapkan Tautan Pembayaran... Klik Muat Ulang</span>
                        </button>
                    @endif

                    <!-- Polling Status Helper -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-zinc-400 pt-1 text-center sm:text-left">
                        <span class="inline-flex items-center gap-2 text-[11px] sm:text-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping shrink-0"></span>
                            <span>Mendeteksi status pembayaran secara otomatis...</span>
                        </span>
                        <button
                            type="button"
                            onclick="window.location.reload()"
                            class="text-amber-400 hover:text-amber-300 hover:underline font-bold inline-flex items-center gap-1.5 cursor-pointer text-xs"
                        >
                            <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5 text-amber-400" />
                            <span>Cek Status Sekarang</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- 3. Rincian Konfirmasi Lunas (Jika Sudah Lunas) -->
        @if($isPaid)
            <div class="p-5 sm:p-7 rounded-2xl sm:rounded-3xl bg-white border border-zinc-200/90 shadow-xs space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-zinc-100">
                    <span class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <x-ui.icon name="shield-check" class="w-5 h-5 text-emerald-600" />
                    </span>
                    <div>
                        <h4 class="font-black text-sm sm:text-base text-zinc-900">Langganan Telah Aktif Sepenuhnya</h4>
                        <p class="text-xs text-zinc-500">Seluruh modul operasional dan kuota barbershop dapat Anda gunakan langsung.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 text-xs">
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/70">
                        <span class="text-[10px] sm:text-[11px] text-zinc-400 block font-medium">Metode Pembayaran</span>
                        <span class="font-bold text-zinc-900">Pembayaran Online Otomatis</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/70">
                        <span class="text-[10px] sm:text-[11px] text-zinc-400 block font-medium">Nomor Tagihan</span>
                        <span class="font-mono font-bold text-zinc-900 truncate block">{{ $invoice->number }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/70">
                        <span class="text-[10px] sm:text-[11px] text-zinc-400 block font-medium">Masa Aktif Berlaku S/d</span>
                        <span class="font-bold text-emerald-700">
                            {{ $invoice->period_end ? $invoice->period_end->translatedFormat('d M Y') : '-' }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <!-- 4. Panduan Aktivasi & Jaminan Keamanan (Responsif 1 kolom di HP & Tablet sidebar aktif, 3 kolom di Layar Lebar) -->
        <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-3 gap-3.5 sm:gap-4">
            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2.5 text-zinc-900 font-extrabold text-xs sm:text-sm pb-2 border-b border-zinc-100">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="zap" class="w-4 h-4 text-amber-500" />
                    </span>
                    <span>Aktivasi Seketika</span>
                </div>
                <p class="text-xs text-zinc-500 leading-relaxed">
                    Sistem terhubung langsung dengan gateway pembayaran resmi. Begitu transaksi selesai di ponsel Anda, software langsung aktif otomatis.
                </p>
            </div>

            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2.5 text-zinc-900 font-extrabold text-xs sm:text-sm pb-2 border-b border-zinc-100">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="shield-check" class="w-4 h-4 text-emerald-600" />
                    </span>
                    <span>Keamanan Terjamin</span>
                </div>
                <p class="text-xs text-zinc-500 leading-relaxed">
                    Setiap transaksi dilindungi enkripsi standar Bank Indonesia &amp; PCI-DSS perbankan resmi nasional.
                </p>
            </div>

            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 shadow-2xs space-y-2.5">
                <div class="flex items-center gap-2.5 text-zinc-900 font-extrabold text-xs sm:text-sm pb-2 border-b border-zinc-100">
                    <span class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="help-circle" class="w-4 h-4 text-sky-500" />
                    </span>
                    <span>Bantuan Support</span>
                </div>
                <p class="text-xs text-zinc-500 leading-relaxed">
                    Mengalami kendala saat bayar? Tim support platform siap membantu melalui layanan bantuan resmi.
                </p>
            </div>
        </div>

        <!-- 5. Opsi Batalkan Tagihan (Hanya jika belum lunas) -->
        @if(! $isPaid && ! $isCancelled)
            <div class="p-4 sm:p-5 rounded-2xl bg-zinc-50/90 border border-zinc-200/80 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3.5 sm:gap-4 text-left">
                <div class="flex items-start gap-3 min-w-0 flex-1">
                    <span class="w-8 h-8 rounded-xl bg-zinc-200/80 text-zinc-600 flex items-center justify-center shrink-0 mt-0.5">
                        <x-ui.icon name="alert-triangle" class="w-4 h-4 text-zinc-600" />
                    </span>
                    <div class="space-y-1 min-w-0">
                        <p class="text-xs font-black text-zinc-900 leading-snug">
                            Ingin mengganti paket atau membatalkan tagihan ini?
                        </p>
                        <p class="text-[11px] text-zinc-500 leading-relaxed">
                            Anda dapat membatalkan tagihan yang belum dibayar kapan saja untuk memilih paket lain.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-owner-cancel-invoice').showModal()"
                    class="w-full lg:w-auto px-4 py-2.5 rounded-xl bg-white hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 border border-zinc-200 text-zinc-700 font-extrabold text-xs shadow-2xs transition cursor-pointer inline-flex items-center justify-center gap-1.5 shrink-0"
                >
                    <x-ui.icon name="trash" class="w-3.5 h-3.5 text-rose-500" />
                    <span>Batalkan Tagihan</span>
                </button>
            </div>

            <!-- Modal Pembatalan Tagihan -->
            <dialog id="modal-owner-cancel-invoice" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-md mx-auto my-auto border border-zinc-200 bg-white">
                <form method="POST" action="{{ route('owner.subscription.cancel-invoice', $invoice->id) }}">
                    @csrf
                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="alert-triangle" class="w-5 h-5 text-rose-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-sm sm:text-base text-zinc-900">Batalkan Tagihan</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Tagihan {{ $invoice->number }} akan dibatalkan</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="owner-cancellation-reason" class="block text-xs font-bold text-zinc-800">
                                Alasan Pembatalan <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                name="cancellation_reason"
                                id="owner-cancellation-reason"
                                rows="3"
                                required
                                placeholder="Tuliskan alasan pembatalan tagihan ini..."
                                class="w-full text-xs rounded-xl border border-zinc-200 bg-white px-3 py-2.5 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition"
                            ></textarea>
                        </div>
                    </div>

                    <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2.5 rounded-b-3xl">
                        <button
                            type="button"
                            onclick="document.getElementById('modal-owner-cancel-invoice').close()"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
                        >
                            Kembali
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer inline-flex items-center gap-1.5"
                        >
                            <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                            <span>Ya, Batalkan Tagihan</span>
                        </button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>

    <!-- Script Polling Otomatis Saat Menunggu Pembayaran -->
    @if(! $isPaid && ! $isCancelled)
        <script>
            (function() {
                const checkUrl = '{{ route('owner.subscription.invoice', $invoice->id) }}?check_status=1';

                const intervalId = setInterval(async function() {
                    try {
                        const response = await fetch(checkUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            if (data.is_paid === true) {
                                clearInterval(intervalId);
                                window.location.reload();
                            }
                        }
                    } catch (e) {
                        // ignore network glitches during polling
                    }
                }, 4000);
            })();
        </script>
    @endif
</x-layouts.app>
