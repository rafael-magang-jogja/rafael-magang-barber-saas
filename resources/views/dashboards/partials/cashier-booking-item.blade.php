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

    <!-- Baris 3: Toolbar Aksi Kasir (Check-in, No-Show, Batalkan) -->
    <div class="pt-2.5 border-t border-zinc-100 space-y-2">
        <!-- Tombol Check-in Antrean (Paling Utama) -->
        <form method="POST" action="{{ route('cashier.bookings.check-in', $booking) }}" class="w-full flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            @csrf

            @if($bookingHasColoring && !empty($cashierColorOptions))
            <div class="w-full sm:w-60 shrink-0">
                <x-ui.select
                    name="hair_color_id"
                    size="sm"
                    :options="$cashierColorOptions"
                    :value="$booking->hair_color_id"
                    search-placeholder="Pilih warna cat..." />
            </div>
            @endif

            <x-ui.button type="submit" size="sm" variant="{{ $isToday ? 'primary' : 'secondary' }}" class="w-full justify-center py-2.5 font-bold">
                <x-ui.icon name="ticket" class="w-4 h-4" />
                <span>{{ $isToday ? 'Check-in Antrean' : ($isPast ? 'Check-in Sekarang' : 'Check-in Lebih Awal') }}</span>
                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
            </x-ui.button>
        </form>

        <!-- Tombol No-Show & Batalkan (Dua Kolom Simetris) -->
        <div class="grid grid-cols-2 gap-2">
            <!-- Tombol No-Show -->
            <form method="POST" action="{{ route('cashier.bookings.cancel', $booking) }}" onsubmit="return confirm('Tandai booking {{ $booking->code }} ({{ $booking->customerProfile?->name }}) sebagai Tidak Hadir (No-Show)?');" class="w-full">
                @csrf
                <input type="hidden" name="action" value="no_show">
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-100 text-zinc-700 text-xs font-bold shadow-2xs transition outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                    <x-ui.icon name="x-circle" class="w-3.5 h-3.5 text-zinc-400" />
                    <span>Tandai No-Show</span>
                </button>
            </form>

            <!-- Tombol Batalkan -->
            <form method="POST" action="{{ route('cashier.bookings.cancel', $booking) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan booking {{ $booking->code }} ({{ $booking->customerProfile?->name }})?');" class="w-full">
                @csrf
                <input type="hidden" name="action" value="cancel">
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-xl border border-rose-200 bg-rose-50/70 hover:bg-rose-100 text-rose-700 text-xs font-bold shadow-2xs transition outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                    <x-ui.icon name="x" class="w-3.5 h-3.5" />
                    <span>Batalkan Booking</span>
                </button>
            </form>
        </div>
    </div>
</div>
