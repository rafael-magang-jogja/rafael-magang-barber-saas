<x-layouts.customer :title="'Antrean ' . $ticket->number" :subtitle="$ticket->tenant?->name ?? 'Nomor Antrean Anda'" :tenant="$tenant">
    <div class="space-y-4">
        <!-- Info Barbershop & Outlet -->
        <div class="rounded-2xl bg-zinc-950 text-white p-4 shadow-lg border border-zinc-800">
            <div class="flex items-center justify-between gap-2">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold">
                    <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                    <span>{{ $ticket->tenant?->name }}</span>
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

        <!-- KARTU STATUS ANTREAN -->
        <div class="rounded-3xl bg-white shadow-xl p-5 border border-zinc-100 text-center space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-zinc-400 tracking-wider uppercase">Nomor Antrean Anda</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold {{ $ticket->type === 'booking' ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700' }}">
                    {{ $ticket->type === 'booking' ? 'Reservasi Booking' : 'Walk-in di Tempat' }}
                </span>
            </div>

            <div>
                <p class="text-5xl font-black text-zinc-950 num tracking-tight my-1">{{ $ticket->number }}</p>
                <p class="text-xs text-zinc-500">
                    Atas Nama: <b class="text-zinc-900">{{ $ticket->customerProfile?->name ?? 'Pelanggan' }}</b>
                    @if($ticket->customerProfile?->phone)
                        @php
                            $phoneStr = $ticket->customerProfile->phone;
                            $maskedPhone = strlen($phoneStr) > 7 
                                ? substr($phoneStr, 0, 4) . '****' . substr($phoneStr, -3)
                                : '****';
                        @endphp
                        · {{ $maskedPhone }}
                    @endif
                </p>
            </div>

            <!-- Banner Status Realtime -->
            @php
                $isPaid = $order !== null && $order->payment_status === \App\Enums\PaymentStatus::Paid;
                $hasPendingOrder = $order !== null && $order->payment_status === \App\Enums\PaymentStatus::Unpaid;
            @endphp

            @if($ticket->status === \App\Enums\QueueStatus::Cancelled)
                @php
                    $refundBank = $ticket->refund_bank_name ?: $ticket->booking?->refund_bank_name;
                    $refundAccNum = $ticket->refund_account_number ?: $ticket->booking?->refund_account_number;
                    $refundAccName = $ticket->refund_account_name ?: $ticket->booking?->refund_account_name;
                @endphp
                <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 text-left space-y-3 shadow-2xs">
                    <!-- Header Status Pembatalan -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                <x-ui.icon name="x" class="w-4 h-4 stroke-[2.5]" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-extrabold text-sm text-zinc-950 leading-tight">Antrean Dibatalkan</h3>
                                <p class="text-xs text-rose-700 font-medium truncate mt-0.5">
                                    {{ !empty($ticket->notes) ? $ticket->notes : 'Layanan antrean telah dibatalkan.' }}
                                </p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-200 shrink-0">
                            Batal
                        </span>
                    </div>

                    @if($ticket->refund_status === 'pending')
                        <!-- Kartu Pengembalian Dana (Status: Pending Transfer) -->
                        <div class="rounded-2xl bg-white border border-amber-300 p-4 space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between gap-2 border-b border-amber-100 pb-2.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500 text-zinc-950 shadow-2xs">
                                    <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                                    <span>Menunggu Transfer</span>
                                </span>
                                <span class="text-base sm:text-lg font-black text-amber-600 num">
                                    Rp{{ number_format($ticket->refund_amount, 0, ',', '.') }}
                                </span>
                            </div>

                            <p class="text-xs text-zinc-600 leading-relaxed font-medium">
                                Pengembalian dana pembayaran QRIS sedang dalam antrean proses transfer oleh kasir atau owner barbershop.
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

                    @elseif($ticket->refund_status === 'completed')
                        <!-- Kartu Pengembalian Dana (Status: Selesai Ditransfer) -->
                        <div class="rounded-2xl bg-white border border-emerald-300 p-4 space-y-3 shadow-sm">
                            <div class="flex items-center justify-between gap-2 border-b border-emerald-100 pb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white shadow-2xs">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 stroke-[3]" />
                                    <span>Pengembalian Dana Selesai</span>
                                </span>
                                <span class="text-base sm:text-lg font-black text-emerald-600 num">
                                    Rp{{ number_format($ticket->refund_amount, 0, ',', '.') }}
                                </span>
                            </div>

                            <p class="text-xs text-zinc-600 leading-relaxed font-medium">
                                @if($refundBank)
                                    Dana pembayaran QRIS telah dikonfirmasi dan ditransfer ke rekening Anda oleh kasir/owner.
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

                                @if($ticket->refunded_at)
                                    <div class="flex items-center justify-between text-zinc-500 text-xs pt-1 border-t border-zinc-200/60">
                                        <span class="text-[11px]">Waktu Selesai:</span>
                                        <span class="font-bold text-zinc-900 num text-[11px]">
                                            {{ \Carbon\Carbon::parse($ticket->refunded_at)->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    </div>
                                @endif

                                @if(!empty($ticket->refund_notes))
                                    <div class="pt-1 border-t border-zinc-200/60">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Catatan:</span>
                                        <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-200/60 text-[11px] text-emerald-950 font-medium leading-relaxed">
                                            {{ $ticket->refund_notes }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($ticket->status === \App\Enums\QueueStatus::Completed)
                @if($isPaid)
                    <div class="p-3.5 rounded-2xl bg-emerald-500 text-white text-left space-y-1 shadow-sm">
                        <div class="flex items-center gap-2 font-extrabold text-sm">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Layanan Selesai &amp; Lunas</span>
                        </div>
                        <p class="text-xs text-emerald-100 leading-relaxed">
                            Layanan potong rambut telah selesai dikerjakan staff dan transaksi telah lunas. Terima kasih atas kunjungan Anda!
                        </p>
                    </div>
                @else
                    <div class="p-3.5 rounded-2xl bg-amber-500 text-zinc-950 text-left space-y-1 shadow-sm">
                        <div class="flex items-center gap-2 font-extrabold text-sm">
                            <x-ui.icon name="alert-circle" class="w-4 h-4 shrink-0" />
                            <span>Layanan Selesai · Menunggu Pembayaran</span>
                        </div>
                        <p class="text-xs font-semibold opacity-95 leading-relaxed">
                            Layanan telah selesai. Mohon segera selesaikan pembayaran di kasir atau via QRIS.
                        </p>
                    </div>
                @endif
            @elseif($ticket->status === \App\Enums\QueueStatus::InService)
                <div class="p-3.5 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-sm text-sky-800">
                        <x-ui.icon name="scissors" class="w-4 h-4 text-sky-600 shrink-0" />
                        <span>Sedang Dilayani Staff di Kursi Barber</span>
                    </div>
                    <p class="text-xs text-sky-700 leading-relaxed">
                        Layanan pangkas rambut Anda sedang berlangsung. @if($isPaid) <span class="font-bold text-emerald-700">Pembayaran telah lunas di awal.</span> @endif
                    </p>
                </div>
            @elseif($ticket->status === \App\Enums\QueueStatus::Called)
                <div class="p-3.5 rounded-2xl bg-amber-500 text-zinc-950 text-left space-y-1.5 animate-pulse shadow-md">
                    <div class="flex items-center gap-2 font-extrabold text-sm">
                        <x-ui.icon name="megaphone" class="w-4 h-4 shrink-0" />
                        <span>NOMOR ANDA SEDANG DIPANGGIL!</span>
                    </div>
                    <p class="text-xs font-bold leading-relaxed">
                        Silakan segera menuju kursi barber yang ditunjuk.
                    </p>
                    @if(! $isPaid)
                        <div class="p-2 rounded-xl bg-zinc-950 text-amber-300 text-[11px] font-extrabold flex items-center gap-1.5">
                            <x-ui.icon name="info" class="w-3.5 h-3.5 shrink-0" />
                            <span>Wajib lunas sebelum dimulai. Silakan bayar via tombol di bawah atau di meja kasir.</span>
                        </div>
                    @endif
                </div>
            @else
                @if(! $isPaid)
                    <div class="p-3.5 rounded-2xl bg-amber-50 border-2 border-amber-400 text-amber-950 text-left space-y-1.5">
                        <div class="flex items-center gap-2 font-extrabold text-xs text-amber-900">
                            <x-ui.icon name="credit-card" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Wajib Bayar di Awal (Saat Antre)</span>
                        </div>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Sesuai ketentuan, pembayaran wajib dilakukan di muka saat mengantre sebelum layanan potong rambut dimulai.
                        </p>
                        <div class="text-[11px] font-bold text-amber-900/80">
                            • Bayar mandiri lewat QRIS atau tunai di meja kasir sekarang.
                        </div>
                    </div>
                @else
                    <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-left space-y-1">
                        <div class="flex items-center gap-2 font-extrabold text-xs text-emerald-800">
                            <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                            <span>Pembayaran Lunas (Bayar di Muka)</span>
                        </div>
                        <p class="text-xs text-emerald-700 leading-relaxed">
                            Pembayaran Anda telah lunas di awal. Silakan bersantai menunggu nomor antrean dipanggil oleh barber.
                        </p>
                    </div>
                @endif
            @endif
        </div>

        <!-- KARTU INFORMASI STAFF / BARBER -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-2">
                    <x-ui.icon name="users" class="w-4 h-4 text-amber-600" />
                    <span>Staff / Barber yang Menangani</span>
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
                        <p class="text-[11px] text-emerald-700 font-semibold mt-1 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                            <span>
                                @if($ticket->status === \App\Enums\QueueStatus::Completed)
                                    Telah menyelesaikan layanan Anda
                                @elseif($ticket->status === \App\Enums\QueueStatus::InService)
                                    Sedang melayani Anda sekarang
                                @elseif($ticket->status === \App\Enums\QueueStatus::Called)
                                    Memanggil Anda ke kursi barber
                                @else
                                    Ditugaskan untuk nomor antrean Anda
                                @endif
                            </span>
                        </p>
                    </div>
                </div>
            @else
                <div class="p-3 rounded-2xl bg-zinc-50 border border-dashed border-zinc-200 text-center">
                    <p class="font-extrabold text-xs text-zinc-800">Menunggu Panggilan Staff / Barber</p>
                    <p class="text-[11px] text-zinc-400 mt-0.5">
                        Staff yang bertugas akan otomatis tertera di sini saat nomor antrean Anda dipanggil ke meja/kursi.
                    </p>
                </div>
            @endif
        </x-ui.card>

        <!-- KARTU RINCIAN LAYANAN & HARGA -->
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-2">
                    <x-ui.icon name="scissors" class="w-4 h-4 text-amber-600" />
                    <span>Layanan &amp; Rincian Harga</span>
                </h2>
            </x-slot:header>

            <div class="space-y-3">
                <div class="divide-y divide-zinc-100 text-xs">
                    @foreach($ticket->items as $item)
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

                @if($ticket->hairColor)
                    <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 flex items-center justify-between gap-2 text-xs">
                        <span class="text-zinc-600 font-semibold">Warna Coloring:</span>
                        <span class="inline-flex items-center gap-1.5 font-extrabold text-zinc-900">
                            <span class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0" @style(['background-color: ' . $ticket->hairColor->hex_code])></span>
                            <span>{{ $ticket->hairColor->name }}</span>
                        </span>
                    </div>
                @endif

                @if(!empty($ticket->notes))
                    <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 text-xs">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Catatan Gaya Rambut:</span>
                        <p class="text-zinc-800 font-medium mt-0.5">{{ $ticket->notes }}</p>
                    </div>
                @endif

                <div class="pt-3 border-t border-zinc-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-zinc-500 font-medium">Total Tagihan</span>
                        <p class="text-xs text-zinc-400 num">{{ $ticket->items->count() }} Layanan</p>
                    </div>
                    <span class="text-lg font-black text-zinc-950 num">
                        Rp{{ number_format($totalPrice, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </x-ui.card>

        <!-- TOMBOL AKSI UTAMA CUSTOMER -->
        <div class="pt-2 space-y-2.5">
            @if(! $isPaid && ! in_array($ticket->status, [\App\Enums\QueueStatus::Cancelled, \App\Enums\QueueStatus::NoShow], true))
                @php
                    $checkoutLink = $hasPendingOrder
                        ? route('customer.tickets.waiting-payment', $ticket->id)
                        : route('customer.tickets.checkout', $ticket->id);
                @endphp
                <x-ui.button
                    :href="$checkoutLink"
                    size="lg"
                    class="w-full !bg-amber-500 hover:!bg-amber-400 !text-zinc-950 shadow-xl py-3.5 text-base font-black flex items-center justify-center gap-2 cursor-pointer"
                >
                    <x-ui.icon name="credit-card" class="w-5 h-5 text-zinc-950" />
                    <span>Bayar Sekarang (Saat Antre / Di Muka)</span>
                    <x-ui.icon name="arrow-right" class="w-4 h-4 text-zinc-950" />
                </x-ui.button>
                <p class="text-[11px] text-center text-zinc-500">
                    Sesuai aturan, pembayaran wajib lunas sebelum nomor antrean Anda mulai dicukur.
                </p>
            @endif

            @if($ticket->status === \App\Enums\QueueStatus::Completed)
                @php
                    $ratingLabels = [
                        1 => 'Sangat Kecewa',
                        2 => 'Kurang Puas',
                        3 => 'Cukup Baik',
                        4 => 'Puas!',
                        5 => 'Sangat Puas!',
                    ];
                @endphp

                @if($review)
                    <!-- 1. KARTU TAMPILAN ULASAN TERSIMPAN (STATE SUDAH DINILAI) -->
                    <x-ui.card id="review-display-card" class="border-2 border-emerald-300 bg-emerald-50/40 shadow-xs">
                        <x-slot:header>
                            <div class="flex items-center justify-between w-full">
                                <h3 class="font-extrabold text-xs uppercase tracking-wider text-emerald-950 flex items-center gap-2">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600" />
                                    <span>Penilaian Anda untuk Staff</span>
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-black uppercase tracking-wider shadow-2xs">
                                    Sudah Dinilai
                                </span>
                            </div>
                        </x-slot:header>

                        <div class="space-y-3.5">
                            <!-- Bintang & Skor Rating -->
                            <div class="p-3.5 rounded-2xl bg-white border border-emerald-200/80 shadow-2xs text-center space-y-1">
                                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Hasil Layanan &amp; Pelayanan Staff</p>
                                <div class="flex items-center justify-center gap-1.5 py-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <svg class="w-7 h-7 text-amber-400 fill-amber-400 drop-shadow-xs" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                            </svg>
                                        @else
                                            <svg class="w-7 h-7 text-zinc-300 fill-zinc-100" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                            </svg>
                                        @endif
                                    @endfor
                                </div>
                                <p class="text-sm font-black text-zinc-900 num">
                                    {{ $review->rating }} / 5 · <span class="text-amber-600 font-extrabold">{{ $ratingLabels[$review->rating] ?? 'Puas!' }}</span>
                                </p>
                                <p class="text-[10px] text-zinc-400 font-medium">
                                    Diperbarui {{ $review->updated_at ? $review->updated_at->diffForHumans() : 'baru saja' }}
                                </p>
                            </div>

                            <!-- Masukan / Komentar Pelanggan -->
                            <div class="p-3 rounded-xl bg-white/90 border border-emerald-200/70 text-xs space-y-1">
                                <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider block">Ulasan / Masukan Anda:</span>
                                @if(!empty($review->comment))
                                    <p class="text-zinc-800 italic leading-relaxed font-medium text-xs bg-zinc-50 p-2.5 rounded-lg border border-zinc-100">
                                        “{{ $review->comment }}”
                                    </p>
                                @else
                                    <p class="text-zinc-400 italic text-[11px] py-1">
                                        (Tidak ada catatan ulasan tertulis)
                                    </p>
                                @endif
                            </div>

                            <!-- Tombol Aksi: Ubah Penilaian -->
                            <button
                                type="button"
                                onclick="openReviewEditMode()"
                                class="w-full min-h-[42px] px-4 py-2.5 rounded-xl bg-white hover:bg-zinc-50 text-amber-950 border border-amber-300 font-extrabold text-xs flex items-center justify-center gap-2 shadow-2xs hover:shadow-xs transition cursor-pointer"
                            >
                                <x-ui.icon name="edit" class="w-4 h-4 text-amber-600" />
                                <span>Ubah Bintang &amp; Masukan</span>
                            </button>
                        </div>
                    </x-ui.card>
                @endif

                <!-- 2. FORM PENILAIAN INTERAKTIF (Untuk Buat Baru atau Mode Edit) -->
                <x-ui.card id="review-form-card" class="border-2 border-amber-300 bg-amber-50/40 {{ $review ? 'hidden' : '' }}">
                    <x-slot:header>
                        <div class="flex items-center justify-between w-full">
                            <h3 class="font-extrabold text-xs uppercase tracking-wider text-amber-900 flex items-center gap-2">
                                <x-ui.icon name="star" class="w-4 h-4 text-amber-500" />
                                <span>{{ $review ? 'Ubah Penilaian Layanan Staff' : 'Beri Penilaian Layanan Staff' }}</span>
                            </h3>
                            @if($review)
                                <button
                                    type="button"
                                    onclick="closeReviewEditMode()"
                                    class="text-[11px] font-bold text-zinc-500 hover:text-zinc-800 underline cursor-pointer"
                                >
                                    Batal
                                </button>
                            @endif
                        </div>
                    </x-slot:header>

                    <form method="POST" action="{{ route('customer.tickets.review', $ticket->id) }}" class="space-y-3.5">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1 text-center">
                                {{ $review ? 'Pilih Bintang Baru untuk Mengubah Penilaian:' : 'Bagaimana hasil cukur dan pelayanan staff kami?' }}
                            </label>

                            <input type="hidden" name="rating" id="ticket_rating_input" value="{{ old('rating', $review?->rating ?? 5) }}" required />

                            <div class="flex items-center justify-center gap-2 py-1.5" id="ticket_star_container">
                                @for($i = 1; $i <= 5; $i++)
                                    <button
                                        type="button"
                                        data-ticket-star="{{ $i }}"
                                        class="w-11 h-11 rounded-2xl flex items-center justify-center transition-all transform hover:scale-115 active:scale-95 cursor-pointer focus:outline-none"
                                        aria-label="Rating {{ $i }} Bintang"
                                    >
                                        <svg class="w-8 h-8 text-amber-400 fill-amber-400 drop-shadow-xs transition-colors duration-150" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                        </svg>
                                    </button>
                                @endfor
                            </div>

                            <p class="text-xs font-extrabold text-center text-amber-800 mt-0.5" id="ticket_rating_label">
                                {{ old('rating', $review?->rating ?? 5) }} / 5 · {{ $ratingLabels[old('rating', $review?->rating ?? 5)] ?? 'Sangat Puas!' }}
                            </p>
                        </div>

                        <div>
                            <label for="ticket-review-comment" class="block text-xs font-bold text-zinc-700 mb-1">
                                Ulasan / Masukan (Opsional)
                            </label>
                            <textarea
                                id="ticket-review-comment"
                                name="comment"
                                rows="2"
                                placeholder="Bagikan komentar mengenai kerapian potongan atau keramahan staff..."
                                class="w-full rounded-xl border border-zinc-200 bg-white p-2.5 text-xs text-zinc-800 placeholder:text-zinc-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                            >{{ old('comment', $review?->comment) }}</textarea>
                        </div>

                        <div class="space-y-2">
                            <button
                                type="submit"
                                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs flex items-center justify-center gap-2 shadow-sm hover:shadow-md transition cursor-pointer"
                            >
                                <x-ui.icon name="star" class="w-4 h-4 text-zinc-950" />
                                <span>{{ $review ? 'Simpan Perubahan Penilaian' : 'Kirim Penilaian' }}</span>
                            </button>
                            @if($review)
                                <button
                                    type="button"
                                    onclick="closeReviewEditMode()"
                                    class="w-full py-2 rounded-xl text-center text-xs font-bold text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 transition cursor-pointer"
                                >
                                    Batal Mengubah
                                </button>
                            @endif
                        </div>
                    </form>
                </x-ui.card>
            @endif

            @if($isPaid)
                <!-- 2. TOMBOL E-INVOICE -->
                <a
                    href="{{ route('invoices.show', $order->number) }}"
                    class="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-white font-extrabold text-sm flex items-center justify-center gap-2 transition shadow-md"
                >
                    <x-ui.icon name="file-text" class="w-4 h-4 text-amber-400" />
                    <span>Lihat E-Invoice</span>
                </a>
            @endif

            <a
                href="{{ auth()->check() ? route('customer.dashboard') : ($ticket->tenant ? $ticket->tenant->customerPortalUrl() : url('/')) }}"
                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-zinc-200 text-zinc-700 bg-white hover:bg-zinc-50 text-xs font-bold flex items-center justify-center gap-2 transition"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>{{ auth()->check() ? 'Kembali ke Beranda' : 'Kembali ke Beranda ' . ($ticket->tenant?->name ?? 'Barbershop') }}</span>
            </a>

            @if(in_array($ticket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true))
                <button
                    type="button"
                    data-dialog-id="modal-cancel-ticket"
                    onclick="document.getElementById(this.dataset.dialogId).showModal()"
                    class="w-full min-h-[44px] py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-[0.99] text-white font-extrabold text-xs flex items-center justify-center transition shadow-xs cursor-pointer"
                >
                    Batalkan Antrean
                </button>
            @endif
        </div>
    </div>

    @if(in_array($ticket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true))
        <!-- Modal Konfirmasi Pembatalan Antrean -->
        <dialog
            id="modal-cancel-ticket"
            onclick="if(event.target===this)this.close()"
            class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
        >
            <form method="POST" action="{{ route('customer.tickets.cancel', $ticket->id) }}" class="p-5 space-y-4">
                @csrf
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 grid place-items-center shrink-0">
                        <x-ui.icon name="alert-triangle" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-extrabold text-sm text-zinc-950">Batalkan Nomor Antrean?</h3>
                        <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                            Apakah Anda yakin ingin membatalkan antrean <b class="text-zinc-900 num">{{ $ticket->number }}</b> di <b class="text-zinc-900">{{ $ticket->tenant?->name }}</b>? Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                @php
                    $paidPayment = $order?->payments?->firstWhere('status', 'success') ?? $order?->payments?->first();
                    $paidMethod = $paidPayment?->method;
                    $isQrisPaid = $isPaid && (
                        $paidMethod === \App\Enums\PaymentMethod::Qris
                        || (is_object($paidMethod) && $paidMethod->value === 'qris')
                        || $paidMethod === 'qris'
                    );
                    $isCashPaid = $isPaid && ! $isQrisPaid;
                @endphp

                @if($isQrisPaid)
                    <div class="flex items-start gap-2.5 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left">
                        <x-ui.icon name="wallet" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="space-y-0.5 min-w-0">
                            <h4 class="font-bold text-xs text-emerald-950">Pengembalian Dana ke Saldo Dompet</h4>
                            <p class="text-[11px] text-emerald-800 leading-relaxed">
                                Dana <strong class="num font-black text-emerald-950">Rp{{ number_format($order->paid_total, 0, ',', '.') }}</strong> otomatis masuk ke Saldo Dompet Anda dan siap dipakai booking lagi atau dicairkan.
                            </p>
                        </div>
                    </div>
                @elseif($isCashPaid)
                    <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-950 space-y-1.5">
                        <div class="flex items-center gap-2 font-bold text-xs text-emerald-800">
                            <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                            <span>Pengembalian Tunai Langsung</span>
                        </div>
                        <p class="text-[11px] text-emerald-900 leading-relaxed">
                            Karena Anda telah membayar <strong class="num">Rp{{ number_format($order->paid_total, 0, ',', '.') }}</strong> secara <b>Tunai (Cash)</b> di kasir, Anda <b>tidak perlu mengisi nomor rekening</b>. Uang tunai Anda dapat diambil langsung di meja kasir outlet saat pembatalan.
                        </p>
                    </div>
                @endif

                <div>
                    <label for="cancel-ticket-reason" class="block text-[11px] font-bold text-zinc-600 mb-1">
                        Alasan Pembatalan <span class="text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        id="cancel-ticket-reason"
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
                        data-dialog-id="modal-cancel-ticket"
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

    <!-- Script Interaktif Star Rating Penilaian Staff -->
    <script>
        window.openReviewEditMode = function () {
            const displayCard = document.getElementById('review-display-card');
            const formCard = document.getElementById('review-form-card');
            if (displayCard) displayCard.classList.add('hidden');
            if (formCard) {
                formCard.classList.remove('hidden');
                formCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        };

        window.closeReviewEditMode = function () {
            const displayCard = document.getElementById('review-display-card');
            const formCard = document.getElementById('review-form-card');
            if (formCard) formCard.classList.add('hidden');
            if (displayCard) {
                displayCard.classList.remove('hidden');
                displayCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        };

        (function () {
            const ratingInput = document.getElementById('ticket_rating_input');
            const ratingLabel = document.getElementById('ticket_rating_label');
            const starButtons = document.querySelectorAll('[data-ticket-star]');
            const starContainer = document.getElementById('ticket_star_container');

            if (!ratingInput || !starButtons.length) return;

            const labels = {
                1: '1 / 5 · Sangat Kecewa',
                2: '2 / 5 · Kurang Puas',
                3: '3 / 5 · Cukup Baik',
                4: '4 / 5 · Puas!',
                5: '5 / 5 · Sangat Puas!'
            };

            function renderTicketStars(val) {
                starButtons.forEach(btn => {
                    const btnVal = parseInt(btn.getAttribute('data-ticket-star'), 10);
                    const svg = btn.querySelector('svg');
                    if (svg) {
                        if (btnVal <= val) {
                            svg.setAttribute('class', 'w-8 h-8 text-amber-400 fill-amber-400 drop-shadow-xs transition-colors duration-150');
                        } else {
                            svg.setAttribute('class', 'w-8 h-8 text-zinc-300 fill-zinc-100 transition-colors duration-150');
                        }
                    }
                });
            }

            function setTicketRating(val) {
                ratingInput.value = val;
                if (ratingLabel && labels[val]) {
                    ratingLabel.textContent = labels[val];
                }
                renderTicketStars(val);
            }

            if (starContainer) {
                starContainer.addEventListener('mouseleave', () => {
                    const currentVal = parseInt(ratingInput.value || '5', 10);
                    renderTicketStars(currentVal);
                });
            }

            starButtons.forEach(btn => {
                const val = parseInt(btn.getAttribute('data-ticket-star'), 10);
                btn.addEventListener('mouseenter', () => renderTicketStars(val));
                btn.addEventListener('click', () => setTicketRating(val));
            });

            // Initial render berdasarkan nilai input saat ini
            setTicketRating(parseInt(ratingInput.value || '5', 10));
        })();
    </script>

    @if(! in_array($ticket->status, [\App\Enums\QueueStatus::Completed, \App\Enums\QueueStatus::Cancelled, \App\Enums\QueueStatus::NoShow], true))
        <!-- Real-time Poller (Setiap 5 detik): Memeriksa pemanggilan staff & status pangkas rambut -->
        @php
            $ticketPollUrl = route('customer.tickets.show', $ticket->id) . '?poll=1';
            $ticketStatusVal = $ticket->status->value;
            $barberNameVal = $ticket->barber?->display_name ?? '';
            $isPaidVal = $isPaid ? '1' : '0';
        @endphp
        <script
            id="ticket-poller-script"
            data-poll-url="{{ $ticketPollUrl }}"
            data-current-status="{{ $ticketStatusVal }}"
            data-current-barber="{{ $barberNameVal }}"
            data-current-paid="{{ $isPaidVal }}"
        >
            (function () {
                const pollerCfg = document.getElementById('ticket-poller-script')?.dataset || {};
                const pollUrl = pollerCfg.pollUrl || '';
                const currentStatus = pollerCfg.currentStatus || '';
                const currentBarber = pollerCfg.currentBarber || '';
                const currentPaid = pollerCfg.currentPaid || '0';

                // Polling setiap 5 detik agar sistem tetap ringan dan responsif
                setInterval(async () => {
                    try {
                        const res = await fetch(pollUrl, {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store'
                        });
                        if (!res.ok) return;
                        const data = await res.json();

                        const newBarber = data.barber_name || '';
                        const newPaid = data.is_paid ? '1' : '0';

                        // Jika status berubah, staff mulai memanggil/melayani, atau selesai cukur
                        if (data.status !== currentStatus || data.is_completed || newBarber !== currentBarber || newPaid !== currentPaid) {
                            window.location.reload();
                        }
                    } catch (e) {
                        // Silent catch jika ada kendala jaringan sesaat
                    }
                }, 5000);
            })();
        </script>
    @endif
</x-layouts.customer>
