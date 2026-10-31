<x-layouts.customer title="Riwayat">
    <div class="space-y-4">
        <!-- Header Info Mobile -->
        <div class="p-3.5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 grid place-items-center shrink-0">
                <x-ui.icon name="file-text" class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <h1 class="font-extrabold text-sm text-zinc-900 truncate">Riwayat Layanan</h1>
                <p class="text-[11px] text-zinc-500 truncate">{{ $tickets->count() }} Antrean · {{ $bookings->count() }} Booking</p>
            </div>
        </div>

        <!-- TABEL 1: Antrean Langsung (Tiket) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                        <x-ui.icon name="ticket" class="w-3.5 h-3.5 text-amber-600" />
                        <span>Tiket Antrean Langsung</span>
                    </h2>
                    <span class="text-[11px] text-zinc-400 num font-bold">{{ $tickets->count() }} Tiket</span>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($tickets as $ticket)
                    <div class="py-3 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-zinc-950 text-amber-400 font-extrabold text-xs num">
                                        {{ $ticket->number }}
                                    </span>
                                    <span class="font-extrabold text-xs text-zinc-900">{{ $ticket->tenant?->name }}</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    {{ $ticket->branch?->name }} · {{ $ticket->created_at->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                            @php
                                $ticketStatusColor = match ($ticket->status->value) {
                                    'completed' => 'text-emerald-600',
                                    'cancelled', 'no_show' => 'text-zinc-400',
                                    'in_service' => 'text-blue-600',
                                    'called' => 'text-amber-600',
                                    default => 'text-amber-600',
                                };
                            @endphp
                            <div class="flex items-center gap-1.5 text-xs font-extrabold {{ $ticketStatusColor }} bg-transparent">
                                <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
                                <span>{{ $ticket->status->label() }}</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-200/60 text-xs space-y-1">
                            <div class="flex items-center justify-between text-zinc-700">
                                <span>Layanan:</span>
                                <span class="font-semibold text-right">{{ $ticket->items->pluck('service_name')->join(', ') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-700">
                                <span>Barber:</span>
                                <span class="font-semibold">{{ $ticket->barber?->display_name ?? 'Siapa saja' }}</span>
                            </div>
                            @if($ticket->hairColor)
                                <div class="flex items-center justify-between text-zinc-700">
                                    <span>Warna Rambut:</span>
                                    <span class="inline-flex items-center gap-1.5 font-semibold">
                                        <span class="w-2.5 h-2.5 rounded-full border border-black/20" style="background-color: {{ $ticket->hairColor->hex_code }}"></span>
                                        <span>{{ $ticket->hairColor->name }}</span>
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <a
                                href="{{ route('customer.tickets.show', $ticket->id) }}"
                                class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 bg-white text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition"
                            >
                                <x-ui.icon name="ticket" class="w-3.5 h-3.5 text-amber-600" />
                                <span>Detail Pesanan</span>
                            </a>
                            @if($ticket->status->value === 'completed' && $ticket->order)
                                <a
                                    href="{{ route('invoices.show', $ticket->order->number) }}"
                                    class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-xs hover:bg-zinc-800 transition shadow-2xs"
                                >
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                    <span>Lihat E-Invoice</span>
                                </a>
                            @elseif($ticket->status->value === 'completed' && (! $ticket->order || $ticket->order->payment_status !== \App\Enums\PaymentStatus::Paid))
                                <a
                                    href="{{ route('customer.tickets.checkout', $ticket->id) }}"
                                    class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 text-white font-extrabold text-xs hover:bg-emerald-500 transition shadow-2xs"
                                >
                                    <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                    <span>Selesai &amp; Bayar</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Belum ada riwayat tiket antrean.</p>
                @endforelse
            </div>
        </x-ui.card>

        <!-- TABEL 2: Jadwal Booking -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600" />
                        <span>Jadwal Booking Online</span>
                    </h2>
                    <span class="text-[11px] text-zinc-400 num font-bold">{{ $bookings->count() }} Booking</span>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($bookings as $booking)
                    <div class="py-3 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-zinc-900 text-amber-400 font-extrabold text-xs num">
                                        {{ $booking->code }}
                                    </span>
                                    <span class="font-extrabold text-xs text-zinc-900">{{ $booking->tenant?->name }}</span>
                                </div>
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    {{ $booking->branch?->name }} · Kedatangan: <b class="text-zinc-800">{{ $booking->scheduled_at->translatedFormat('d M Y, H:i') }}</b>
                                </p>
                            </div>
                            @php
                                $bookingStatusColor = match ($booking->status->value) {
                                    'completed' => 'text-emerald-600',
                                    'cancelled', 'no_show' => 'text-zinc-400',
                                    'confirmed' => 'text-amber-600',
                                    default => 'text-zinc-600',
                                };
                            @endphp
                            <div class="flex items-center gap-1.5 text-xs font-extrabold {{ $bookingStatusColor }} bg-transparent">
                                <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
                                <span>{{ $booking->status->label() }}</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-200/60 text-xs space-y-1">
                            <div class="flex items-center justify-between text-zinc-700">
                                <span>Layanan:</span>
                                <span class="font-semibold text-right">{{ $booking->items->pluck('service_name')->join(', ') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-700">
                                <span>Barber:</span>
                                <span class="font-semibold">{{ $booking->barber?->display_name ?? 'Siapa saja' }}</span>
                            </div>
                            @if($booking->hairColor)
                                <div class="flex items-center justify-between text-zinc-700">
                                    <span>Warna Rambut:</span>
                                    <span class="inline-flex items-center gap-1.5 font-semibold">
                                        <span class="w-2.5 h-2.5 rounded-full border border-black/20" style="background-color: {{ $booking->hairColor->hex_code }}"></span>
                                        <span>{{ $booking->hairColor->name }}</span>
                                    </span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between text-zinc-700 pt-1 border-t border-zinc-200/60">
                                <span>Perkiraan Total:</span>
                                <span class="font-extrabold text-zinc-900 num">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <a
                                href="{{ route('customer.bookings.show', $booking->id) }}"
                                class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 bg-white text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition"
                            >
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600" />
                                <span>Detail Booking</span>
                            </a>
                            @if($booking->queueTicket)
                                <a
                                    href="{{ route('customer.tickets.show', $booking->queueTicket->id) }}"
                                    class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs hover:bg-amber-400 transition"
                                >
                                    <x-ui.icon name="ticket" class="w-3.5 h-3.5" />
                                    <span>Tiket Antrean</span>
                                </a>
                            @elseif($booking->status->value === 'completed' && $booking->queueTicket?->order)
                                <a
                                    href="{{ route('invoices.show', $booking->queueTicket->order->number) }}"
                                    class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-xs hover:bg-zinc-800 transition"
                                >
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                    <span>E-Invoice</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Belum ada riwayat booking.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layouts.customer>
