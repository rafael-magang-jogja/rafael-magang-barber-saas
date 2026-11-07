<x-layouts.customer title="Dashboard Pelanggan" subtitle="Pusat Antrean & Reservasi" :tenant="$tenant">
    @php
        $branchMapsData = $branchMapsData ?? [];

        // Katalog Produk Iklan Official Platform BarberOS (Dinamis dari Konfigurasi Platform Superadmin)
        $allOfficialAds = \App\Support\Platform\PlatformConfig::promotedProducts();
        $officialAds = array_values(array_filter($allOfficialAds, fn($ad) => ($ad['is_active'] ?? true)));
        if (empty($officialAds)) {
            $officialAds = \App\Support\Platform\PlatformConfig::defaultPromotedProducts();
        }
        $slideDurationSeconds = \App\Support\Platform\PlatformConfig::promotedProductsSlideDuration();
    @endphp

    <!-- BANNER IKLAN PRODUK OFFICIAL PLATFORM (Clean Dark, Hanya Gambar + Nama + Harga, Slide 5 Detik) -->
    <div
        id="official-ad-slider"
        class="relative rounded-2xl bg-zinc-950 text-white shadow-lg border border-zinc-800/90 overflow-hidden select-none"
        aria-label="Iklan Produk Resmi BarberOS"
    >
        <!-- Top Bar Header Toko Platform (Clean Minimal) -->
        <div class="px-3.5 py-2 flex items-center justify-between border-b border-zinc-800/80 bg-zinc-950">
            <div class="flex items-center gap-2 min-w-0">
                <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                <span class="text-xs font-black text-white tracking-tight truncate">
                    Produk Rekomendasi
                </span>
                <span class="text-[9px] font-black uppercase tracking-wider text-amber-400 bg-amber-500/15 border border-amber-400/25 px-1.5 py-0.5 rounded-md shrink-0">
                    Official Store
                </span>
            </div>

            <!-- Mini Nav Controls -->
            <div class="flex items-center gap-1 shrink-0">
                <button
                    type="button"
                    onclick="prevOfficialAdSlide()"
                    class="w-6 h-6 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white transition flex items-center justify-center cursor-pointer border border-zinc-800"
                    title="Slide Sebelumnya"
                    aria-label="Slide Sebelumnya"
                >
                    <x-ui.icon name="chevron-left" class="w-3 h-3" />
                </button>
                <button
                    type="button"
                    onclick="nextOfficialAdSlide()"
                    class="w-6 h-6 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white transition flex items-center justify-center cursor-pointer border border-zinc-800"
                    title="Slide Selanjutnya"
                    aria-label="Slide Selanjutnya"
                >
                    <x-ui.icon name="chevron-right" class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- Carousel Track Wrapper -->
        <div class="relative overflow-hidden w-full">
            <div
                id="official-ad-track"
                class="flex transition-transform duration-500 ease-in-out"
                style="transform: translateX(0%);"
            >
                @foreach($officialAds as $index => $ad)
                    <div class="w-full shrink-0 p-3.5 sm:p-4 flex items-center gap-3.5 sm:gap-4">
                        <!-- Product Image -->
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-zinc-900 border border-zinc-800/90 p-1.5 flex items-center justify-center shrink-0 overflow-hidden shadow-inner">
                            <img
                                src="{{ $ad['image'] }}"
                                alt="{{ $ad['name'] }}"
                                class="w-full h-full object-contain"
                                loading="lazy"
                            />
                        </div>

                        <!-- Text Info: Cukup Nama Produk & Harga Saja (Warna Pada Teks) -->
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <h2 class="text-sm sm:text-base font-extrabold text-white tracking-tight leading-snug line-clamp-2">
                                {{ $ad['name'] }}
                            </h2>

                            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                <span class="text-base sm:text-lg font-black {{ $ad['price_color'] }} num tracking-tight">
                                    {{ $ad['price'] }}
                                </span>
                                @if(!empty($ad['original_price']))
                                    <span class="text-[11px] text-zinc-500 line-through num">
                                        {{ $ad['original_price'] }}
                                    </span>
                                @endif
                                @if(($ad['stock_status'] ?? 'ready') === 'habis')
                                    <span class="px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-400 border border-rose-500/25 text-[9px] font-extrabold shrink-0">
                                        Stok Habis
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2.5">
                                <button
                                    type="button"
                                    onclick="openOfficialProductModal({{ json_encode($ad) }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700/80 text-xs font-bold text-amber-400 hover:text-amber-300 transition cursor-pointer"
                                >
                                    <x-ui.icon name="tag" class="w-3 h-3 text-amber-400" />
                                    <span>Lihat Produk</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Bottom Dot Indicators (Teks dihapus, dots di tengah rapi) -->
        <div class="px-4 py-2.5 bg-zinc-950 border-t border-zinc-800/80 flex items-center justify-center">
            <div class="flex items-center gap-1.5" id="official-ad-dots">
                @foreach($officialAds as $index => $ad)
                    <button
                        type="button"
                        onclick="goToOfficialAdSlide({{ $index }})"
                        class="ad-dot transition-all duration-300 rounded-full h-1.5 {{ $index === 0 ? 'w-5 bg-amber-400' : 'w-1.5 bg-zinc-800 hover:bg-zinc-600' }}"
                        aria-label="Slide {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- BAR INFO SALDO DOMPET CUSTOMER (JIKA MEMILIKI SALDO REFUND) -->
    @if(isset($user) && $user->wallet_balance > 0)
        <div class="p-3.5 sm:p-4 rounded-2xl bg-gradient-to-r from-zinc-950 via-zinc-900 to-zinc-950 text-white border border-amber-500/40 shadow-md flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center font-black shrink-0 shadow-xs">
                    <x-ui.icon name="wallet" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] uppercase font-bold text-amber-400 tracking-wider block">Saldo Dompet Anda</span>
                    <span class="text-base sm:text-lg font-black text-white num leading-tight">Rp{{ number_format($user->wallet_balance, 0, ',', '.') }}</span>
                </div>
            </div>
            <a
                href="{{ route('customer.profile') }}"
                class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold text-xs transition shrink-0 inline-flex items-center gap-1 shadow-xs"
            >
                <span>Tarik / Kelola</span>
                <x-ui.icon name="chevron-right" class="w-3.5 h-3.5" />
            </a>
        </div>
    @endif

    <!-- CARD 1: Status Antrean Aktif / Reservasi Booking Aktif / 1 Riwayat Terakhir -->
    <x-ui.card class="{{ ($activeTicket || $activeBooking) ? 'ring-2 ring-amber-500/50 border-amber-500 shadow-md' : '' }}">
        <x-slot:header>
            <div class="flex items-center justify-between gap-2 w-full">
                <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                    <x-ui.icon name="{{ $activeTicket ? 'ticket' : ($activeBooking ? 'calendar' : 'history') }}" class="w-3.5 h-3.5 text-amber-600" />
                    <span>
                        @if($activeTicket)
                            Antrean Aktif Anda
                        @elseif($activeBooking)
                            Reservasi Booking Anda
                        @else
                            Riwayat Kunjungan Terbaru
                        @endif
                    </span>
                </h2>
                @if($activeTicket || $activeBooking)
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                @endif
            </div>
        </x-slot:header>

        @if($activeTicket)
            <!-- Tampilan Antrean Aktif -->
            <div class="space-y-3">
                <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-center">
                    <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Nomor Antrean Anda</p>
                    <p class="text-3xl font-extrabold text-zinc-950 num tracking-tight my-0.5">{{ $activeTicket->number }}</p>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold {{ $activeTicket->status->value === 'called' ? 'bg-amber-500 text-zinc-950 animate-pulse' : ($activeTicket->status->value === 'in_service' ? 'bg-emerald-500 text-white' : 'bg-zinc-900 text-white') }}">
                        {{ $activeTicket->status->label() }}
                    </div>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Barbershop:</span>
                        <span class="font-extrabold text-zinc-900">{{ $activeTicket->tenant?->name }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Cabang:</span>
                        <div class="text-right">
                            <span class="font-semibold text-zinc-800">{{ $activeTicket->branch?->name }}</span>
                            @if(!empty($activeTicket->branch?->maps_url))
                                <a
                                    href="{{ $activeTicket->branch->maps_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline ml-1"
                                >
                                    <x-ui.icon name="external-link" class="w-3 h-3" />
                                    <span>Maps</span>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Barber:</span>
                        <span class="font-semibold text-zinc-800">{{ $activeTicket->barber?->display_name ?? 'Siapa saja (Tercepat)' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Layanan:</span>
                        <span class="font-semibold text-zinc-800 text-right">{{ $activeTicket->items->pluck('service_name')->join(', ') }}</span>
                    </div>
                    @if($activeTicket->hairColor)
                        <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                            <span class="text-zinc-500">Warna Rambut:</span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-zinc-900">
                                <span class="w-2.5 h-2.5 rounded-full border border-black/20" @style(['background-color: ' . $activeTicket->hairColor->hex_code])></span>
                                <span>{{ $activeTicket->hairColor->name }}</span>
                            </span>
                        </div>
                    @endif
                </div>

                @if($activeTicket)
                    <div class="space-y-2">
                        <a
                            href="{{ route('customer.tickets.show', $activeTicket->id) }}"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs hover:bg-amber-400 transition shadow-md shadow-amber-500/20"
                        >
                            <span>Lihat Detail Pesanan &amp; Status Layanan</span>
                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>

                        @if(in_array($activeTicket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true))
                            <button
                                type="button"
                                data-dialog-id="modal-cancel-active-ticket"
                                onclick="document.getElementById(this.dataset.dialogId).showModal()"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-sm transition cursor-pointer"
                            >
                                <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                <span>Batalkan Antrean</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @elseif($activeBooking)
            <!-- Tampilan Reservasi Booking Aktif / Mendatang -->
            @php
                $bookingStatusColor = match ($activeBooking->status->value) {
                    'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'checked_in' => 'bg-blue-100 text-blue-800 border-blue-300',
                    default => 'bg-amber-100 text-amber-900 border-amber-300',
                };
            @endphp
            <div class="space-y-3">
                <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-center">
                    <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Kode Reservasi Booking</p>
                    <p class="text-3xl font-extrabold text-zinc-950 num tracking-tight my-0.5">{{ $activeBooking->code }}</p>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold border {{ $bookingStatusColor }}">
                        @if($activeBooking->queueTicket)
                            Masuk Antrean ({{ $activeBooking->queueTicket->number }})
                        @elseif(!empty($activeBooking->payment_proof_path))
                            Verifikasi Pembayaran
                        @else
                            {{ $activeBooking->status->label() }}
                        @endif
                    </div>
                    <p class="text-xs text-zinc-600 mt-2 font-medium">
                        Jadwal Kedatangan: <b class="text-zinc-900">{{ $activeBooking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</b>
                    </p>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Barbershop:</span>
                        <span class="font-extrabold text-zinc-900">{{ $activeBooking->tenant?->name }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Cabang:</span>
                        <div class="text-right">
                            <span class="font-semibold text-zinc-800">{{ $activeBooking->branch?->name }}</span>
                            @if(!empty($activeBooking->branch?->maps_url))
                                <a
                                    href="{{ $activeBooking->branch->maps_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline ml-1"
                                >
                                    <x-ui.icon name="external-link" class="w-3 h-3" />
                                    <span>Maps</span>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Barber:</span>
                        <span class="font-semibold text-zinc-800">{{ $activeBooking->barber?->display_name ?? 'Siapa saja (Tercepat)' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Layanan:</span>
                        <span class="font-semibold text-zinc-800 text-right">{{ $activeBooking->items->pluck('service_name')->join(', ') }}</span>
                    </div>
                    @if($activeBooking->hairColor)
                        <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                            <span class="text-zinc-500">Warna Rambut:</span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-zinc-900">
                                <span class="w-2.5 h-2.5 rounded-full border border-black/20" @style(['background-color: ' . $activeBooking->hairColor->hex_code])></span>
                                <span>{{ $activeBooking->hairColor->name }}</span>
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                        <span class="text-zinc-500">Estimasi Total:</span>
                        <span class="font-black text-zinc-900 num">Rp{{ number_format($activeBooking->estimated_total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <a
                        href="{{ route('customer.bookings.show', $activeBooking) }}"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs hover:bg-amber-400 transition shadow-md shadow-amber-500/20"
                    >
                        <span>Buka Detail &amp; Pembayaran Booking</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>

                    <a
                        href="{{ route('customer.history') }}"
                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition shadow-2xs"
                    >
                        <span>Buka Semua Riwayat Antrean &amp; Booking</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        @elseif($latestHistory)
            <!-- Tampilan 1 Riwayat Terakhir -->
            <div class="space-y-3">
                <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/90 text-xs">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <span class="px-2 py-0.5 rounded bg-zinc-900 text-amber-400 font-extrabold text-[11px] num">
                            {{ $latestHistory->number }}
                        </span>
                        <span class="text-[11px] text-zinc-500">
                            {{ $latestHistory->created_at->translatedFormat('d M Y, H:i') }}
                        </span>
                    </div>
                    <p class="font-extrabold text-zinc-900">{{ $latestHistory->tenant?->name }} · {{ $latestHistory->branch?->name }}</p>
                    <p class="text-zinc-600 mt-0.5">{{ $latestHistory->items->pluck('service_name')->join(', ') }}</p>
                    @php
                        $histStatusColor = match ($latestHistory->status->value) {
                            'completed' => 'text-emerald-600',
                            'cancelled', 'no_show' => 'text-zinc-400',
                            default => 'text-amber-600',
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-zinc-200/60 text-[11px]">
                        <span class="text-zinc-500">Status:</span>
                        <div class="flex items-center gap-1.5 font-extrabold {{ $histStatusColor }} bg-transparent">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
                            <span>{{ $latestHistory->status->label() }}</span>
                        </div>
                    </div>
                </div>

                @if($latestHistory->status->value === 'completed' && $latestHistory->order)
                    <a
                        href="{{ route('invoices.show', $latestHistory->order->number) }}"
                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold text-xs transition shadow-2xs"
                    >
                        <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                        <span>Lihat E-Invoice Terakhir</span>
                    </a>
                @endif

                <a
                    href="{{ route('customer.history') }}"
                    class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition shadow-2xs"
                >
                    <span>Buka Semua Riwayat Antrean &amp; Booking</span>
                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                </a>
            </div>
        @else
            <!-- Belum Ada Kunjungan -->
            <div class="py-6 text-center space-y-1.5">
                <div class="w-9 h-9 rounded-xl bg-zinc-100 text-zinc-400 grid place-items-center mx-auto">
                    <x-ui.icon name="ticket" class="w-4 h-4" />
                </div>
                <p class="font-extrabold text-xs text-zinc-800">Belum Ada Antrean atau Booking Aktif</p>
                <p class="text-[11px] text-zinc-400 max-w-xs mx-auto">
                    Arahkan kamera ke QR di barbershop untuk mengambil nomor antrean, atau lakukan booking jadwal pangkas rambut.
                </p>
            </div>
        @endif
    </x-ui.card>

    @if($activeTicket && $activeBooking && (int) $activeTicket->booking_id !== (int) $activeBooking->id)
        <!-- Banner Reservasi Booking Mendatang (Jika user memiliki antrean live & booking sekaligus) -->
        <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-300 shadow-2xs flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-xs font-extrabold text-amber-950">
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                    <span>Booking Mendatang: <b class="num">{{ $activeBooking->code }}</b></span>
                </div>
                <p class="text-[11px] text-zinc-600 mt-0.5 truncate">
                    {{ $activeBooking->branch?->name }} · {{ $activeBooking->scheduled_at->translatedFormat('d M Y, H:i') }} WIB
                </p>
            </div>
            <a
                href="{{ route('customer.bookings.show', $activeBooking) }}"
                class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shrink-0 transition"
            >
                Buka Booking
            </a>
        </div>
    @endif

    <!-- CARD 2: Scan QR Langsung di Tempat (Kamera atau Foto HP) -->
    <x-ui.card class="border-2 border-amber-500/30">
        <x-slot:header>
            <div class="flex items-center justify-between gap-2 w-full">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-xl bg-amber-500/15 text-amber-600 grid place-items-center shrink-0">
                        <x-ui.icon name="ticket" class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <h2 class="font-extrabold text-xs text-zinc-900">Ambil Antrean di Tempat</h2>
                        <p class="text-[11px] text-zinc-500">Scan QR di layar TV / meja kasir</p>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-extrabold border border-emerald-200/80">
                    Scan Cepat
                </span>
            </div>
        </x-slot:header>

        <div class="space-y-3">
            <!-- Tombol Aksi Scanner Kamera Saja -->
            <div>
                <button
                    type="button"
                    id="btn-open-camera"
                    class="w-full flex items-center justify-center gap-2 px-3.5 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold text-xs shadow-md transition cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Buka Kamera Scan QR</span>
                </button>
            </div>

            <!-- Area Kamera Scanner (Awalnya Tersembunyi) -->
            <div id="camera-scanner-wrapper" class="hidden p-3.5 rounded-2xl bg-zinc-950 text-white border border-zinc-800 space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold text-amber-400 flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        Kamera Aktif — Arahkan ke QR
                    </span>
                    <button
                        type="button"
                        id="btn-close-camera"
                        class="text-[11px] px-2 py-0.5 rounded-lg bg-white/10 hover:bg-rose-600 hover:text-white transition cursor-pointer font-bold"
                    >
                        Tutup
                    </button>
                </div>

                <div id="qr-video-reader" class="w-full overflow-hidden rounded-xl bg-black min-h-[220px] relative"></div>

                <p id="qr-scan-feedback" class="text-center text-[11px] text-zinc-400">
                    Posisikan QR code barbershop di dalam kotak pemindai
                </p>
            </div>

            <!-- Hasil Scan Alert -->
            <div id="scan-result-box" class="hidden p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
                <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                <span id="scan-result-text">QR berhasil dipindai! Mengalihkan ke barbershop...</span>
            </div>
        </div>
    </x-ui.card>

    @php
        $branchMapsData = [];
        if (! $tenant) {
            foreach ($tenants as $t) {
                $branchMapsData[$t->id] = [
                    'tenant_name' => $t->name,
                    'branches' => $t->branches->map(function ($b) use ($t) {
                        $mapsUrl = !empty($b->maps_url)
                            ? $b->maps_url
                            : ($b->lat && $b->lng
                                ? "https://www.google.com/maps?q={$b->lat},{$b->lng}"
                                : "https://www.google.com/maps/search/?api=1&query=" . urlencode(trim($t->name . ' ' . $b->name . ' ' . ($b->address ?? $t->address ?? ''))));

                        return [
                            'id' => $b->id,
                            'name' => $b->name,
                            'address' => $b->address ?: ($t->address ?: 'Alamat belum diatur'),
                            'maps_url' => $mapsUrl,
                            'phone' => $b->phone,
                        ];
                    })->values()->all(),
                ];
            }
        }
    @endphp

    @if($tenant)
        @php
            $activeBranches = $tenant->branches->where('is_active', true);
        @endphp
        <!-- CARD 3: Daftar Cabang Tenant yang Sedang Dipilih / Di-Scan -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                        <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-600" />
                        <span>Daftar Cabang {{ $tenant->name }}</span>
                    </h2>
                    <span class="text-[11px] text-zinc-400 num font-bold">{{ $activeBranches->count() }} Cabang</span>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($activeBranches as $branch)
                    @php
                        $branchMapsUrl = !empty($branch->maps_url)
                            ? $branch->maps_url
                            : ($branch->lat && $branch->lng
                                ? "https://www.google.com/maps?q={$branch->lat},{$branch->lng}"
                                : "https://www.google.com/maps/search/?api=1&query=" . urlencode(trim($tenant->name . ' ' . $branch->name . ' ' . ($branch->address ?? $tenant->address ?? ''))));
                        $bRatingInfo = $branchRatings[$branch->id] ?? null;
                    @endphp
                    <div class="py-3.5 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 truncate">{{ $branch->name }}</h3>
                                    @if($bRatingInfo && $bRatingInfo['avg'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] font-extrabold shrink-0 border border-amber-200">
                                            <x-ui.icon name="star" class="w-3 h-3 text-amber-500 fill-amber-500" />
                                            <span class="num">{{ number_format($bRatingInfo['avg'], 1) }}</span>
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    {{ $branch->address ?: ($tenant->address ?: 'Alamat belum diatur') }}
                                </p>
                                @if($branch->phone)
                                    <p class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1 font-mono">
                                        <x-ui.icon name="phone" class="w-3 h-3 text-zinc-400" />
                                        <span>{{ $branch->phone }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="pt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <a
                                href="{{ $branchMapsUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-800 font-extrabold text-xs hover:bg-sky-50 hover:border-sky-300 hover:text-sky-900 transition shadow-2xs"
                            >
                                <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-sky-600" />
                                <span>Buka Maps</span>
                            </a>

                            <a
                                href="{{ $tenant->customerPortalUrl($branch->code, ['tab' => 'booking']) }}"
                                class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold text-xs transition shadow-2xs"
                            >
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                                <span>Booking Jadwal</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Belum ada cabang aktif yang terdaftar untuk {{ $tenant->name }}.</p>
                @endforelse
            </div>
        </x-ui.card>
    @else
        <!-- CARD 3: Daftar Outlet Barbershop Mitra (Pilihan Cepat Mode Platform) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                        <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-600" />
                        <span>Pilihan Barbershop Mitra</span>
                    </h2>
                    <span class="text-[11px] text-zinc-400 num font-bold">{{ $tenants->count() }} Outlet</span>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($tenants as $t)
                    @php
                        $tenantBranches = $branchMapsData[$t->id]['branches'] ?? [];
                        $branchCount = count($tenantBranches);
                        $firstBranch = $tenantBranches[0] ?? null;
                        $singleMapsUrl = $firstBranch['maps_url'] ?? ("https://www.google.com/maps/search/?api=1&query=" . urlencode(trim($t->name . ' ' . ($t->address ?? ''))));
                        $tRatingInfo = $tenantRatings[$t->id] ?? null;
                    @endphp
                    <div class="py-3.5 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 truncate">{{ $t->name }}</h3>
                                    @if($tRatingInfo && $tRatingInfo['avg'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] font-extrabold shrink-0 border border-amber-200" title="Rata-rata rating dari {{ $tRatingInfo['staff_count'] }} staff barber">
                                            <x-ui.icon name="star" class="w-3 h-3 text-amber-500 fill-amber-500" />
                                            <span class="num">{{ number_format($tRatingInfo['avg'], 1) }}</span>
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500">
                                    {{ $t->branches->count() }} Cabang
                                    @if($t->branches->isNotEmpty())
                                        · {{ $t->branches->pluck('name')->join(', ') }}
                                    @endif
                                </p>
                                @if($t->address)
                                    <p class="text-[11px] text-zinc-400 mt-0.5 truncate">{{ $t->address }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="pt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @if($branchCount > 1)
                                <button
                                    type="button"
                                    onclick="openBranchMapsModal('{{ $t->id }}')"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-800 font-extrabold text-xs hover:bg-sky-50 hover:border-sky-300 hover:text-sky-900 transition shadow-2xs cursor-pointer"
                                >
                                    <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-sky-600" />
                                    <span>Buka Maps</span>
                                </button>
                            @else
                                <a
                                    href="{{ $singleMapsUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-800 font-extrabold text-xs hover:bg-sky-50 hover:border-sky-300 hover:text-sky-900 transition shadow-2xs"
                                >
                                    <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-sky-600" />
                                    <span>Buka Maps</span>
                                </a>
                            @endif

                            <a
                                href="{{ route('customer.booking', ['tenant_id' => $t->id]) }}"
                                class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold text-xs transition shadow-2xs"
                            >
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                                <span>Booking Jadwal</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Belum ada outlet barbershop aktif yang terdaftar.</p>
                @endforelse
            </div>
        </x-ui.card>
    @endif

    <!-- Modal Dialog Pilih Cabang untuk Maps -->
    <div id="branch-maps-modal" class="fixed inset-0 z-[70] hidden flex items-end sm:items-center justify-center p-0 sm:p-4 bg-zinc-950/70 backdrop-blur-xs transition-opacity" role="dialog" aria-modal="true">
        <div class="w-full max-w-md bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl border border-zinc-200 p-5 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 pb-3">
                <div class="min-w-0">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-700 font-extrabold text-[10px] uppercase tracking-wider mb-1 border border-sky-200/80">
                        <x-ui.icon name="map-pin" class="w-3 h-3 text-sky-600" />
                        <span>Google Maps</span>
                    </span>
                    <h3 id="modal-tenant-name" class="font-extrabold text-sm text-zinc-900 truncate">Pilih Cabang</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Pilih cabang yang ingin Anda tuju untuk membuka rute di Maps:</p>
                </div>
                <button
                    type="button"
                    onclick="closeBranchMapsModal()"
                    class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 hover:text-zinc-800 grid place-items-center transition shrink-0 cursor-pointer"
                    aria-label="Tutup"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div id="modal-branch-list" class="space-y-2">
                <!-- Diisi secara dinamis melalui JavaScript -->
            </div>

            <div class="pt-1">
                <button
                    type="button"
                    onclick="closeBranchMapsModal()"
                    class="w-full py-2.5 rounded-xl bg-zinc-100 text-zinc-700 font-extrabold text-xs hover:bg-zinc-200 transition text-center cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Script HTML5-QRCode Scanner -->
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <script id="customer-qr-scanner-script" data-scan-url="{{ route('customer.qr-scan') }}">
        document.addEventListener('DOMContentLoaded', function () {
            const scanBaseUrl = document.getElementById('customer-qr-scanner-script')?.dataset.scanUrl || '/customer/qr-scan';
            let html5QrCode = null;
            const cameraWrapper = document.getElementById('camera-scanner-wrapper');
            const btnOpenCamera = document.getElementById('btn-open-camera');
            const btnCloseCamera = document.getElementById('btn-close-camera');
            const feedbackEl = document.getElementById('qr-scan-feedback');
            const resultBox = document.getElementById('scan-result-box');
            const resultText = document.getElementById('scan-result-text');

            function handleQrScanned(decodedText) {
                if (html5QrCode && html5QrCode.isScanning) {
                    html5QrCode.stop().then(() => {
                        cameraWrapper.classList.add('hidden');
                    }).catch(console.error);
                }

                if (resultBox && resultText) {
                    resultBox.classList.remove('hidden');
                    resultText.textContent = 'QR Berhasil: ' + decodedText + ' — Membuka halaman...';
                }

                setTimeout(() => {
                    window.location.href = scanBaseUrl + "?qr=" + encodeURIComponent(decodedText.trim());
                }, 600);
            }

            if (btnOpenCamera) {
                btnOpenCamera.addEventListener('click', function () {
                    if (!window.Html5Qrcode) {
                        alert('Pemindai QR belum selesai dimuat, silakan coba beberapa saat lagi.');
                        return;
                    }

                    cameraWrapper.classList.remove('hidden');
                    if (!html5QrCode) {
                        html5QrCode = new Html5Qrcode('qr-video-reader');
                    }

                    const config = { fps: 10, qrbox: { width: 220, height: 220 } };
                    html5QrCode.start(
                        { facingMode: 'environment' },
                        config,
                        handleQrScanned,
                        (errorMessage) => {}
                    ).catch((err) => {
                        if (feedbackEl) {
                            feedbackEl.textContent = 'Izin kamera ditolak atau kamera tidak tersedia: ' + err;
                            feedbackEl.classList.add('text-rose-500');
                        }
                    });
                });
            }

            if (btnCloseCamera) {
                btnCloseCamera.addEventListener('click', function () {
                    if (html5QrCode && html5QrCode.isScanning) {
                        html5QrCode.stop().then(() => {
                            cameraWrapper.classList.add('hidden');
                        }).catch(console.error);
                    } else {
                        cameraWrapper.classList.add('hidden');
                    }
                });
            }
        });
    </script>

    <!-- Script Modal Pilih Cabang untuk Buka Google Maps -->
    <script type="application/json" id="branch-maps-json">@json($branchMapsData)</script>
    @if($activeTicket && in_array($activeTicket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true))
        <dialog
            id="modal-cancel-active-ticket"
            onclick="if(event.target===this)this.close()"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
        >
            <form method="POST" action="{{ route('customer.tickets.cancel', $activeTicket->id) }}" class="p-5 space-y-4">
                @csrf
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 grid place-items-center shrink-0">
                        <x-ui.icon name="alert-triangle" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-extrabold text-sm text-zinc-950">Batalkan Nomor Antrean?</h3>
                        <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                            Apakah Anda yakin ingin membatalkan antrean <b class="text-zinc-900 num">{{ $activeTicket->number }}</b> di <b class="text-zinc-900">{{ $activeTicket->tenant?->name }}</b>?
                        </p>
                    </div>
                </div>

                @php
                    $activeOrder = $activeTicket->order;
                    $paidPayment = $activeOrder?->payments?->firstWhere('status', 'success') ?? $activeOrder?->payments?->first();
                    $paidMethod = $paidPayment?->method;
                    $isTicketPaid = ($activeOrder !== null && $activeOrder->payment_status === \App\Enums\PaymentStatus::Paid)
                        || ($activeTicket->booking !== null && (
                            $activeTicket->booking->xendit_paid_at !== null
                            || $activeTicket->booking->payment_approved_at !== null
                            || in_array($activeTicket->booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::CheckedIn, \App\Enums\BookingStatus::Completed], true)
                            || ! empty($activeTicket->booking->payment_proof_path)
                        ));
                    $isQrisPaid = $isTicketPaid && (
                        $activeTicket->booking_id !== null
                        || $paidMethod === \App\Enums\PaymentMethod::Qris
                        || (is_object($paidMethod) && $paidMethod->value === 'qris')
                        || $paidMethod === 'qris'
                        || $paidMethod === \App\Enums\PaymentMethod::Transfer
                        || (is_object($paidMethod) && $paidMethod->value === 'transfer')
                        || $paidMethod === 'transfer'
                    );
                    $isCashPaid = $isTicketPaid && ! $isQrisPaid;
                    $paidAmount = $activeOrder?->paid_total ?? (int) ($activeTicket->booking?->estimated_total ?? $activeTicket->items->sum('price'));
                @endphp

                @if($isQrisPaid)
                    <div class="flex items-start gap-2.5 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-left">
                        <x-ui.icon name="wallet" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="space-y-0.5 min-w-0">
                            <h4 class="font-bold text-xs text-emerald-950">Pengembalian Dana ke Saldo Dompet</h4>
                            <p class="text-[11px] text-emerald-800 leading-relaxed">
                                Dana <strong class="num font-black text-emerald-950">Rp{{ number_format($paidAmount, 0, ',', '.') }}</strong> otomatis masuk ke Saldo Dompet Anda dan siap dipakai booking lagi atau dicairkan.
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
                            Karena Anda telah membayar <strong class="num">Rp{{ number_format($paidAmount, 0, ',', '.') }}</strong> secara <b>Tunai (Cash)</b> di kasir, Anda <b>tidak perlu mengisi nomor rekening</b>. Uang tunai Anda dapat diambil langsung di meja kasir outlet saat pembatalan.
                        </p>
                    </div>
                @endif

                <div>
                    <label for="cancel-active-ticket-reason" class="block text-[11px] font-bold text-zinc-600 mb-1">
                        Alasan Pembatalan <span class="text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        id="cancel-active-ticket-reason"
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
                        data-dialog-id="modal-cancel-active-ticket"
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

    <script>
        const branchMapsData = JSON.parse(document.getElementById('branch-maps-json')?.textContent || '{}');

        window.openBranchMapsModal = function (tenantId) {
            const data = branchMapsData[tenantId];
            if (!data) return;

            const modal = document.getElementById('branch-maps-modal');
            const titleEl = document.getElementById('modal-tenant-name');
            const listEl = document.getElementById('modal-branch-list');

            if (!modal || !titleEl || !listEl) return;

            titleEl.textContent = data.tenant_name;
            listEl.innerHTML = '';

            data.branches.forEach((b) => {
                const item = document.createElement('a');
                item.href = b.maps_url;
                item.target = '_blank';
                item.rel = 'noopener noreferrer';
                item.className = 'flex items-start justify-between gap-3 p-3 rounded-xl border border-zinc-200/90 hover:border-sky-500 hover:bg-sky-50/40 transition group cursor-pointer shadow-2xs';

                const escapedName = escapeHtml(b.name);
                const escapedAddress = escapeHtml(b.address);

                item.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
                            <span class="font-extrabold text-xs text-zinc-900 group-hover:text-sky-800">${escapedName}</span>
                        </div>
                        <p class="text-[11px] text-zinc-500 mt-1 line-clamp-2 leading-relaxed">${escapedAddress}</p>
                    </div>
                    <div class="shrink-0 flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-sky-600 text-white font-extrabold text-[11px] group-hover:bg-sky-500 transition shadow-2xs">
                        <span>Rute</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </div>
                `;
                listEl.appendChild(item);
            });

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeBranchMapsModal = function () {
            const modal = document.getElementById('branch-maps-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('branch-maps-modal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        window.closeBranchMapsModal();
                    }
                });
            }
        });
    </script>

    <!-- MODAL DETAIL PRODUK OFFICIAL BARBEROS -->
    <div
        id="modal-official-product"
        class="fixed inset-0 w-screen h-screen min-h-[100dvh] z-[100] bg-black/90 backdrop-blur-md flex items-center justify-center p-3 sm:p-5 overflow-y-auto hidden transition-all duration-200"
        role="dialog"
        aria-modal="true"
    >
        <div class="bg-zinc-900 border border-zinc-800 text-white rounded-3xl w-full max-w-md max-h-[92vh] overflow-y-auto p-4 sm:p-5 shadow-2xl relative my-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 border-b border-zinc-800/80 pb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-400">
                        BARBEROS OFFICIAL STORE
                    </span>
                </div>
                <button
                    type="button"
                    onclick="closeOfficialProductModal()"
                    class="p-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white transition cursor-pointer shrink-0"
                    aria-label="Tutup"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <!-- Body Modal -->
            <div class="mt-3.5 space-y-3.5 text-xs">
                <!-- Showcase Gambar Utama (Ukuran Besar & Jelas) -->
                <div class="relative w-full h-48 sm:h-56 rounded-2xl bg-zinc-950 border border-zinc-800 p-3 flex items-center justify-center overflow-hidden shadow-inner">
                    <img
                        id="modal-ad-main-img"
                        src=""
                        alt="Foto Produk"
                        class="w-full h-full object-contain transition-all duration-300"
                    />
                </div>

                <!-- Galeri Thumbnail (Jika Admin Upload Banyak Gambar) -->
                <div id="modal-ad-gallery-wrapper" class="hidden">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1">
                            <x-ui.icon name="image" class="w-3 h-3 text-amber-400" />
                            <span>Pilihan Sudut / Galeri:</span>
                        </span>
                        <span class="text-[9px] text-zinc-500">Klik untuk ganti foto</span>
                    </div>
                    <div id="modal-ad-thumbnails" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
                        <!-- Thumbnail buttons disuntik via JS -->
                    </div>
                </div>

                <!-- Judul & Harga Produk -->
                <div>
                    <h3 id="modal-ad-title" class="text-base sm:text-lg font-black text-white leading-snug">
                        Nama Produk
                    </h3>

                    <!-- Harga Promo & Status -->
                    <div class="p-3 mt-2 rounded-2xl bg-zinc-950 border border-zinc-800 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] text-zinc-500 uppercase tracking-wider font-bold block">Harga Promo Official</span>
                            <div class="flex items-baseline gap-2 mt-0.5">
                                <span id="modal-ad-price" class="text-xl font-black text-amber-400 num">Rp0</span>
                                <span id="modal-ad-original-price" class="text-xs text-zinc-500 line-through num">Rp0</span>
                            </div>
                        </div>
                        <span id="modal-ad-stock-badge" class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold shrink-0">
                            Ready Stock
                        </span>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div>
                    <h4 class="text-xs font-bold text-zinc-300 uppercase tracking-wider mb-1">Deskripsi Produk</h4>
                    <p id="modal-ad-desc" class="text-zinc-400 text-xs leading-relaxed">
                        Deskripsi
                    </p>
                </div>

                <!-- Keunggulan / Fitur -->
                <div>
                    <h4 class="text-xs font-bold text-zinc-300 uppercase tracking-wider mb-1.5">Keunggulan Utama</h4>
                    <ul id="modal-ad-features" class="space-y-1.5 text-xs text-zinc-300">
                        <!-- Disuntik lewat JS -->
                    </ul>
                </div>

                <!-- Jaminan Keaslian -->
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center gap-2.5">
                    <x-ui.icon name="shield-check" class="w-5 h-5 text-amber-400 shrink-0" />
                    <p class="text-[11px] text-zinc-300 leading-tight">
                        <strong class="text-amber-400">100% Produk Original BarberOS.</strong> Dijamin kualitas premium resmi &amp; garansi kepuasan pelanggan.
                    </p>
                </div>

                <!-- CTA Actions -->
                <div class="pt-1 flex flex-col gap-2">
                    <a
                        id="modal-ad-buy-btn"
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-md transition cursor-pointer"
                    >
                        <x-ui.icon name="external-link" class="w-4 h-4 text-zinc-950" />
                        <span id="modal-ad-buy-label">Beli Sekarang</span>
                    </a>
                    <button
                        type="button"
                        onclick="closeOfficialProductModal()"
                        class="w-full py-2.5 px-4 rounded-xl border border-zinc-700 bg-zinc-800 hover:bg-zinc-700 hover:border-zinc-600 text-zinc-200 hover:text-white font-extrabold text-xs transition cursor-pointer flex items-center justify-center gap-1.5 shadow-xs"
                    >
                        <x-ui.icon name="x" class="w-3.5 h-3.5 text-zinc-400" />
                        <span>Tutup</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT BANNER SLIDER & MODAL PRODUK OFFICIAL -->
    <script>
        let currentAdIndex = 0;
        const totalAdSlides = {{ count($officialAds) }};
        let adSlideInterval = null;
        let isAdPaused = false;

        function updateOfficialAdSlide() {
            const track = document.getElementById('official-ad-track');
            if (!track) return;
            track.style.transform = `translateX(-${currentAdIndex * 100}%)`;

            // Update dots
            const dots = document.querySelectorAll('#official-ad-dots .ad-dot');
            dots.forEach((dot, idx) => {
                if (idx === currentAdIndex) {
                    dot.className = 'ad-dot transition-all duration-300 rounded-full h-1.5 w-5 bg-amber-400';
                } else {
                    dot.className = 'ad-dot transition-all duration-300 rounded-full h-1.5 w-1.5 bg-zinc-800 hover:bg-zinc-600';
                }
            });
        }

        window.nextOfficialAdSlide = function() {
            currentAdIndex = (currentAdIndex + 1) % totalAdSlides;
            updateOfficialAdSlide();
        };

        window.prevOfficialAdSlide = function() {
            currentAdIndex = (currentAdIndex - 1 + totalAdSlides) % totalAdSlides;
            updateOfficialAdSlide();
        };

        window.goToOfficialAdSlide = function(index) {
            currentAdIndex = index;
            updateOfficialAdSlide();
            resetOfficialAdTimer();
        };

        function startOfficialAdTimer() {
            stopOfficialAdTimer();
            adSlideInterval = setInterval(() => {
                if (!isAdPaused) {
                    window.nextOfficialAdSlide();
                }
            }, {{ $slideDurationSeconds * 1000 }}); // Dinamis sesuai pengaturan Superadmin
        }

        function stopOfficialAdTimer() {
            if (adSlideInterval) {
                clearInterval(adSlideInterval);
                adSlideInterval = null;
            }
        }

        function resetOfficialAdTimer() {
            startOfficialAdTimer();
        }

        // Modal Produk
        window.openOfficialProductModal = function(ad) {
            isAdPaused = true;
            const modal = document.getElementById('modal-official-product');
            if (!modal) return;

            // Galeri & Gambar Utama
            const images = Array.isArray(ad.images) && ad.images.length > 0 ? ad.images : [ad.image];
            const mainImg = document.getElementById('modal-ad-main-img');
            mainImg.src = images[0];

            const galleryWrapper = document.getElementById('modal-ad-gallery-wrapper');
            const thumbsList = document.getElementById('modal-ad-thumbnails');
            thumbsList.innerHTML = '';

            if (images.length > 1) {
                galleryWrapper.classList.remove('hidden');
                images.forEach((imgUrl, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `w-12 h-12 rounded-xl bg-zinc-950 border p-1 shrink-0 transition cursor-pointer ${idx === 0 ? 'border-amber-500 ring-2 ring-amber-500/40' : 'border-zinc-800 hover:border-zinc-700'}`;
                    btn.innerHTML = `<img src="${imgUrl}" class="w-full h-full object-contain" alt="Foto ${idx + 1}" />`;
                    btn.onclick = () => {
                        mainImg.src = imgUrl;
                        thumbsList.querySelectorAll('button').forEach(b => {
                            b.className = 'w-12 h-12 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 p-1 shrink-0 transition cursor-pointer';
                        });
                        btn.className = 'w-12 h-12 rounded-xl bg-zinc-950 border border-amber-500 ring-2 ring-amber-500/40 p-1 shrink-0 transition cursor-pointer';
                    };
                    thumbsList.appendChild(btn);
                });
            } else {
                galleryWrapper.classList.add('hidden');
            }

            document.getElementById('modal-ad-title').textContent = ad.name;
            document.getElementById('modal-ad-price').textContent = ad.price;
            document.getElementById('modal-ad-original-price').textContent = ad.original_price || '';
            document.getElementById('modal-ad-desc').textContent = ad.desc || '';

            const featuresList = document.getElementById('modal-ad-features');
            featuresList.innerHTML = '';
            (ad.features || []).forEach(f => {
                const li = document.createElement('li');
                li.className = 'flex items-center gap-2';
                li.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span><span>${escapeHtml(f)}</span>`;
                featuresList.appendChild(li);
            });

            // Status Stok
            const isHabis = ad.stock_status === 'habis';
            const stockBadge = document.getElementById('modal-ad-stock-badge');
            if (stockBadge) {
                if (isHabis) {
                    stockBadge.textContent = 'Stok Habis';
                    stockBadge.className = 'px-2.5 py-1 rounded-full bg-rose-500/15 text-rose-400 border border-rose-500/25 text-[10px] font-extrabold shrink-0';
                } else {
                    stockBadge.textContent = 'Ready Stock';
                    stockBadge.className = 'px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold shrink-0';
                }
            }

            // Tautan Pembelian Langsung (Marketplace / Toko Online)
            const targetUrl = (ad.url || ad.link || '').trim() || '#';
            const buyBtn = document.getElementById('modal-ad-buy-btn');
            const buyLabel = document.getElementById('modal-ad-buy-label');
            if (buyBtn) {
                buyBtn.href = targetUrl;
                if (targetUrl !== '#') {
                    buyBtn.target = '_blank';
                    buyBtn.rel = 'noopener noreferrer';
                } else {
                    buyBtn.removeAttribute('target');
                }
            }
            if (buyLabel) {
                buyLabel.textContent = isHabis ? 'Lihat Detail Produk' : 'Beli Sekarang';
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeOfficialProductModal = function() {
            const modal = document.getElementById('modal-official-product');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            isAdPaused = false;
        };

        document.addEventListener('DOMContentLoaded', () => {
            const sliderEl = document.getElementById('official-ad-slider');
            if (sliderEl) {
                startOfficialAdTimer();

                // Pause on hover
                sliderEl.addEventListener('mouseenter', () => { isAdPaused = true; });
                sliderEl.addEventListener('mouseleave', () => { isAdPaused = false; });

                // Touch swipe support for mobile
                let touchStartX = 0;
                let touchEndX = 0;

                sliderEl.addEventListener('touchstart', (e) => {
                    touchStartX = e.changedTouches[0].screenX;
                    isAdPaused = true;
                }, { passive: true });

                sliderEl.addEventListener('touchend', (e) => {
                    touchEndX = e.changedTouches[0].screenX;
                    isAdPaused = false;
                    const diff = touchStartX - touchEndX;
                    if (Math.abs(diff) > 40) {
                        if (diff > 0) {
                            window.nextOfficialAdSlide();
                        } else {
                            window.prevOfficialAdSlide();
                        }
                        resetOfficialAdTimer();
                    }
                }, { passive: true });
            }

            const modal = document.getElementById('modal-official-product');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        window.closeOfficialProductModal();
                    }
                });
            }
        });
    </script>
</x-layouts.customer>
