<div wire:poll.5s="checkBookingStatus" class="w-full">
    @if($status === 'cancelled' || $status === 'no_show')
        {{-- Bar tracker tidak ditampilkan saat status dibatalkan --}}
    @elseif(! $isPaid)
        <div class="flex items-center justify-between text-xs py-2 px-3.5 rounded-2xl bg-zinc-950 text-white border border-zinc-800 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span class="text-[11px] font-bold text-zinc-200">Sinkronisasi status pembayaran QRIS...</span>
            </div>
        </div>
    @elseif(in_array($ticketStatus, ['called', 'in_service'], true))
        <div class="flex items-center justify-between text-xs py-2 px-3.5 rounded-2xl bg-emerald-950/70 text-emerald-200 border border-emerald-800/80 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-[11px] font-bold text-emerald-100">
                    @if($ticketStatus === 'in_service')
                        Nomor Anda sedang dicukur di kursi!
                    @else
                        Nomor antrean Anda sedang dipanggil!
                    @endif
                </span>
            </div>
        </div>
    @endif
</div>
