<x-layouts.customer :title="'Booking ' . $booking->code" :subtitle="$booking->tenant?->name" :tenant="$booking->tenant">
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

        <!-- KARTU STATUS BOOKING -->
        <div class="rounded-3xl bg-white shadow-xl p-5 border border-zinc-100 text-center space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-zinc-400 tracking-wider uppercase">Kode Reservasi Booking</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-500/15 text-amber-800">
                    {{ $booking->status->label() }}
                </span>
            </div>

            <div>
                <p class="text-3xl font-black text-zinc-950 num tracking-tight my-1">{{ $booking->code }}</p>
                <p class="text-xs text-zinc-500">
                    Jadwal Kedatangan: <b class="text-zinc-900">{{ $booking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</b>
                </p>
            </div>

            @if($booking->status === \App\Enums\BookingStatus::Cancelled)
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-left space-y-1.5">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-rose-800">
                        <x-ui.icon name="x" class="w-4 h-4 text-rose-600 shrink-0" />
                        <span>Booking Dibatalkan</span>
                    </div>
                    @if(!empty($booking->cancel_reason))
                        <div class="p-2 rounded-xl bg-white/80 border border-rose-200/60 text-xs font-semibold text-rose-900 leading-relaxed">
                            Alasan: {{ $booking->cancel_reason }}
                        </div>
                    @else
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Booking ini telah dibatalkan oleh kasir atau sistem.
                        </p>
                    @endif
                </div>
            @elseif($booking->queueTicket)
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-left space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-emerald-900">Telah Di-Check-in di Outlet!</span>
                        <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-extrabold text-xs num">
                            {{ $booking->queueTicket->number }}
                        </span>
                    </div>
                    <p class="text-xs text-emerald-700">
                        Tiket antrean fisik telah aktif. Anda dapat memantau giliran layanan dan tombol selesai secara langsung.
                    </p>
                    <a
                        href="{{ route('customer.tickets.show', $booking->queueTicket->id) }}"
                        class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-emerald-600 text-white font-extrabold text-xs hover:bg-emerald-500 transition"
                    >
                        <span>Buka Detail Antrean &amp; Layanan</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            @else
                <div class="p-3.5 rounded-2xl bg-zinc-100 text-zinc-700 text-left space-y-1">
                    <div class="flex items-center gap-2 font-extrabold text-xs text-zinc-900">
                        <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Menunggu Kedatangan Hari H</span>
                    </div>
                    <p class="text-xs text-zinc-500 leading-relaxed">
                        Silakan datang ke outlet sesuai jadwal. Kasir akan melakukan check-in dan mengonversi booking menjadi nomor antrean aktif.
                    </p>
                </div>
            @endif
        </div>

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
                    <span>Layanan Dipesan &amp; Estimasi Biaya</span>
                </h2>
            </x-slot:header>

            <div class="space-y-3">
                <div class="divide-y divide-zinc-100 text-xs">
                    @foreach($booking->items as $item)
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

                @if($booking->hairColor)
                    <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 flex items-center justify-between gap-2 text-xs">
                        <span class="text-zinc-600 font-semibold">Warna Coloring:</span>
                        <span class="inline-flex items-center gap-1.5 font-extrabold text-zinc-900">
                            <span class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0" style="background-color: {{ $booking->hairColor->hex_code }}"></span>
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
                        <span class="text-xs text-zinc-500 font-medium">Estimasi Total Biaya</span>
                        <p class="text-xs text-zinc-400 num">{{ $booking->items->count() }} Layanan</p>
                    </div>
                    <span class="text-lg font-black text-zinc-950 num">
                        Rp{{ number_format($totalPrice, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </x-ui.card>

        <!-- TOMBOL NAVIGASI -->
        <div class="pt-2 space-y-2">
            <a
                href="{{ auth()->check() ? route('customer.history') : route('customer.dashboard') }}"
                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-zinc-950 text-white text-xs font-bold flex items-center justify-center gap-2 hover:bg-zinc-800 transition"
            >
                <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                <span>Lihat Riwayat &amp; Jadwal Booking</span>
            </a>
            <a
                href="{{ auth()->check() ? route('customer.dashboard') : route('customer.portal', $booking->tenant?->slug ?? '') }}"
                class="w-full min-h-[44px] px-4 py-2.5 rounded-xl border border-zinc-200 text-zinc-700 bg-white hover:bg-zinc-50 text-xs font-bold flex items-center justify-center gap-2 transition"
            >
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span>Kembali ke Dashboard</span>
            </a>
        </div>
    </div>
</x-layouts.customer>
