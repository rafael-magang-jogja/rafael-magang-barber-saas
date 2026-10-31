<x-layouts.customer title="Dashboard Pelanggan" subtitle="Pusat Antrean & Reservasi">
    <!-- Sambutan Pengguna (Mobile Card) -->
    <div class="rounded-2xl bg-zinc-950 text-white p-5 shadow-lg border border-zinc-800">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-extrabold mb-2">
            <x-ui.icon name="sparkles" class="w-3 h-3" />
            <span>Mode Pelanggan BarberOS</span>
        </div>
        <h1 class="text-xl font-extrabold tracking-tight">Halo, {{ $user->name }}!</h1>
        <p class="text-xs text-zinc-400 mt-1 leading-relaxed">
            Ambil antrean langsung di tempat dengan scan QR, atau jadwalkan booking jam potong di outlet favorit Anda.
        </p>

        <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-center justify-between gap-2">
            <span class="text-[11px] text-zinc-400">Ingin reservasi jam potong?</span>
            <a
                href="{{ route('customer.booking') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs shadow-md shadow-amber-500/20 hover:bg-amber-400 transition"
            >
                <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                <span>Booking Jadwal</span>
            </a>
        </div>
    </div>

    <!-- CARD 1: Status Antrean Aktif / 1 Riwayat Terakhir -->
    <x-ui.card class="{{ $activeTicket ? 'ring-2 ring-amber-500/50 border-amber-500 shadow-md' : '' }}">
        <x-slot:header>
            <div class="flex items-center justify-between gap-2 w-full">
                <h2 class="font-extrabold text-xs flex items-center gap-1.5 uppercase tracking-wider text-zinc-800">
                    <x-ui.icon name="ticket" class="w-3.5 h-3.5 text-amber-600" />
                    <span>{{ $activeTicket ? 'Antrean Aktif Anda' : 'Riwayat Kunjungan Terbaru' }}</span>
                </h2>
                @if($activeTicket)
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
                                <span class="w-2.5 h-2.5 rounded-full border border-black/20" style="background-color: {{ $activeTicket->hairColor->hex_code }}"></span>
                                <span>{{ $activeTicket->hairColor->name }}</span>
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between py-1">
                        <span class="text-zinc-500">Estimasi:</span>
                        <span class="font-extrabold text-zinc-900 num">~{{ $activeTicket->estimated_duration_minutes }} Menit</span>
                    </div>
                </div>

                @if($activeTicket)
                    <a
                        href="{{ route('customer.tickets.show', $activeTicket->id) }}"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs hover:bg-amber-400 transition shadow-md shadow-amber-500/20"
                    >
                        <span>Lihat Detail Pesanan &amp; Status Layanan</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                @endif
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
                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-xs hover:bg-zinc-800 transition shadow-2xs"
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
                <p class="font-extrabold text-xs text-zinc-800">Belum Ada Antrean Aktif</p>
                <p class="text-[11px] text-zinc-400 max-w-xs mx-auto">
                    Arahkan kamera ke QR di barbershop untuk mengambil nomor antrean langsung di tempat.
                </p>
            </div>
        @endif
    </x-ui.card>

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
                    class="w-full flex items-center justify-center gap-2 px-3.5 py-3 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-xs shadow-md hover:bg-zinc-800 transition cursor-pointer"
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

    <!-- CARD 3: Daftar Outlet Barbershop (Pilihan Cepat) -->
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
                <div class="py-3 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-extrabold text-sm text-zinc-900 truncate">{{ $t->name }}</h3>
                            <p class="text-xs text-zinc-500">
                                {{ $t->branches->count() }} Cabang
                                @if($t->branches->isNotEmpty())
                                    · {{ $t->branches->pluck('name')->join(', ') }}
                                @endif
                            </p>
                            @if($t->address)
                                <p class="text-[11px] text-zinc-400 mt-0.5 truncate">{{ $t->address }}</p>
                            @endif

                            @foreach($t->branches as $branch)
                                @if(!empty($branch->maps_url))
                                    <a
                                        href="{{ $branch->maps_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 hover:underline mt-1 mr-1 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100"
                                    >
                                        <x-ui.icon name="external-link" class="w-3 h-3" />
                                        <span>Maps {{ $branch->name }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-1.5 space-y-1">
                        <a
                            href="{{ route('customer.booking', ['tenant_id' => $t->id]) }}"
                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-zinc-950 text-amber-400 font-extrabold text-xs hover:bg-zinc-800 transition shadow-2xs"
                        >
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                            <span>Booking Jadwal Potong</span>
                        </a>
                        <p class="text-[10px] text-zinc-400 text-center italic">
                            *Ambil antrean langsung wajib scan QR di lokasi cabang
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-xs text-zinc-400 py-6 text-center">Belum ada outlet barbershop aktif yang terdaftar.</p>
            @endforelse
        </div>
    </x-ui.card>

    <!-- Script HTML5-QRCode Scanner -->
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                    if (decodedText.startsWith('http://') || decodedText.startsWith('https://')) {
                        window.location.href = decodedText;
                    } else if (decodedText.startsWith('/')) {
                        window.location.href = decodedText;
                    } else {
                        window.location.href = '/b/' + encodeURIComponent(decodedText.trim());
                    }
                }, 800);
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
</x-layouts.customer>
