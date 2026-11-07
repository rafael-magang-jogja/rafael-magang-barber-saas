<x-layouts.customer title="Riwayat" :tenant="$tenant">
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

        <!-- Segmented Navigation Tabs: Antrean vs Booking -->
        <div class="grid grid-cols-2 p-1 bg-zinc-100/90 rounded-2xl border border-zinc-200/80 gap-1 select-none shadow-2xs">
            <button
                type="button"
                id="tab-btn-tickets"
                onclick="switchHistoryTab('tickets')"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs font-black transition-all shadow-xs bg-white text-zinc-950 border border-zinc-200/70 cursor-pointer"
            >
                <x-ui.icon name="ticket" class="w-4 h-4 text-amber-500 shrink-0" />
                <span class="truncate">Antrean Langsung</span>
                <span id="tab-badge-tickets" class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 num shrink-0">{{ $tickets->count() }}</span>
            </button>
            <button
                type="button"
                id="tab-btn-bookings"
                onclick="switchHistoryTab('bookings')"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs font-bold transition-all text-zinc-500 hover:text-zinc-800 cursor-pointer"
            >
                <x-ui.icon name="calendar" class="w-4 h-4 text-zinc-400 shrink-0" />
                <span class="truncate">Booking Online</span>
                <span id="tab-badge-bookings" class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-zinc-200 text-zinc-700 num shrink-0">{{ $bookings->count() }}</span>
            </button>
        </div>

        <!-- TAB 1: Antrean Langsung (Tiket) -->
        <div id="tab-content-tickets" class="space-y-4">
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
                                        <span class="w-2.5 h-2.5 rounded-full border border-black/20" @style(['background-color: ' . $ticket->hairColor->hex_code])></span>
                                        <span>{{ $ticket->hairColor->name }}</span>
                                    </span>
                                </div>
                            @endif
                        </div>

                        @php
                            $canCancelTicket = in_array($ticket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true);
                            $hasTicketInvoice = $ticket->status->value === 'completed' && $ticket->order;
                            $needsTicketPayment = $ticket->status->value === 'completed' && (! $ticket->order || $ticket->order->payment_status !== \App\Enums\PaymentStatus::Paid);
                            $hasTicketSecondAction = $canCancelTicket || $hasTicketInvoice || $needsTicketPayment;
                        @endphp

                        <div class="grid {{ $hasTicketSecondAction ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 pt-1.5">
                            <a
                                href="{{ route('customer.tickets.show', $ticket->id) }}"
                                class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-zinc-200 bg-white text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition shadow-2xs"
                            >
                                <x-ui.icon name="ticket" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                <span>Detail Pesanan</span>
                            </a>

                            @if($canCancelTicket)
                                <button
                                    type="button"
                                    data-dialog-id="modal-cancel-ticket-{{ $ticket->id }}"
                                    onclick="document.getElementById(this.dataset.dialogId).showModal()"
                                    class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/90 font-bold text-xs transition cursor-pointer"
                                >
                                    <x-ui.icon name="x" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                    <span>Batalkan</span>
                                </button>
                            @elseif($hasTicketInvoice)
                                <a
                                    href="{{ route('invoices.show', $ticket->order->number) }}"
                                    class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold text-xs transition shadow-2xs"
                                >
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-950 shrink-0" />
                                    <span>Lihat E-Invoice</span>
                                </a>
                            @elseif($needsTicketPayment)
                                <a
                                    href="{{ route('customer.tickets.checkout', $ticket->id) }}"
                                    class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 text-white font-extrabold text-xs hover:bg-emerald-500 transition shadow-2xs"
                                >
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 shrink-0" />
                                    <span>Selesai &amp; Bayar</span>
                                </a>
                            @endif
                        </div>

                        @if($canCancelTicket)
                            <dialog
                                id="modal-cancel-ticket-{{ $ticket->id }}"
                                onclick="if(event.target===this)this.close()"
                                class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
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
                                                Apakah Anda yakin ingin membatalkan antrean <b class="text-zinc-900 num">{{ $ticket->number }}</b> di <b class="text-zinc-900">{{ $ticket->tenant?->name }}</b>?
                                            </p>
                                        </div>
                                    </div>

                                    @php
                                        $histOrder = $ticket->order;
                                        $paidPayment = $histOrder?->payments?->firstWhere('status', 'success') ?? $histOrder?->payments?->first();
                                        $paidMethod = $paidPayment?->method;
                                        $isTicketPaid = ($histOrder !== null && $histOrder->payment_status === \App\Enums\PaymentStatus::Paid)
                                            || ($ticket->booking !== null && (
                                                $ticket->booking->xendit_paid_at !== null
                                                || $ticket->booking->payment_approved_at !== null
                                                || in_array($ticket->booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::CheckedIn, \App\Enums\BookingStatus::Completed], true)
                                                || ! empty($ticket->booking->payment_proof_path)
                                            ));
                                        $isQrisPaid = $isTicketPaid && (
                                            $ticket->booking_id !== null
                                            || $paidMethod === \App\Enums\PaymentMethod::Qris
                                            || (is_object($paidMethod) && $paidMethod->value === 'qris')
                                            || $paidMethod === 'qris'
                                            || $paidMethod === \App\Enums\PaymentMethod::Transfer
                                            || (is_object($paidMethod) && $paidMethod->value === 'transfer')
                                            || $paidMethod === 'transfer'
                                        );
                                        $isCashPaid = $isTicketPaid && ! $isQrisPaid;
                                        $paidAmount = $histOrder?->paid_total ?? (int) ($ticket->booking?->estimated_total ?? $ticket->items->sum('price'));
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
                                        <label for="cancel-hist-ticket-{{ $ticket->id }}" class="block text-[11px] font-bold text-zinc-600 mb-1">
                                            Alasan Pembatalan <span class="text-zinc-400 font-normal">(Opsional)</span>
                                        </label>
                                        <input
                                            id="cancel-hist-ticket-{{ $ticket->id }}"
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
                                            data-dialog-id="modal-cancel-ticket-{{ $ticket->id }}"
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
                    </div>
                @empty
                    <div class="py-8 text-center space-y-1.5">
                        <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 mx-auto flex items-center justify-center">
                            <x-ui.icon name="ticket" class="w-5 h-5 text-zinc-400" />
                        </div>
                        <p class="text-xs font-bold text-zinc-700">Belum Ada Riwayat Antrean</p>
                        <p class="text-[11px] text-zinc-400">Anda belum pernah mengambil tiket antrean langsung.</p>
                    </div>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <!-- TAB 2: Jadwal Booking Online -->
    <div id="tab-content-bookings" class="space-y-4 hidden">
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
                                    'completed', 'confirmed' => 'text-emerald-600',
                                    'cancelled', 'no_show', 'expired' => 'text-zinc-400',
                                    'pending' => 'text-amber-600',
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
                                        <span class="w-2.5 h-2.5 rounded-full border border-black/20" @style(['background-color: ' . $booking->hairColor->hex_code])></span>
                                        <span>{{ $booking->hairColor->name }}</span>
                                    </span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between text-zinc-700 pt-1 border-t border-zinc-200/60">
                                <span>Perkiraan Total:</span>
                                <span class="font-extrabold text-zinc-900 num">Rp{{ number_format($booking->estimated_total, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @php
                            $canCancelHistBooking = in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Confirmed], true)
                                || ($booking->status === \App\Enums\BookingStatus::CheckedIn
                                    && $booking->queueTicket
                                    && in_array($booking->queueTicket->status, [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called], true));

                            $isHistBookingCancelled = in_array($booking->status, [\App\Enums\BookingStatus::Cancelled, \App\Enums\BookingStatus::NoShow], true);
                            $isHistTicketActive = $booking->queueTicket !== null
                                && ! $isHistBookingCancelled
                                && ! in_array($booking->queueTicket->status, [\App\Enums\QueueStatus::Cancelled, \App\Enums\QueueStatus::NoShow], true);
                        @endphp

                        <div class="space-y-2 pt-1.5">
                            {{-- Baris 1: Aksi Utama Menuju Tiket Antrean / E-Invoice (Full Width) --}}
                            @if($isHistTicketActive)
                                <a
                                    href="{{ route('customer.tickets.show', $booking->queueTicket->id) }}"
                                    class="w-full min-h-[38px] flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs transition shadow-2xs"
                                >
                                    <x-ui.icon name="ticket" class="w-4 h-4 text-zinc-950 shrink-0" />
                                    <span>Buka Tiket Antrean ({{ $booking->queueTicket->number }})</span>
                                </a>
                            @elseif($booking->status->value === 'completed' && $booking->queueTicket?->order)
                                <a
                                    href="{{ route('invoices.show', $booking->queueTicket->order->number) }}"
                                    class="w-full min-h-[38px] flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs transition shadow-2xs"
                                >
                                    <x-ui.icon name="file-text" class="w-4 h-4 text-zinc-950 shrink-0" />
                                    <span>Lihat E-Invoice</span>
                                </a>
                            @endif

                            {{-- Baris 2: Detail Booking & Opsi Batalkan (Rapi Berdampingan atau Full) --}}
                            <div class="grid {{ $canCancelHistBooking ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                                <a
                                    href="{{ route('customer.bookings.show', $booking) }}"
                                    class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-zinc-200 bg-white text-zinc-800 font-extrabold text-xs hover:bg-zinc-50 transition shadow-2xs"
                                >
                                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                    <span>Detail Booking</span>
                                </a>

                                @if($canCancelHistBooking)
                                    <button
                                        type="button"
                                        data-dialog-id="modal-cancel-booking-{{ $booking->id }}"
                                        onclick="document.getElementById(this.dataset.dialogId).showModal()"
                                        class="min-h-[38px] flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/90 font-bold text-xs transition cursor-pointer"
                                    >
                                        <x-ui.icon name="x" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                        <span>Batalkan</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Modal Dialog Pembatalan Booking --}}
                        @if($canCancelHistBooking)
                            <dialog
                                id="modal-cancel-booking-{{ $booking->id }}"
                                onclick="if(event.target===this)this.close()"
                                class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
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
                                        <label for="cancel-hist-booking-{{ $booking->id }}" class="block text-[11px] font-bold text-zinc-600 mb-1">
                                            Alasan Pembatalan <span class="text-zinc-400 font-normal">(Opsional)</span>
                                        </label>
                                        <input
                                            id="cancel-hist-booking-{{ $booking->id }}"
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
                                            data-dialog-id="modal-cancel-booking-{{ $booking->id }}"
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
                    </div>
                @empty
                    <div class="py-8 text-center space-y-1.5">
                        <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 mx-auto flex items-center justify-center">
                            <x-ui.icon name="calendar" class="w-5 h-5 text-zinc-400" />
                        </div>
                        <p class="text-xs font-bold text-zinc-700">Belum Ada Riwayat Booking</p>
                        <p class="text-[11px] text-zinc-400">Anda belum memiliki riwayat reservasi jadwal pangkas online.</p>
                    </div>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <!-- SCRIPT TAB SWITCHER RIWAYAT (Antrean vs Booking) -->
    <script>
        function switchHistoryTab(tabName) {
            const ticketsContent = document.getElementById('tab-content-tickets');
            const bookingsContent = document.getElementById('tab-content-bookings');
            const ticketsBtn = document.getElementById('tab-btn-tickets');
            const bookingsBtn = document.getElementById('tab-btn-bookings');
            const ticketsIcon = ticketsBtn ? ticketsBtn.querySelector('svg') : null;
            const bookingsIcon = bookingsBtn ? bookingsBtn.querySelector('svg') : null;
            const ticketsBadge = document.getElementById('tab-badge-tickets');
            const bookingsBadge = document.getElementById('tab-badge-bookings');

            const activeClasses = ['bg-white', 'text-zinc-950', 'font-black', 'shadow-xs', 'border', 'border-zinc-200/70'];
            const inactiveClasses = ['text-zinc-500', 'font-bold', 'hover:text-zinc-800'];

            if (tabName === 'bookings') {
                if (ticketsContent) ticketsContent.classList.add('hidden');
                if (bookingsContent) bookingsContent.classList.remove('hidden');

                if (ticketsBtn) {
                    ticketsBtn.classList.remove(...activeClasses);
                    ticketsBtn.classList.add(...inactiveClasses);
                }
                if (ticketsIcon) ticketsIcon.className = 'w-4 h-4 text-zinc-400 shrink-0';
                if (ticketsBadge) ticketsBadge.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-zinc-200 text-zinc-700 num shrink-0';

                if (bookingsBtn) {
                    bookingsBtn.classList.remove(...inactiveClasses);
                    bookingsBtn.classList.add(...activeClasses);
                }
                if (bookingsIcon) bookingsIcon.className = 'w-4 h-4 text-amber-500 shrink-0';
                if (bookingsBadge) bookingsBadge.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 num shrink-0';

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#booking');
                }
            } else {
                if (bookingsContent) bookingsContent.classList.add('hidden');
                if (ticketsContent) ticketsContent.classList.remove('hidden');

                if (bookingsBtn) {
                    bookingsBtn.classList.remove(...activeClasses);
                    bookingsBtn.classList.add(...inactiveClasses);
                }
                if (bookingsIcon) bookingsIcon.className = 'w-4 h-4 text-zinc-400 shrink-0';
                if (bookingsBadge) bookingsBadge.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-zinc-200 text-zinc-700 num shrink-0';

                if (ticketsBtn) {
                    ticketsBtn.classList.remove(...inactiveClasses);
                    ticketsBtn.classList.add(...activeClasses);
                }
                if (ticketsIcon) ticketsIcon.className = 'w-4 h-4 text-amber-500 shrink-0';
                if (ticketsBadge) ticketsBadge.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 num shrink-0';

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#antrean');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const hash = (window.location.hash || '').toLowerCase();
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = (urlParams.get('tab') || '').toLowerCase();

            if (hash === '#booking' || hash === '#bookings' || tabParam === 'booking' || tabParam === 'bookings') {
                switchHistoryTab('bookings');
            } else {
                switchHistoryTab('tickets');
            }
        });
    </script>
</div>
</x-layouts.customer>
