<x-layouts.customer :title="'Menunggu Konfirmasi & Invoice — ' . $tenant->name">
    <div class="px-4 py-6 space-y-4">
        <!-- Banner Menunggu Konfirmasi & Invoice -->
        <div class="rounded-3xl bg-amber-500/10 border-2 border-amber-500/30 p-5 text-center space-y-3 shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-zinc-950 text-amber-400 grid place-items-center mx-auto shadow-md border border-zinc-800 relative">
                <x-ui.icon name="file-text" class="w-7 h-7 text-amber-400" />
                <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 ring-2 ring-white"></span>
                </span>
            </div>

            <div>
                <h1 class="text-lg font-black text-zinc-950">Menunggu Konfirmasi Kasir</h1>
                <p class="text-xs text-zinc-600 mt-1 leading-relaxed">
                    Penilaian staff telah tersimpan! Kasir sedang memeriksa apakah pembayaran Anda sudah masuk untuk menerbitkan invoice.
                </p>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/80 border border-amber-300 text-[11px] font-bold text-amber-900 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Halaman ini akan otomatis menampilkan invoice Anda...</span>
            </div>
        </div>

        <!-- Detail Tagihan & Metode Pembayaran -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-500">Rincian Pembayaran</span>
                    <span class="px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-700 text-[10px] font-bold uppercase num">
                        {{ $ticket->number }}
                    </span>
                </div>
            </x-slot:header>

            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                    <span class="text-zinc-500">Outlet / Cabang</span>
                    <span class="font-bold text-zinc-900">{{ $branch->name }}</span>
                </div>

                @if($barber)
                <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                    <span class="text-zinc-500">Staff Barber</span>
                    <span class="font-bold text-zinc-900">{{ $barber->display_name ?? $barber->user?->name }}</span>
                </div>
                @endif

                <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                    <span class="text-zinc-500">Metode Dipilih</span>
                    <span class="inline-flex items-center gap-1.5 font-extrabold text-zinc-900 uppercase">
                        @if($selectedMethod->value === 'qris')
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>QRIS (Meja Kasir)</span>
                        @else
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Tunai di Kasir</span>
                        @endif
                    </span>
                </div>

                <div class="flex items-center justify-between py-1 border-b border-zinc-100">
                    <span class="text-zinc-500">Status Pembayaran</span>
                    <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 text-[11px] font-extrabold">
                        Menunggu Cek Kasir
                    </span>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <span class="text-sm font-bold text-zinc-700">Total Tagihan</span>
                    <span class="text-xl font-black text-zinc-950 num">
                        Rp{{ number_format($totalPrice, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </x-ui.card>

        @if($selectedMethod->value === 'qris')
        <!-- Instruksi QRIS Meja Kasir -->
        <div class="rounded-2xl bg-amber-50/70 border border-amber-200/80 p-4 text-xs space-y-1.5">
            <div class="flex items-center gap-2 font-bold text-amber-950">
                <x-ui.icon name="check" class="w-4 h-4 text-amber-600" />
                <span>Instruksi Pembayaran QRIS:</span>
            </div>
            <p class="text-[11px] text-zinc-600 leading-relaxed">
                Scan kode/stiker QRIS yang ada di meja kasir melalui aplikasi m-Banking atau E-Wallet Anda dengan nominal pas <b class="text-zinc-950 num">Rp{{ number_format($totalPrice, 0, ',', '.') }}</b>. Beritahu kasir jika Anda sudah selesai transfer.
            </p>
        </div>
        @else
        <!-- Instruksi Tunai -->
        <div class="rounded-2xl bg-zinc-50 border border-zinc-200/80 p-4 text-xs space-y-1.5">
            <div class="flex items-center gap-2 font-bold text-zinc-900">
                <x-ui.icon name="banknote" class="w-4 h-4 text-emerald-600" />
                <span>Instruksi Pembayaran Tunai:</span>
            </div>
            <p class="text-[11px] text-zinc-600 leading-relaxed">
                Silakan serahkan uang tunai sebesar <b class="text-zinc-950 num">Rp{{ number_format($totalPrice, 0, ',', '.') }}</b> ke meja kasir. Kasir akan mengonfirmasi dan invoice Anda akan terbit di layar ini.
            </p>
        </div>
        @endif

        <!-- Tombol Beranda -->
        <div class="pt-2 text-center">
            <a
                href="{{ route('customer.dashboard') }}"
                class="text-xs font-bold text-zinc-500 hover:text-zinc-800 transition"
            >
                Kembali ke Beranda
            </a>
        </div>
    </div>

    <!-- Poller Otomatis Setiap 3 Detik: Redirect ke Invoice Saat Kasir Konfirmasi Lunas -->
    <script>
        (function () {
            const checkUrl = "{{ route('customer.tickets.show', $ticket->id) }}?poll=1";

            const poller = setInterval(async () => {
                try {
                    const response = await fetch(checkUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) return;

                    const data = await response.json();

                    if (data.is_paid && data.invoice_url) {
                        clearInterval(poller);
                        window.location.href = data.invoice_url;
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            }, 3000);
        })();
    </script>
</x-layouts.customer>
