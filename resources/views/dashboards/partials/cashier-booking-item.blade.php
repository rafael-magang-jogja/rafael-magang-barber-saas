@props([
    'booking',
    'group' => 'today', // 'today', 'past', 'future'
    'cashierColorOptions' => [],
])

@php
    $bookingHasColoring = $booking->items->contains(
        fn ($item) => stripos($item->service_name, 'color') !== false || stripos($item->service_name, 'warna') !== false
    );

    $isPast = $booking->scheduled_at->toDateString() < now()->toDateString();
    $isToday = $booking->scheduled_at->isToday();
    $isFuture = $booking->scheduled_at->toDateString() > now()->toDateString();

    $badgeClass = match(true) {
        $isPast => 'bg-rose-50 text-rose-700 border-rose-200/80',
        $isToday => 'bg-amber-50 text-amber-800 border-amber-200/80',
        default => 'bg-sky-50 text-sky-800 border-sky-200/80',
    };

    $barberName = $booking->barber?->display_name ?? $booking->barber?->user?->name ?? 'Siapa Saja (Auto)';
    $serviceNames = $booking->items->pluck('service_name')->join(', ') ?: 'Layanan Cukur';
@endphp

<div class="p-3.5 sm:p-4 rounded-2xl border border-zinc-200/80 bg-white hover:border-zinc-300 hover:shadow-xs transition-all space-y-3">
    <!-- Baris 1: Header Kode Booking, Status Pill, & Jadwal Waktu -->
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 pb-2.5 border-b border-zinc-100">
        <div class="flex items-center gap-2">
            <!-- Kode Booking -->
            <span class="px-2.5 py-0.5 rounded-lg bg-zinc-950 text-amber-400 text-xs font-mono font-extrabold tracking-wide shadow-2xs">
                {{ $booking->code }}
            </span>

            <!-- Status Pill -->
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badgeClass }}">
                @if($isPast)
                    <x-ui.icon name="clock" class="w-3 h-3 text-rose-500" />
                    <span>Terlewat</span>
                @elseif($isToday)
                    <x-ui.icon name="calendar" class="w-3 h-3 text-amber-600" />
                    <span>Hari Ini</span>
                @else
                    <x-ui.icon name="calendar" class="w-3 h-3 text-sky-600" />
                    <span>Mendatang</span>
                @endif
            </span>
        </div>

        <!-- Jadwal Waktu Tanggal & Jam -->
        <div class="text-xs text-zinc-500 flex flex-wrap items-center gap-1.5 num">
            <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
            <span class="font-bold text-zinc-800">{{ $booking->scheduled_at->translatedFormat('d M Y') }}</span>
            <span class="text-zinc-300">·</span>
            <span class="font-bold text-zinc-800">{{ $booking->scheduled_at->format('H:i') }} WIB</span>
            @if($isPast)
                <span class="text-[11px] text-rose-600 font-semibold bg-rose-50 px-1.5 py-0.2 rounded border border-rose-100">({{ $booking->scheduled_at->diffForHumans() }})</span>
            @endif
        </div>
    </div>

    <!-- Baris 2: Pelanggan, Barber & Rincian Layanan -->
    <div class="space-y-2 py-0.5">
        <!-- Nama Pelanggan & No HP -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-900 text-xs font-black flex items-center justify-center shrink-0">
                {{ strtoupper(substr($booking->customerProfile?->name ?? 'P', 0, 1)) }}
            </span>
            <span class="font-bold text-sm text-zinc-900">{{ $booking->customerProfile?->name ?? 'Pelanggan' }}</span>

            @if($booking->customerProfile?->phone)
                <span class="inline-flex items-center gap-1 text-xs text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded-lg num">
                    <x-ui.icon name="phone" class="w-3 h-3 text-zinc-400" />
                    <span>{{ $booking->customerProfile->phone }}</span>
                </span>
            @else
                <span class="text-[11px] px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-400 font-medium">
                    Tanpa No. HP
                </span>
            @endif
        </div>

        <!-- Dua Kartu Mini: Barber & Layanan/Harga -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs pt-1">
            <div class="flex items-center gap-2 bg-zinc-50 border border-zinc-200/70 p-2 rounded-xl">
                <x-ui.icon name="scissors" class="w-4 h-4 text-zinc-400 shrink-0" />
                <div class="min-w-0">
                    <span class="text-zinc-400 text-[10px] uppercase font-bold block">Barber</span>
                    <span class="font-bold text-zinc-800 truncate block">{{ $barberName }}</span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-1.5 bg-zinc-50 border border-zinc-200/70 p-2 rounded-xl">
                <div class="min-w-0">
                    <span class="text-zinc-400 text-[10px] uppercase font-bold block">Layanan</span>
                    <span class="font-bold text-zinc-800 truncate block">{{ $serviceNames }}</span>
                </div>
                <span class="font-black text-amber-900 num bg-amber-100/70 border border-amber-200/80 px-2 py-0.5 rounded-lg shrink-0">
                    Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}
                </span>
            </div>
        </div>

        <!-- Catatan Tambahan Pelanggan -->
        @if(!empty($booking->notes))
            <p class="text-xs text-zinc-600 bg-amber-50/50 p-2 rounded-xl border border-amber-200/60 italic mt-1">
                <span class="font-bold not-italic text-amber-900">Catatan:</span> {{ $booking->notes }}
            </p>
        @endif
    </div>

    <!-- Baris 3: Status Pembayaran Transfer & Tindakan Kasir -->
    <div class="pt-2.5 border-t border-zinc-100 space-y-2.5">
        @if($booking->queueTicket)
            <!-- SUDAH DI-APPROVE & MASUK ANTREAN SESUAI JAMNYA -->
            <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-black text-xs grid place-items-center shrink-0">
                        <x-ui.icon name="check" class="w-4 h-4" />
                    </span>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-emerald-950">Lunas &amp; Masuk Antrean:</span>
                            <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-extrabold text-xs num">
                                {{ $booking->queueTicket->number }}
                            </span>
                        </div>
                        <p class="text-[11px] text-emerald-800 mt-0.5 leading-relaxed">
                            Akan dipanggil sesuai jam <b class="text-emerald-950">{{ $booking->scheduled_at->format('H:i') }} WIB</b> (tidak memotong antrean walk-in sebelum jamnya).
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('customer.tickets.show', $booking->queueTicket->id) }}"
                    target="_blank"
                    class="shrink-0 px-3 py-1.5 rounded-xl bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold transition flex items-center justify-center gap-1"
                >
                    <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                    <span>Lihat Tiket</span>
                </a>
            </div>
        @elseif($booking->payment_approved_at !== null)
            <!-- SUDAH DI-APPROVE UNTUK HARI LAIN / MENDATANG -->
            <div class="p-3.5 rounded-2xl bg-emerald-50/80 border border-emerald-300 text-emerald-950 space-y-1">
                <div class="flex items-center gap-2">
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span class="font-black text-xs text-emerald-950">Pembayaran Terverifikasi Lunas (Booking Hari Lain)</span>
                </div>
                <p class="text-[11px] text-emerald-800 leading-relaxed">
                    Jadwal: <b>{{ $booking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</b>. Saat hari H tiba, booking ini akan otomatis masuk ke antrean sesuai jam jadwalnya.
                </p>
            </div>
        @elseif(!empty($booking->payment_proof_path))
            <!-- KOTAK BUKTI TRANSFER MENUNGGU PEMERIKSAAN KASIR -->
            <div class="p-3.5 rounded-2xl bg-amber-500/10 border-2 border-amber-400 space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                        <span class="font-extrabold text-xs text-amber-950">Bukti Transfer Bank Masuk!</span>
                    </div>
                    <span class="text-[11px] text-zinc-500 num">
                        {{ $booking->payment_proof_uploaded_at?->diffForHumans() }}
                    </span>
                </div>

                <div class="flex items-center gap-3 bg-white p-2.5 rounded-xl border border-amber-200">
                    <button
                        type="button"
                        onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').showModal()"
                        class="shrink-0 group relative block cursor-pointer rounded-lg overflow-hidden border border-zinc-200 focus:outline-none focus:ring-2 focus:ring-amber-500 text-left"
                        title="Klik untuk melihat bukti transfer"
                    >
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::url($booking->payment_proof_path) }}"
                            alt="Struk Transfer"
                            class="w-14 h-14 object-cover group-hover:scale-105 group-hover:opacity-85 transition duration-150"
                        />
                        <div class="absolute inset-0 bg-black/35 rounded-lg opacity-0 group-hover:opacity-100 flex items-center justify-center transition">
                            <x-ui.icon name="eye" class="w-4 h-4 text-white" />
                        </div>
                    </button>
                    <div class="min-w-0 flex-1 text-xs">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] uppercase font-bold text-zinc-400 block">Total Tagihan Transfer</span>
                            <button
                                type="button"
                                onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').showModal()"
                                class="text-[11px] font-bold text-amber-700 hover:text-amber-800 hover:underline inline-flex items-center gap-1 cursor-pointer"
                            >
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                <span>Lihat Bukti</span>
                            </button>
                        </div>
                        <p class="font-black text-sm text-zinc-900 num">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</p>
                        <span class="text-[11px] text-zinc-500 block truncate">Transfer Rekening oleh {{ $booking->customerProfile?->name }}</span>
                    </div>
                </div>

                <!-- Tombol Approve & Reject Kasir -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                    <!-- Tombol Buka Modal Konfirmasi Approve -->
                    <button
                        type="button"
                        onclick="document.getElementById('modal-approve-booking-{{ $booking->id }}').showModal()"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white text-xs font-black shadow-2xs hover:shadow-xs transition cursor-pointer"
                    >
                        <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
                        <span>{{ $isToday ? "Approve & Masukkan Antrean (Sesuai Jam {$booking->scheduled_at->format('H:i')})" : "Approve Pembayaran (Lunas untuk {$booking->scheduled_at->translatedFormat('d M')})" }}</span>
                    </button>

                    <!-- Tombol Buka Dialog Tolak Bukti (Solid Background) -->
                    <button
                        type="button"
                        onclick="document.getElementById('modal-reject-booking-{{ $booking->id }}').showModal()"
                        class="px-3.5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-[0.98] text-white text-xs font-black transition cursor-pointer shrink-0 inline-flex items-center justify-center gap-1.5 shadow-2xs hover:shadow-xs"
                        title="Tolak bukti pembayaran jika salah / mutasi belum masuk"
                    >
                        <x-ui.icon name="x" class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span>Tolak Bukti</span>
                    </button>
                </div>
            </div>

            <!-- 1. Modal Preview Bukti Transfer (Popup Seperti Superadmin Tanpa Tab Baru) -->
            <dialog
                id="modal-proof-booking-{{ $booking->id }}"
                onclick="if(event.target===this)this.close()"
                class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/75 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-2xl mx-auto my-auto border border-zinc-200 bg-white outline-none focus:outline-none ring-0"
            >
                <div class="p-4 sm:p-5 border-b border-zinc-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-black text-sm sm:text-base text-zinc-900">Bukti Transfer Pembayaran</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Booking <span class="font-mono font-bold text-zinc-800">{{ $booking->code }}</span> &middot; {{ $booking->customerProfile?->name ?? 'Pelanggan Umum' }} &middot; {{ $booking->payment_proof_uploaded_at?->diffForHumans() }}
                        </p>
                    </div>
                    <button
                        type="button"
                        onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').close()"
                        class="p-2 rounded-xl text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer"
                        title="Tutup Preview"
                    >
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 sm:p-5 space-y-3.5">
                    <!-- Viewport Gambar Bukti Gelap Kontras -->
                    <div class="bg-zinc-950 rounded-2xl h-[46vh] sm:h-[52vh] flex items-center justify-center overflow-auto relative select-none border border-zinc-800 p-2">
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::url($booking->payment_proof_path) }}"
                            alt="Struk Transfer {{ $booking->code }}"
                            class="max-h-full max-w-full rounded-lg object-contain shadow-md"
                        />
                    </div>

                    <!-- Ringkasan Info Transaksi -->
                    <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Pelanggan:</span>
                            <strong class="text-zinc-900">{{ $booking->customerProfile?->name ?? 'Pelanggan Umum' }} ({{ $booking->customerProfile?->phone ?? '-' }})</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Layanan:</span>
                            <strong class="text-zinc-900">{{ $booking->service?->name ?? 'Layanan' }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Jadwal Booking:</span>
                            <strong class="text-zinc-900">{{ $booking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</strong>
                        </div>
                        <div class="pt-1.5 border-t border-zinc-200/80 flex items-center justify-between">
                            <span class="text-zinc-600 font-bold">Total Tagihan Transfer:</span>
                            <strong class="text-emerald-700 font-black text-sm num">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal Preview dengan Aksi Cepat -->
                <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-2 rounded-b-3xl">
                    <button
                        type="button"
                        onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').close()"
                        class="px-4 py-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-100 text-zinc-700 font-bold text-xs transition cursor-pointer"
                    >
                        Tutup
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').close(); document.getElementById('modal-reject-booking-{{ $booking->id }}').showModal();"
                            class="px-3.5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition cursor-pointer inline-flex items-center gap-1.5 shadow-2xs"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>Tolak Bukti</span>
                        </button>
                        <button
                            type="button"
                            onclick="document.getElementById('modal-proof-booking-{{ $booking->id }}').close(); document.getElementById('modal-approve-booking-{{ $booking->id }}').showModal();"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs transition cursor-pointer inline-flex items-center gap-1.5 shadow-xs"
                        >
                            <x-ui.icon name="check" class="w-3.5 h-3.5 stroke-[3]" />
                            <span>Konfirmasi Lunas</span>
                        </button>
                    </div>
                </div>
            </dialog>

            <!-- 2. Modal Konfirmasi Approve Pembayaran (Popup Konfirmasi Sebelum Disetujui) -->
            <dialog
                id="modal-approve-booking-{{ $booking->id }}"
                onclick="if(event.target===this)this.close()"
                class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs outline-none focus:outline-none ring-0"
            >
                <form method="POST" action="{{ route('cashier.bookings.approve-payment', $booking) }}">
                    @csrf
                    @if($bookingHasColoring && !empty($cashierColorOptions))
                        <input type="hidden" name="hair_color_id" value="{{ $booking->hair_color_id }}">
                    @endif

                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-2xs">
                                <x-ui.icon name="check-circle" class="w-6 h-6 text-emerald-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-base text-zinc-900">Konfirmasi Terima Pembayaran</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Pastikan mutasi rekening bank barbershop sudah menerima dana transfer ini.</p>
                            </div>
                        </div>

                        <!-- Detail Ringkasan Booking & Tagihan -->
                        <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500 font-medium">No. Booking:</span>
                                <strong class="font-mono text-zinc-900 font-black">{{ $booking->code }}</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500 font-medium">Pelanggan:</span>
                                <strong class="text-zinc-900">{{ $booking->customerProfile?->name ?? 'Pelanggan Umum' }}</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500 font-medium">Layanan:</span>
                                <strong class="text-zinc-900">{{ $booking->service?->name ?? 'Layanan' }}</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500 font-medium">Jadwal:</span>
                                <span class="font-bold text-zinc-800">{{ $booking->scheduled_at->translatedFormat('l, d M Y · H:i') }} WIB</span>
                            </div>
                            <div class="pt-2 border-t border-zinc-200/80 flex items-center justify-between">
                                <span class="text-zinc-600 font-bold">Total Pembayaran:</span>
                                <strong class="text-emerald-700 font-black text-base num">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</strong>
                            </div>
                        </div>

                        <!-- Banner Penjelasan Antrean Sesuai Jadwal -->
                        @if($isToday)
                            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2">
                                <x-ui.icon name="alert-circle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <p class="leading-relaxed">
                                    Booking dijadwalkan untuk <b>Hari Ini ({{ $booking->scheduled_at->format('H:i') }} WIB)</b>. Setelah disetujui, booking langsung dibuatkan tiket dan masuk ke daftar antrean kasir.
                                </p>
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex items-start gap-2">
                                <x-ui.icon name="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                <p class="leading-relaxed">
                                    Booking dijadwalkan untuk <b>{{ $booking->scheduled_at->translatedFormat('l, d M Y') }}</b>. Pembayaran akan ditandai <b>Lunas</b> dan tiket otomatis aktif di antrean pada tanggal tersebut.
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2.5 rounded-b-3xl">
                        <button
                            type="button"
                            onclick="document.getElementById('modal-approve-booking-{{ $booking->id }}').close()"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer inline-flex items-center gap-1.5"
                        >
                            <x-ui.icon name="check" class="w-4 h-4 stroke-[3]" />
                            <span>Ya, Konfirmasi Lunas</span>
                        </button>
                    </div>
                </form>
            </dialog>

            <!-- 3. Dialog Modal Tolak Bukti Pembayaran -->
            <dialog
                id="modal-reject-booking-{{ $booking->id }}"
                onclick="if(event.target===this)this.close()"
                class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs outline-none focus:outline-none ring-0"
            >
                <form method="POST" action="{{ route('cashier.bookings.reject-payment', $booking) }}" class="p-5 space-y-4">
                    @csrf
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 grid place-items-center shrink-0">
                            <x-ui.icon name="alert-triangle" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-extrabold text-sm text-zinc-950">Tolak Bukti Transfer?</h3>
                            <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                                Bukti pembayaran booking <b class="text-zinc-900">{{ $booking->code }}</b> akan ditolak dan pelanggan diminta mengunggah ulang.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-zinc-700 mb-1">
                            Alasan Penolakan <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <input
                            type="text"
                            name="reason"
                            id="reject-reason-input-{{ $booking->id }}"
                            required
                            placeholder="Contoh: Mutasi bank belum masuk / nominal kurang"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-900 placeholder:text-zinc-400 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition"
                        />
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            <button type="button" onclick="document.getElementById('reject-reason-input-{{ $booking->id }}').value='Mutasi rekening bank belum masuk'" class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                Mutasi belum masuk
                            </button>
                            <button type="button" onclick="document.getElementById('reject-reason-input-{{ $booking->id }}').value='Nominal transfer tidak sesuai'" class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                Nominal tidak sesuai
                            </button>
                            <button type="button" onclick="document.getElementById('reject-reason-input-{{ $booking->id }}').value='Foto bukti buram / tidak terbaca'" class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                                Foto bukti buram
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            onclick="document.getElementById('modal-reject-booking-{{ $booking->id }}').close()"
                            class="min-h-[40px] px-3 py-2 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-bold text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="min-h-[40px] px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-sm transition cursor-pointer"
                        >
                            Ya, Tolak Bukti
                        </button>
                    </div>
                </form>
            </dialog>
        @else
            <!-- BELUM ADA BUKTI TRANSFER DARI PELANGGAN -->
            <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex items-center gap-2 text-xs text-zinc-500">
                <x-ui.icon name="clock" class="w-4 h-4 text-zinc-400 shrink-0" />
                <span>Menunggu pelanggan transfer rekening &amp; unggah bukti di sistem.</span>
            </div>
        @endif
    </div>
</div>
