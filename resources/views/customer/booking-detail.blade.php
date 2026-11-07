<x-layouts.customer :title="'Booking ' . $booking->code" :subtitle="$booking->tenant?->name ?? 'Detail Reservasi'" :tenant="$tenant">
    @php
        $isCancelled = in_array($booking->status, [\App\Enums\BookingStatus::Cancelled, \App\Enums\BookingStatus::NoShow], true);
        $hasActiveTicket = $booking->queueTicket !== null
            && ! $isCancelled
            && ! in_array($booking->queueTicket->status, [\App\Enums\QueueStatus::Cancelled, \App\Enums\QueueStatus::NoShow], true);
        $hasTicket = $hasActiveTicket || $booking->queueTicket !== null;
        $isPaid = $hasTicket
            || $booking->xendit_paid_at !== null
            || $booking->payment_approved_at !== null
            || in_array($booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::CheckedIn, \App\Enums\BookingStatus::Completed], true);
        $todayTickets = $todayTickets ?? collect();
        $isTodayBooking = $booking->scheduled_at->isToday();
        $ticketNumber = $booking->queueTicket?->number;
    @endphp

    <div class="space-y-4">
        <!-- Info Barbershop & Outlet -->
        <div class="rounded-2xl bg-zinc-950 text-white p-4 shadow-lg border border-zinc-800">
            <div class="flex items-center justify-between gap-2">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold">
                    <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                    <span>{{ $booking->tenant?->name }}</span>
                </div>
                @if(!empty($branch?->maps_url))
                    <a
                        href="{{ $branch->maps_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-sky-300 hover:text-sky-200 font-bold inline-flex items-center gap-1 bg-sky-500/15 hover:bg-sky-500/25 border border-sky-400/30 px-2.5 py-1 rounded-xl transition"
                    >
                        <span>Google Maps</span>
                        <x-ui.icon name="external-link" class="w-3 h-3 text-sky-300" />
                    </a>
                @endif
            </div>

            <p class="text-xs text-zinc-300 font-semibold mt-2.5">
                {{ $branch?->name }} · {{ $branch?->address }}
            </p>
        </div>

        @if(! $isCancelled)
            <!-- Livewire Real-time Auto-Sync Poller (5 Detik) -->
            <livewire:customer-booking-tracker :booking-id="$booking->id" />
        @endif

        <!-- KARTU STATUS BOOKING -->
        <div class="rounded-3xl bg-white shadow-xl p-5 border border-zinc-100 text-center space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-zinc-400 tracking-wider uppercase">Kode Reservasi Booking</span>
                @if($isCancelled)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-rose-100 text-rose-800 border border-rose-300">
                        {{ $booking->status->label() }}
                    </span>
                @elseif($hasActiveTicket)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        Masuk Antrean ({{ $booking->queueTicket->number }})
                    </span>
                @elseif($isPaid)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        Lunas via QRIS
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-500/15 text-amber-800">
                        Menunggu Pembayaran
                    </span>
                @endif
            </div>

            <div>
                <p class="text-3xl font-black text-zinc-950 num tracking-tight my-1">{{ $booking->code }}</p>
                <p class="text-xs text-zinc-500">
                    Jadwal Kedatangan: <b class="text-zinc-900">{{ $booking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</b>
                </p>
            </div>

            @if($booking->queueTicket)
                <!-- Tampilan Utama Nomor Antrean Booking Resmi -->
                <div class="pt-1 pb-2">
                    <div class="inline-flex flex-col items-center justify-center py-2.5 px-6 rounded-2xl bg-zinc-950 text-white border-2 border-amber-500 shadow-md">
                        <span class="text-[10px] font-bold text-amber-400 uppercase tracking-widest flex items-center gap-1">
                            <x-ui.icon name="ticket" class="w-3 h-3 text-amber-400" />
                            <span>Nomor Antrean Anda</span>
                        </span>
                        <span class="text-3xl sm:text-4xl font-black text-amber-400 num tracking-tight my-0.5">
                            {{ $booking->queueTicket->number }}
                        </span>
                        <span class="text-[10px] text-zinc-400 font-medium">Jadwal: {{ $booking->scheduled_at->format('H:i') }} WIB</span>
                    </div>
                </div>
            @endif

            @if($booking->status === \App\Enums\BookingStatus::Cancelled)
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-left space-y-2">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-rose-800">
                        <x-ui.icon name="x" class="w-4 h-4 text-rose-600 shrink-0" />
                        <span>Booking Dibatalkan</span>
                    </div>
                    @if(!empty($booking->cancel_reason))
                        <div class="p-2.5 rounded-xl bg-white/80 border border-rose-200/60 text-xs font-semibold text-rose-900 leading-relaxed">
                            Alasan: {{ $booking->cancel_reason }}
                        </div>
                    @else
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Booking ini telah dibatalkan.
                        </p>
                    @endif

                    @if(!empty($booking->payment_proof_path))
                        <div class="p-2.5 rounded-xl bg-white/80 border border-rose-200/60 flex items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-ui.icon name="file-text" class="w-4 h-4 text-zinc-400 shrink-0" />
                                <span class="text-zinc-600 truncate">Bukti transfer yang pernah diunggah</span>
                            </div>
                            <button
                                type="button"
                                onclick="openCustomerProofModal()"
                                class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-[11px] font-bold transition cursor-pointer shrink-0 inline-flex items-center gap-1"
                            >
                                <x-ui.icon name="eye" class="w-3.5 h-3.5 text-zinc-600" />
                                <span>Lihat Struk</span>
                            </button>
                        </div>
                    @endif

                    @if($booking->refund_status === 'pending')
                        @php
                            $refundBank = $booking->refund_bank_name ?: ($booking->queueTicket?->refund_bank_name ?: null);
                            $refundAccNum = $booking->refund_account_number ?: ($booking->queueTicket?->refund_account_number ?: null);
                            $refundAccName = $booking->refund_account_name ?: ($booking->queueTicket?->refund_account_name ?: null);
                        @endphp
                        <!-- Kartu Pengembalian Dana (Status: Pending Transfer) -->
                        <div class="mt-3 rounded-2xl bg-white border border-amber-300 p-4 space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between gap-2 border-b border-amber-100 pb-2.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500 text-zinc-950 shadow-2xs">
                                    <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                                    <span>Menunggu Transfer</span>
                                </span>
                                <span class="text-base sm:text-lg font-black text-amber-600 num">
                                    Rp{{ number_format($booking->refund_amount ?? $booking->estimated_total, 0, ',', '.') }}
                                </span>
                            </div>

                            <p class="text-xs text-zinc-600 leading-relaxed font-medium">
                                Pengembalian dana pembayaran booking sedang dalam antrean proses transfer oleh kasir atau owner barbershop.
                            </p>

                            @if($refundBank)
                                <div class="rounded-xl bg-amber-50/60 border border-amber-200/80 p-3 space-y-2 text-xs">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Rekening Tujuan Pengembalian:</span>
                                    <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white border border-amber-200/70">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-zinc-900 text-white">{{ $refundBank }}</span>
                                                <span class="font-mono font-bold text-zinc-900 text-xs">{{ $refundAccNum }}</span>
                                            </div>
                                            <p class="text-[11px] text-zinc-500 font-medium mt-0.5">
                                                a.n. <b class="text-zinc-800">{{ $refundAccName }}</b>
                                            </p>
                                        </div>
                                        <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @elseif($booking->refund_status === 'completed')
                        @php
                            $refundBank = $booking->refund_bank_name ?: ($booking->queueTicket?->refund_bank_name ?: null);
                            $refundAccNum = $booking->refund_account_number ?: ($booking->queueTicket?->refund_account_number ?: null);
                            $refundAccName = $booking->refund_account_name ?: ($booking->queueTicket?->refund_account_name ?: null);
                        @endphp
                        <!-- Kartu Pengembalian Dana (Status: Selesai Ditransfer) -->
                        <div class="mt-3 rounded-2xl bg-white border border-emerald-300 p-4 space-y-3 shadow-sm">
                            <div class="flex items-center justify-between gap-2 border-b border-emerald-100 pb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white shadow-2xs">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 stroke-[3]" />
                                    <span>Pengembalian Dana Selesai</span>
                                </span>
                                <span class="text-base sm:text-lg font-black text-emerald-600 num">
                                    Rp{{ number_format($booking->refund_amount ?? $booking->estimated_total, 0, ',', '.') }}
                                </span>
                            </div>

                            <p class="text-xs text-zinc-600 leading-relaxed font-medium">
                                @if($refundBank)
                                    Dana pembayaran booking telah dikonfirmasi dan ditransfer ke rekening Anda oleh kasir/owner.
                                @else
                                    Uang tunai telah dikembalikan langsung di meja kasir outlet.
                                @endif
                            </p>

                            <div class="rounded-xl bg-zinc-50/90 border border-zinc-200/80 p-3 space-y-2.5 text-xs">
                                @if($refundBank)
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Rekening Tujuan:</span>
                                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white border border-zinc-200/70">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-zinc-900 text-white">{{ $refundBank }}</span>
                                                    <span class="font-mono font-bold text-zinc-900 text-xs">{{ $refundAccNum }}</span>
                                                </div>
                                                <p class="text-[11px] text-zinc-500 font-medium mt-0.5">
                                                    a.n. <b class="text-zinc-800">{{ $refundAccName }}</b>
                                                </p>
                                            </div>
                                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                                <x-ui.icon name="check" class="w-3.5 h-3.5 stroke-[2.5]" />
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                @if($booking->refunded_at)
                                    <div class="flex items-center justify-between text-zinc-500 text-xs pt-1 border-t border-zinc-200/60">
                                        <span class="text-[11px]">Waktu Selesai:</span>
                                        <span class="font-bold text-zinc-900 num text-[11px]">
                                            {{ \Carbon\Carbon::parse($booking->refunded_at)->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    </div>
                                @endif

                                @if(!empty($booking->refund_notes))
                                    <div class="pt-1 border-t border-zinc-200/60">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Catatan:</span>
                                        <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-200/60 text-[11px] text-emerald-950 font-medium leading-relaxed">
                                            {{ $booking->refund_notes }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($isCancelled && $booking->refund_amount > 0)
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-emerald-900">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>Pengembalian Dana Berhasil (Refund)</span>
                    </div>
                    <p class="text-xs text-emerald-800 leading-relaxed">
                        Dana reservasi booking sebesar <b class="text-emerald-950 num">Rp{{ number_format($booking->refund_amount, 0, ',', '.') }}</b> telah otomatis masuk ke <b>Saldo Dompet Akun Anda</b> dan siap digunakan kembali untuk booking atau ditarik tunai tanpa potongan.
                    </p>
                </div>
            @elseif($hasActiveTicket || $booking->queueTicket)
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-extrabold text-emerald-950">Antrean Terdaftar di Sistem</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-extrabold text-xs num">
                            {{ $booking->queueTicket->number }}
                        </span>
                    </div>
                    <p class="text-xs text-emerald-800 leading-relaxed">
                        Pembayaran terkonfirmasi otomatis! Antrean resmi Anda (<b>{{ $booking->queueTicket->number }}</b>) akan aktif di monitor antrean kasir tepat <b>15 menit sebelum jam jadwal Anda</b> ({{ $booking->scheduled_at->format('H:i') }} WIB).
                    </p>
                </div>
            @elseif($isPaid)
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-emerald-900">
                        <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>Pembayaran Lunas via QRIS</span>
                    </div>
                    <p class="text-xs text-emerald-800 leading-relaxed">
                        Pembayaran QRIS telah berhasil diverifikasi otomatis oleh sistem. Booking Anda telah aktif dan siap dilayani sesuai jadwal.
                    </p>
                </div>
            @endif
        </div>

        <!-- KARTU DAFTAR ANTREAN HARI INI DI CABANG (Sebelum Membayar / Pantau Antrean Hari Ini) -->
        @if($isTodayBooking && ! $isCancelled)
            <div class="rounded-3xl bg-white border border-zinc-200/90 shadow-sm p-4 sm:p-5 space-y-3.5">
                <div class="flex items-center justify-between gap-2 border-b border-zinc-100 pb-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-9 h-9 rounded-2xl bg-amber-500/15 text-amber-600 grid place-items-center shrink-0">
                            <x-ui.icon name="ticket" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-extrabold text-sm text-zinc-900 truncate">Daftar Antrean Hari Ini</h2>
                            <p class="text-[11px] text-zinc-500 truncate">{{ $branch?->name }} · {{ now()->translatedFormat('l, d M Y') }}</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-700 text-xs font-black num shrink-0">
                        {{ $todayTickets->count() }} Antrean Aktif
                    </span>
                </div>

                @if($todayTickets->isEmpty())
                    <div class="py-3 px-3.5 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs flex items-center gap-2.5">
                        <x-ui.icon name="sparkles" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>Waktu kunjungan yang ideal! Barber kami siap menyambut kedatangan Anda dengan pelayanan prima tanpa perlu menunggu lama.</span>
                    </div>
                @else
                    <div class="space-y-2">
                        <p class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Antrean Sedang Berjalan di Outlet:</p>
                        <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1 divide-y divide-zinc-100">
                            @foreach($todayTickets as $t)
                                @php
                                    $isCurrentBookingTicket = ($ticketNumber && $t->number === $ticketNumber);
                                    $statusBadge = match($t->status->value) {
                                        'in_service' => ['bg' => 'bg-amber-100 text-amber-900 border-amber-300', 'label' => 'Sedang Dicukur', 'dot' => 'bg-amber-500'],
                                        'called' => ['bg' => 'bg-sky-100 text-sky-900 border-sky-300', 'label' => 'Dipanggil', 'dot' => 'bg-sky-500'],
                                        default => ['bg' => 'bg-zinc-100 text-zinc-700 border-zinc-200', 'label' => 'Menunggu', 'dot' => 'bg-zinc-400'],
                                    };
                                @endphp
                                <div class="pt-2 first:pt-0 flex items-center justify-between gap-2.5 text-xs">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="px-2.5 py-1 rounded-xl {{ $isCurrentBookingTicket ? 'bg-amber-500 text-zinc-950 font-black ring-2 ring-amber-400' : 'bg-zinc-950 text-amber-400 font-extrabold' }} text-xs num shrink-0">
                                            {{ $t->number }}
                                        </span>
                                        <div class="min-w-0 leading-tight">
                                            <p class="font-extrabold text-zinc-900 truncate flex items-center gap-1.5">
                                                <span>{{ $t->customerProfile?->name ? \Illuminate\Support\Str::limit($t->customerProfile->name, 18) : 'Pelanggan Walk-in' }}</span>
                                                @if($isCurrentBookingTicket)
                                                    <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-black uppercase">Anda</span>
                                                @endif
                                            </p>
                                            <p class="text-[10px] text-zinc-400 truncate mt-0.5">
                                                {{ $t->items->pluck('service_name')->join(', ') }} · {{ $t->barber?->display_name ?? 'Otomatis' }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $statusBadge['bg'] }} shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                        <span>{{ $statusBadge['label'] }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if(! $isCancelled)
            <!-- KARTU PEMBAYARAN RESERVASI BOOKING VIA QRIS -->
            <div class="rounded-3xl bg-white border {{ $isPaid ? 'border-emerald-300' : 'border-zinc-200' }} shadow-sm p-4 sm:p-5 space-y-4">
                <!-- Header Kartu Pembayaran (Longgar & Berjarak) -->
                <div class="space-y-3.5 border-b border-zinc-100 pb-4">
                    <!-- Baris Atas: Identitas Metode & Status Badge -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-xl {{ $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-950 text-amber-400' }} grid place-items-center shrink-0 shadow-2xs">
                                <x-ui.icon name="qr-code" class="w-4 h-4" />
                            </span>
                            <div class="min-w-0 leading-tight">
                                <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400 block">Metode Resmi</span>
                                <span class="font-black text-sm text-zinc-900 tracking-tight">QRIS &amp; E-Wallet</span>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if($isPaid)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                    <span>Lunas via QRIS / E-Wallet</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-50 text-amber-900 border border-amber-300/90 shadow-2xs">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Menunggu Pembayaran</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Keterangan Judul yang Lega -->
                    <div class="pt-0.5">
                        <h2 class="font-black text-base text-zinc-950 tracking-tight">Pembayaran Reservasi</h2>
                        <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                            Bayar instan via QRIS (semua m-Banking / E-Wallet) atau langsung melalui akun E-Wallet (DANA, OVO, ShopeePay, GoPay, LinkAja). Antrean booking akan otomatis diterbitkan seketika setelah pembayaran berhasil.
                        </p>
                    </div>
                </div>

                <!-- Total Tagihan -->
                <div class="p-4 rounded-2xl bg-zinc-950 text-white flex items-center justify-between shadow-xs">
                    <div>
                        <span class="text-[11px] text-zinc-400 font-medium block">
                            {{ $isPaid ? 'Total Pembayaran Reservasi' : 'Total Tagihan Reservasi' }}
                        </span>
                        @if($isPaid)
                            <span class="text-xs text-emerald-400 font-bold flex items-center gap-1">
                                <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-400" />
                                <span>Lunas ({{ $booking->items->count() }} Layanan)</span>
                            </span>
                        @else
                            <span class="text-xs text-amber-400 font-bold num">{{ $booking->items->count() }} Layanan Reservasi</span>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-black {{ $isPaid ? 'text-white' : 'text-amber-400' }} num tracking-tight">
                            Rp{{ number_format($totalPrice, 0, ',', '.') }}
                        </span>
                        <span class="text-[10px] {{ $isPaid ? 'text-emerald-400 font-semibold' : 'text-zinc-400 font-medium' }} block">
                            {{ $isPaid ? 'Telah Terbayar Lunas' : 'QRIS & E-Wallet Resmi' }}
                        </span>
                    </div>
                </div>

                @if($isPaid)
                    <!-- STATUS SUDAH LUNAS & TERKONFIRMASI -->
                    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-950 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-2xl bg-emerald-600 text-white grid place-items-center shrink-0 mt-0.5 shadow-xs">
                                <x-ui.icon name="check" class="w-5 h-5 text-white stroke-[2.5]" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-extrabold text-sm text-emerald-900 leading-tight">Pembayaran Berhasil &amp; Terkonfirmasi!</h3>
                                <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                                    Reservasi Anda telah aktif dan terjadwal rapi. Antrean resmi Anda akan otomatis muncul di sistem kasir tepat <b>15 menit sebelum jam jadwal Anda</b> ({{ $booking->scheduled_at->format('H:i') }} WIB) tanpa mengganggu antrean jam lainnya.
                                </p>
                            </div>
                        </div>

                        @if($booking->queueTicket)
                            <div class="pt-1">
                                <a
                                    href="{{ route('customer.tickets.show', $booking->queueTicket->id) }}"
                                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition shadow-sm"
                                >
                                    <x-ui.icon name="ticket" class="w-4 h-4" />
                                    <span>Buka Tiket Antrean</span>
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <!-- KONTEN PEMBAYARAN QRIS TERSTRUKTUR -->
                    <div class="space-y-4">
                        <!-- Batas Waktu Pembayaran (Jika Ada) -->
                        @if($booking->xendit_expired_at)
                            <div class="flex items-center justify-between text-xs py-2.5 px-3.5 rounded-xl bg-amber-50/80 border border-amber-200/90 text-amber-950">
                                <span class="flex items-center gap-1.5 font-bold text-amber-900 text-xs">
                                    <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                    <span>Batas Waktu Pembayaran:</span>
                                </span>
                                <span class="font-mono font-black text-amber-950 num text-xs">
                                    {{ $booking->xendit_expired_at->translatedFormat('d M Y · H:i') }} WIB
                                </span>
                            </div>
                        @endif

                        <!-- Panduan Pembayaran & Metode E-Wallet / Bank (Ditempatkan Sebelum Tombol Aksi) -->
                        <div class="rounded-2xl bg-zinc-50 border border-zinc-200/80 p-3.5 space-y-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-700 font-extrabold text-xs flex items-center gap-1.5">
                                    <x-ui.icon name="shield-check" class="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span>Mendukung QRIS &amp; E-Wallet:</span>
                                </span>
                                <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md shrink-0">
                                    Otomatis
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">QRIS (Semua Bank)</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">GoPay</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">OVO</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">DANA</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">ShopeePay</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">LinkAja</span>
                                <span class="px-2 py-0.5 rounded-md bg-white border border-zinc-200 text-zinc-800 font-bold text-[10px] shadow-2xs">AstraPay</span>
                            </div>
                            <p class="text-[11px] text-zinc-500 leading-relaxed pt-1.5 border-t border-zinc-200/60">
                                Setelah pembayaran berhasil diverifikasi melalui scan QRIS atau E-Wallet, antrean Anda langsung aktif seketika tanpa perlu mengirim struk transfer.
                            </p>
                        </div>

                        <!-- Opsi Bayar Langsung dengan Saldo Dompet (Jika Saldo Mencukupi) -->
                        @if(auth()->check() && auth()->user()->wallet_balance >= $booking->estimated_total)
                            <div class="p-4 rounded-2xl bg-zinc-950 text-white border border-amber-500/50 shadow-md space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-500 text-zinc-950 grid place-items-center font-black shrink-0">
                                            <x-ui.icon name="wallet" class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-xs text-amber-400">Bayar Pakai Saldo Dompet</h4>
                                            <p class="text-[10px] text-zinc-400">Saldo akun Anda mencukupi</p>
                                        </div>
                                    </div>
                                    <span class="font-black text-xs text-white num">Saldo: Rp{{ number_format(auth()->user()->wallet_balance, 0, ',', '.') }}</span>
                                </div>
                                <form method="POST" action="{{ route('customer.bookings.pay-wallet', $booking) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-2"
                                    >
                                        <x-ui.icon name="check-circle" class="w-4 h-4" />
                                        <span>Bayar Sekarang (Rp{{ number_format($booking->estimated_total, 0, ',', '.') }})</span>
                                    </button>
                                </form>
                            </div>
                            <div class="flex items-center gap-3 text-zinc-400 text-[11px] font-bold">
                                <span class="h-px bg-zinc-200 flex-1"></span>
                                <span>ATAU VIA SCAN QRIS</span>
                                <span class="h-px bg-zinc-200 flex-1"></span>
                            </div>
                        @endif

                        <!-- Tombol Aksi Utama di Bagian Bawah Kartu -->
                        @php
                            $qrisUrl = ! empty($booking->xendit_payment_url)
                                ? $booking->xendit_payment_url
                                : route('customer.bookings.pay-qris', $booking);
                        @endphp

                        <div class="pt-2 space-y-2.5">
                            <a
                                href="{{ $qrisUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full min-h-[50px] px-5 py-3.5 rounded-2xl bg-zinc-950 hover:bg-zinc-800 active:scale-[0.99] text-amber-400 hover:text-amber-300 font-black text-xs sm:text-sm flex items-center justify-center gap-2.5 shadow-md hover:shadow-lg transition cursor-pointer"
                            >
                                <x-ui.icon name="qr-code" class="w-4 h-4 text-amber-400 shrink-0" />
                                <span>Buka Pembayaran QRIS / E-Wallet</span>
                                <x-ui.icon name="arrow-right" class="w-4 h-4 text-amber-400 shrink-0" />
                            </a>

                            <!-- Tombol Cek Status -->
                            <button
                                type="button"
                                onclick="window.location.reload()"
                                class="w-full py-2.5 px-4 rounded-xl bg-zinc-50 hover:bg-zinc-100 border border-zinc-200 text-zinc-600 hover:text-zinc-900 font-bold text-xs flex items-center justify-center gap-2 transition cursor-pointer"
                            >
                                <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5 text-zinc-400" />
                                <span>Sudah Melakukan Pembayaran? Perbarui Status</span>
                            </button>
                        </div>

                        @if(! $isPaid && (request()->has('mock_qris') || ! app(\App\Services\XenditPaymentService::class)->isConfigured()))
                            <!-- TOMBOL SIMULASI LOKAL (JIKA API KEY XENDIT BELUM DIISI) DI PALING BAWAH -->
                            <div class="pt-3 border-t border-dashed border-zinc-200">
                                <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-1.5 font-black text-amber-900">
                                            <x-ui.icon name="zap" class="w-4 h-4 text-amber-600" />
                                            <span>Mode Uji Coba Lokal (Simulasi)</span>
                                        </div>
                                        <span class="text-[9px] font-black text-amber-800 bg-amber-200/80 px-1.5 py-0.5 rounded uppercase">Dev Mode</span>
                                    </div>
                                    <p class="text-[11px] text-amber-800 leading-relaxed">
                                        Kunci API Xendit belum diisi di <code>.env</code>. Klik tombol di bawah untuk menyimulasikan pembayaran QRIS sukses secara instan di localhost:
                                    </p>
                                    <form method="POST" action="{{ route('customer.bookings.mock-pay', $booking) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full py-2.5 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                                        >
                                            <x-ui.icon name="check-circle" class="w-4 h-4 text-zinc-950" />
                                            <span>Simulasikan Bayar QRIS Sukses</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- KARTU INFORMASI STAFF / BARBER PILIHAN -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-2">
                    <x-ui.icon name="users" class="w-4 h-4 text-amber-600" />
                    <span>Barber Pilihan</span>
                </h2>
            </x-slot:header>

            @if($barber)
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-zinc-950 text-amber-400 font-extrabold text-base grid place-items-center shrink-0 shadow-sm">
                        {{ strtoupper(substr($barber->display_name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-1">
                            <h3 class="font-extrabold text-sm text-zinc-900 truncate">{{ $barber->display_name }}</h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] font-extrabold shrink-0 border border-amber-200">
                                <x-ui.icon name="star" class="w-3 h-3 text-amber-500" />
                                <span class="num">{{ number_format($barber->rating_avg, 1) }}</span>
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5 truncate">
                            {{ $barber->specialty ?? 'Barber Profesional' }}
                        </p>
                    </div>
                </div>
            @else
                <div class="p-3 rounded-2xl bg-zinc-50 border border-dashed border-zinc-200 text-center">
                    <p class="font-extrabold text-xs text-zinc-800">Siapa Saja (Tercepat di Hari H)</p>
                    <p class="text-[11px] text-zinc-400 mt-0.5">
                        Barber yang sedang siap di outlet saat Anda tiba akan langsung melayani Anda.
                    </p>
                </div>
            @endif
        </x-ui.card>

        <!-- KARTU RINCIAN LAYANAN & HARGA -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-2">
                    <x-ui.icon name="scissors" class="w-4 h-4 text-amber-600" />
                    <span>Layanan Dipesan &amp; Total Biaya</span>
                </h2>
            </x-slot:header>

            <div class="space-y-3">
                <div class="divide-y divide-zinc-100 text-xs">
                    @foreach($booking->items as $item)
                        <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-bold text-zinc-900 truncate">{{ $item->service_name }}</p>
                            </div>
                            <span class="font-extrabold text-zinc-900 num shrink-0">
                                Rp{{ number_format($item->price, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                @if($booking->hairColor)
                    <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 flex items-center justify-between gap-2 text-xs">
                        <span class="text-zinc-600 font-semibold">Warna Coloring:</span>
                        <span class="inline-flex items-center gap-1.5 font-extrabold text-zinc-900">
                            <span class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0" @style(['background-color: ' . $booking->hairColor->hex_code])></span>
                            <span>{{ $booking->hairColor->name }}</span>
                        </span>
                    </div>
                @endif

                @if(!empty($booking->notes))
                    <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 text-xs">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Catatan Khusus:</span>
                        <p class="text-zinc-800 font-medium mt-0.5">{{ $booking->notes }}</p>
                    </div>
                @endif

                <div class="pt-3 border-t border-zinc-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-zinc-500 font-medium">Total Biaya</span>
                        <p class="text-xs text-zinc-400 num">{{ $booking->items->count() }} Layanan</p>
                    </div>
                    <span class="text-lg font-black text-zinc-950 num">
                        Rp{{ number_format($totalPrice, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </x-ui.card>

        @php
            $canCancelBooking = in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Confirmed], true)
                || ($booking->status === \App\Enums\BookingStatus::CheckedIn
                    && $booking->queueTicket
                    && in_array($booking->queueTicket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true));
        @endphp

        <!-- TOMBOL NAVIGASI & AKSI -->
        <div class="pt-2 space-y-2">
            @if(auth()->check())
                <a
                    href="{{ route('customer.history') }}"
                    class="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-zinc-950 text-white text-xs font-bold flex items-center justify-center gap-2 hover:bg-zinc-800 transition"
                >
                    <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-amber-400" />
                    <span>Lihat Riwayat &amp; Jadwal Booking</span>
                </a>
            @endif

            <a
                href="{{ auth()->check() ? route('customer.dashboard') : ($booking->tenant ? $booking->tenant->customerPortalUrl() : url('/')) }}"
                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-zinc-200 text-zinc-700 bg-white hover:bg-zinc-50 text-xs font-bold flex items-center justify-center gap-2 transition"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>{{ auth()->check() ? 'Kembali ke Beranda' : 'Kembali ke Beranda ' . ($booking->tenant?->name ?? 'Barbershop') }}</span>
            </a>

            @if($canCancelBooking)
                <button
                    type="button"
                    data-dialog-id="modal-cancel-booking"
                    onclick="document.getElementById(this.dataset.dialogId).showModal()"
                    class="w-full min-h-[44px] py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-[0.99] text-white font-extrabold text-xs flex items-center justify-center transition shadow-xs cursor-pointer"
                >
                    Batalkan Reservasi Booking
                </button>
            @endif
        </div>
    </div>

    @if($canCancelBooking)
        <!-- Modal Konfirmasi Pembatalan Booking -->
        <dialog
            id="modal-cancel-booking"
            onclick="if(event.target===this)this.close()"
            class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
        >
            <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}" class="p-5 space-y-4">
                @csrf
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 grid place-items-center shrink-0">
                        <x-ui.icon name="alert-triangle" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-extrabold text-sm text-zinc-950">Batalkan Reservasi Booking?</h3>
                        <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                            Apakah Anda yakin ingin membatalkan reservasi <b class="text-zinc-900 num">{{ $booking->code }}</b> untuk jadwal <b class="text-zinc-900">{{ $booking->scheduled_at->translatedFormat('d M Y, H:i') }} WIB</b>?
                        </p>
                    </div>
                </div>

                @php
                    $isPaidBooking = $booking->xendit_paid_at !== null
                        || $booking->payment_approved_at !== null
                        || in_array($booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::CheckedIn, \App\Enums\BookingStatus::Completed], true)
                        || (! empty($booking->payment_proof_path) && in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Confirmed], true));
                @endphp

                @if($isPaidBooking)
                    <div class="flex items-start gap-2.5 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left">
                        <x-ui.icon name="wallet" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="space-y-0.5 min-w-0">
                            <h4 class="font-bold text-xs text-emerald-950">Pengembalian Dana ke Saldo Dompet</h4>
                            <p class="text-[11px] text-emerald-800 leading-relaxed">
                                Dana <strong class="num font-black text-emerald-950">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</strong> otomatis masuk ke Saldo Dompet Anda dan siap dipakai booking lagi atau dicairkan.
                            </p>
                        </div>
                    </div>
                @endif

                <div>
                    <label for="cancel-booking-reason" class="block text-[11px] font-bold text-zinc-600 mb-1">
                        Alasan Pembatalan <span class="text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        id="cancel-booking-reason"
                        type="text"
                        name="reason"
                        maxlength="255"
                        placeholder="Contoh: Ada keperluan mendadak..."
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-900 placeholder:text-zinc-400 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button
                        type="button"
                        data-dialog-id="modal-cancel-booking"
                        onclick="document.getElementById(this.dataset.dialogId).close()"
                        class="min-h-[40px] px-3 py-2 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-bold text-xs transition cursor-pointer"
                    >
                        Tidak Jadi
                    </button>
                    <button
                        type="submit"
                        class="min-h-[40px] px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-sm transition cursor-pointer"
                    >
                        Ya, Batalkan
                    </button>
                </div>
            </form>
        </dialog>
    @endif

    @if(!empty($booking->payment_proof_path))
        <!-- Dialog Modal Preview Bukti Transfer Pelanggan (Dengan Zoom, Pan & Rotate) -->
        <dialog
            id="modal-proof-customer"
            onclick="if(event.target===this)this.close()"
            class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/75 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-2xl mx-auto my-auto border border-zinc-200 bg-white outline-none focus:outline-none ring-0 overflow-hidden"
        >
            <div class="p-4 sm:p-5 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-sm sm:text-base text-zinc-900">Bukti Transfer Pembayaran</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Booking <span class="font-mono font-bold text-zinc-800">{{ $booking->code }}</span> &middot; Diunggah {{ $booking->payment_proof_uploaded_at?->diffForHumans() }}
                    </p>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-proof-customer').close()"
                    class="p-2 rounded-xl text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer"
                    title="Tutup Preview"
                >
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <div class="p-4 sm:p-5 space-y-3.5">
                <!-- Toolbar Kontrol Zoom, Rotate & Reset -->
                <div class="flex items-center justify-between text-xs text-zinc-500 gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-bold text-zinc-400 hidden sm:inline">Zoom:</span>
                        <div class="inline-flex items-center gap-1 bg-zinc-100 p-1 rounded-xl border border-zinc-200">
                            <button
                                type="button"
                                onclick="zoomCustomerProof(-0.25)"
                                class="w-7 h-7 rounded-lg bg-white hover:bg-zinc-200 active:scale-95 text-zinc-700 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                title="Perkecil (Zoom Out)"
                            >
                                <x-ui.icon name="zoom-out" class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                onclick="resetCustomerProofZoom()"
                                id="customer-proof-zoom-label"
                                class="px-2 text-[11px] font-mono font-black text-zinc-800 min-w-[48px] text-center select-none hover:text-amber-600 transition cursor-pointer"
                                title="Klik untuk reset zoom ke 100%"
                            >
                                100%
                            </button>
                            <button
                                type="button"
                                onclick="zoomCustomerProof(0.25)"
                                class="w-7 h-7 rounded-lg bg-white hover:bg-zinc-200 active:scale-95 text-zinc-700 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                title="Perbesar (Zoom In)"
                            >
                                <x-ui.icon name="zoom-in" class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                onclick="rotateCustomerProof()"
                                class="w-7 h-7 rounded-lg bg-white hover:bg-zinc-200 active:scale-95 text-zinc-700 flex items-center justify-center transition shadow-2xs cursor-pointer ml-0.5"
                                title="Putar 90 Derajat"
                            >
                                <svg class="w-3.5 h-3.5 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            <button
                                type="button"
                                onclick="resetCustomerProofZoom()"
                                class="px-2 py-1 rounded-lg bg-white hover:bg-zinc-200 text-zinc-700 text-[10px] font-bold transition shadow-2xs cursor-pointer ml-0.5"
                                title="Reset Ukuran (100%)"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-zinc-400 italic">Scroll / geser gambar untuk perbesar</span>
                    </div>
                </div>

                <!-- Viewport Gambar Bukti Transfer (Dengan Zoom & Drag Pan) -->
                <div
                    id="customer-proof-viewport"
                    class="bg-zinc-950 rounded-2xl h-[48vh] sm:h-[54vh] flex items-center justify-center overflow-hidden relative select-none border border-zinc-800 cursor-default"
                    title="Klik 2x untuk toggle zoom 2x / 1x"
                >
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::url($booking->payment_proof_path) }}"
                        alt="Bukti Transfer {{ $booking->code }}"
                        id="customer-proof-img"
                        class="max-h-full max-w-full rounded-lg object-contain pointer-events-none transition-transform duration-75 shadow-md"
                        style="transform-origin: center center;"
                    />
                </div>

                <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200 text-xs text-zinc-600 flex items-center justify-between">
                    <span>Total Tagihan:</span>
                    <strong class="font-black text-sm text-emerald-600">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</strong>
                </div>
            </div>

            <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end rounded-b-3xl">
                <button
                    type="button"
                    onclick="document.getElementById('modal-proof-customer').close()"
                    class="px-5 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs transition cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </dialog>
    @endif

    <!-- Script Interaktif Zoom, Pan & Rotate Bukti Transfer Pelanggan -->
    <script>
        let customerProofZoom = 1;
        let customerProofPanX = 0;
        let customerProofPanY = 0;
        let customerProofRotation = 0;
        let isCustomerProofPanning = false;
        let customerProofStartX = 0;
        let customerProofStartY = 0;

        function updateCustomerProofTransform() {
            const img = document.getElementById('customer-proof-img');
            const label = document.getElementById('customer-proof-zoom-label');
            const viewport = document.getElementById('customer-proof-viewport');
            if (!img) return;

            img.style.transform = `translate(${customerProofPanX}px, ${customerProofPanY}px) scale(${customerProofZoom}) rotate(${customerProofRotation}deg)`;
            if (label) label.textContent = `${Math.round(customerProofZoom * 100)}%`;

            if (viewport) {
                if (customerProofZoom > 1.05) {
                    viewport.style.cursor = isCustomerProofPanning ? 'grabbing' : 'grab';
                } else {
                    viewport.style.cursor = 'default';
                }
            }
        }

        function zoomCustomerProof(delta) {
            const newZoom = Math.min(Math.max(customerProofZoom + delta, 0.5), 5.0);
            customerProofZoom = Math.round(newZoom * 100) / 100;
            if (customerProofZoom <= 1.05) {
                customerProofPanX = 0;
                customerProofPanY = 0;
            }
            updateCustomerProofTransform();
        }

        function rotateCustomerProof() {
            customerProofRotation = (customerProofRotation + 90) % 360;
            updateCustomerProofTransform();
        }

        function resetCustomerProofZoom() {
            customerProofZoom = 1;
            customerProofPanX = 0;
            customerProofPanY = 0;
            customerProofRotation = 0;
            updateCustomerProofTransform();
        }

        function openCustomerProofModal() {
            resetCustomerProofZoom();
            const modal = document.getElementById('modal-proof-customer');
            if (modal) modal.showModal();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const viewport = document.getElementById('customer-proof-viewport');
            const proofModal = document.getElementById('modal-proof-customer');

            if (proofModal) {
                proofModal.addEventListener('close', function() {
                    resetCustomerProofZoom();
                });
            }

            if (viewport) {
                // Mouse drag pan
                viewport.addEventListener('mousedown', function (e) {
                    if (customerProofZoom > 1.05) {
                        isCustomerProofPanning = true;
                        customerProofStartX = e.clientX - customerProofPanX;
                        customerProofStartY = e.clientY - customerProofPanY;
                        updateCustomerProofTransform();
                    }
                });

                window.addEventListener('mousemove', function (e) {
                    if (!isCustomerProofPanning) return;
                    customerProofPanX = e.clientX - customerProofStartX;
                    customerProofPanY = e.clientY - customerProofStartY;
                    updateCustomerProofTransform();
                });

                window.addEventListener('mouseup', function () {
                    if (isCustomerProofPanning) {
                        isCustomerProofPanning = false;
                        updateCustomerProofTransform();
                    }
                });

                // Wheel zoom
                viewport.addEventListener('wheel', function (e) {
                    e.preventDefault();
                    const delta = e.deltaY < 0 ? 0.25 : -0.25;
                    zoomCustomerProof(delta);
                }, { passive: false });

                // Double click toggle 2x / 1x
                viewport.addEventListener('dblclick', function () {
                    if (customerProofZoom > 1.05) {
                        resetCustomerProofZoom();
                    } else {
                        customerProofZoom = 2.0;
                        customerProofPanX = 0;
                        customerProofPanY = 0;
                        updateCustomerProofTransform();
                    }
                });

                // Touch pan support for mobile
                let touchStartX = 0;
                let touchStartY = 0;
                viewport.addEventListener('touchstart', function(e) {
                    if (e.touches.length === 1 && customerProofZoom > 1.05) {
                        isCustomerProofPanning = true;
                        touchStartX = e.touches[0].clientX - customerProofPanX;
                        touchStartY = e.touches[0].clientY - customerProofPanY;
                    }
                }, { passive: true });

                viewport.addEventListener('touchmove', function(e) {
                    if (isCustomerProofPanning && e.touches.length === 1) {
                        customerProofPanX = e.touches[0].clientX - touchStartX;
                        customerProofPanY = e.touches[0].clientY - touchStartY;
                        updateCustomerProofTransform();
                    }
                }, { passive: true });

                viewport.addEventListener('touchend', function() {
                    isCustomerProofPanning = false;
                });
            }
        });
    </script>
</x-layouts.customer>
