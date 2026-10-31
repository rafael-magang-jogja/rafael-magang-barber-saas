<x-layouts.customer :title="'Antrean ' . $ticket->number" :subtitle="$ticket->tenant?->name" :tenant="$ticket->tenant">
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
                        class="text-xs text-amber-400 hover:text-amber-300 font-bold inline-flex items-center gap-1 bg-white/10 hover:bg-white/15 px-2.5 py-1 rounded-xl transition"
                    >
                        <span>Google Maps</span>
                        <x-ui.icon name="external-link" class="w-3 h-3" />
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
                $isStaffFinished = $ticket->status === \App\Enums\QueueStatus::Completed && ! $isPaid;
            @endphp

            @if($isStaffFinished)
                <div class="p-3.5 rounded-2xl bg-emerald-50 border-2 border-emerald-500 text-emerald-900 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-sm text-emerald-800">
                        <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>Layanan Selesai Dikerjakan Staff!</span>
                    </div>
                    <p class="text-xs text-emerald-700 leading-relaxed">
                        Staff telah menyelesaikan cukur/layanan Anda. Silakan tekan tombol <b>Selesai</b> di bawah untuk memberikan penilaian bintang kepada staff dan memilih metode pembayaran Anda.
                    </p>
                </div>
            @elseif($isPaid)
                <div class="p-3.5 rounded-2xl bg-emerald-500 text-white text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-sm">
                        <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Selesai &amp; Lunas</span>
                    </div>
                    <p class="text-xs text-emerald-100 leading-relaxed">
                        Transaksi telah selesai. Terima kasih atas kunjungan Anda!
                    </p>
                </div>
            @elseif($ticket->status === \App\Enums\QueueStatus::InService)
                <div class="p-3.5 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-sm text-sky-800">
                        <x-ui.icon name="scissors" class="w-4 h-4 text-sky-600 shrink-0" />
                        <span>Sedang Dilayani Staff</span>
                    </div>
                    <p class="text-xs text-sky-700 leading-relaxed">
                        Layanan sedang berlangsung. Tombol selesai akan otomatis muncul setelah staff menyelesaikan potong rambut di sistem kasir/barber.
                    </p>
                </div>
            @elseif($ticket->status === \App\Enums\QueueStatus::Called)
                <div class="p-3.5 rounded-2xl bg-amber-500 text-zinc-950 text-left space-y-1 animate-pulse">
                    <div class="flex items-center gap-2 font-extrabold text-sm">
                        <x-ui.icon name="megaphone" class="w-4 h-4 shrink-0" />
                        <span>NOMOR ANDA SEDANG DIPANGGIL!</span>
                    </div>
                    <p class="text-xs font-semibold text-zinc-900 leading-relaxed">
                        Silakan segera menuju kursi barber yang ditunjuk atau meja layanan.
                    </p>
                </div>
            @elseif($ticket->status === \App\Enums\QueueStatus::Cancelled)
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-950 text-left space-y-1.5">
                    <div class="flex items-center gap-2 font-extrabold text-sm text-rose-700">
                        <x-ui.icon name="x" class="w-4 h-4 text-rose-600 shrink-0" />
                        <span>Antrean Dibatalkan</span>
                    </div>
                    @if(!empty($ticket->notes))
                        <div class="p-2 rounded-xl bg-white/80 border border-rose-200/60 text-xs font-semibold text-rose-900">
                            {{ $ticket->notes }}
                        </div>
                    @else
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Antrean ini telah dibatalkan oleh kasir atau sistem.
                        </p>
                    @endif
                </div>
            @else
                <div class="p-3.5 rounded-2xl bg-zinc-100 text-zinc-700 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-zinc-900">
                        <x-ui.icon name="ticket" class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Menunggu Giliran Antrean</span>
                    </div>
                    <p class="text-xs text-zinc-500 leading-relaxed">
                        Nomor Anda sedang mengantre. Estimasi waktu layanan sekitar ~{{ $ticket->estimated_duration_minutes }} menit.
                    </p>
                </div>
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
                                <p class="text-[11px] text-zinc-400">Durasi: ~{{ $item->duration_minutes }} menit</p>
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
                            <span class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0" style="background-color: {{ $ticket->hairColor->hex_code }}"></span>
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

        <!-- KARTU ULASAN JIKA SUDAH PERNAH DINILAI -->
        @if($review)
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-bold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-2">
                        <x-ui.icon name="star" class="w-4 h-4 text-amber-500" />
                        <span>Penilaian Anda untuk Staff</span>
                    </h2>
                </x-slot:header>
                <div class="p-3 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-1.5">
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <x-ui.icon name="star" class="w-4 h-4 {{ $i <= $review->rating ? 'text-amber-500' : 'text-zinc-300' }}" />
                        @endfor
                        <span class="text-xs font-extrabold text-amber-900 ml-1.5">({{ $review->rating }}/5)</span>
                    </div>
                    @if(!empty($review->comment))
                        <p class="text-xs text-zinc-700 italic">"{{ $review->comment }}"</p>
                    @endif
                </div>
            </x-ui.card>
        @endif

        <!-- TOMBOL AKSI UTAMA CUSTOMER -->
        <div class="pt-2 space-y-2.5">
            @if($isStaffFinished)
                <!-- TOMBOL SELESAI: MUNCUL SETELAH STAFF MENEKAN SELESAI DI HALAMANNYA -->
                <x-ui.button
                    :href="route('customer.tickets.checkout', $ticket->id)"
                    size="lg"
                    class="w-full !bg-emerald-600 hover:!bg-emerald-500 !text-white shadow-xl py-3.5 text-base font-extrabold flex items-center justify-center gap-2"
                >
                    <span>Selesai &amp; Lanjut ke Pembayaran</span>
                    <x-ui.icon name="arrow-right" class="w-4 h-4" />
                </x-ui.button>
                <p class="text-[11px] text-center text-zinc-500">
                    Layanan selesai. Klik di atas untuk menilai keramahan staff dan memilih metode pembayaran Anda.
                </p>
            @elseif($isPaid)
                <a
                    href="{{ route('invoices.show', $order->number) }}"
                    class="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-sm flex items-center justify-center gap-2 hover:bg-zinc-800 transition shadow-md"
                >
                    <x-ui.icon name="file-text" class="w-4 h-4" />
                    <span>Buka E-Invoice Resmi</span>
                </a>
            @else
                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-center space-y-1">
                    <p class="text-xs font-bold text-zinc-700">Menunggu Proses Layanan Staff</p>
                    <p class="text-[11px] text-zinc-400 leading-relaxed">
                        Tombol <b>Selesai</b> akan muncul otomatis setelah staff menekan selesai cukur di layarnya. Halaman ini memantau status secara langsung.
                    </p>
                </div>
            @endif

            <a
                href="{{ auth()->check() ? route('customer.dashboard') : route('customer.portal', $ticket->tenant?->slug ?? '') }}"
                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-zinc-200 text-zinc-700 bg-white hover:bg-zinc-50 text-xs font-bold flex items-center justify-center gap-2 transition"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>Kembali ke Halaman Utama</span>
            </a>
        </div>
    </div>

    @if(! $isPaid)
        <!-- Real-time Poller untuk memeriksa apakah staff telah menekan selesai atau status berubah -->
        <script>
            (function () {
                const pollUrl = "{{ route('customer.tickets.show', $ticket->id) }}?poll=1";
                const currentStatus = "{{ $ticket->status->value }}";

                setInterval(async () => {
                    try {
                        const res = await fetch(pollUrl, {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store'
                        });
                        if (!res.ok) return;
                        const data = await res.json();

                        // Jika status berubah atau sudah completed, refresh untuk menampilkan tombol Selesai
                        if (data.status !== currentStatus || data.is_completed) {
                            window.location.reload();
                        }
                    } catch (e) {
                        // Silent catch on network error
                    }
                }, 5000);
            })();
        </script>
    @endif
</x-layouts.customer>
