@php
    $isCurrentlyCalled = $calledTicket && $calledTicket->status === \App\Enums\QueueStatus::Called;
    $calledCallKey = $isCurrentlyCalled ? ($calledTicket->id . '-' . ($calledTicket->called_at?->timestamp ?? 0)) : '';
@endphp

<div
    wire:poll.3s
    data-tv-root
    data-called-key="{{ $calledCallKey }}"
    data-called-number="{{ $isCurrentlyCalled ? $calledTicket->number : '' }}"
    class="min-h-screen lg:h-screen flex flex-col justify-between overflow-hidden relative"
>
    @if($isLocked ?? false)
        <div class="min-h-screen bg-zinc-950 text-white flex flex-col items-center justify-center p-8 text-center space-y-5">
            <div class="w-20 h-20 rounded-3xl bg-amber-500/10 text-amber-400 grid place-items-center border border-amber-500/20 shadow-2xl shadow-amber-500/10 animate-pulse">
                <x-ui.icon name="lock" class="w-10 h-10" />
            </div>
            <div class="space-y-2.5 max-w-lg">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/20 text-rose-300 text-xs font-black uppercase tracking-wider border border-rose-500/30">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span>Layar TV Dinonaktifkan</span>
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-white">Cabang {{ $branch->name }} Sedang Dikunci</h1>
                <p class="text-sm text-zinc-400 leading-relaxed">
                    Cabang ini melebihi batas kuota cabang pada paket langganan barbershop aktif Anda (<strong class="text-white">Paket {{ $planName }}: Maksimal {{ $maxBranches }} Cabang</strong>).
                </p>
                <p class="text-xs text-zinc-500 pt-2">
                    Silakan hubungi pemilik barbershop untuk meng-upgrade paket langganan agar cabang ini dapat diaktifkan kembali.
                </p>
            </div>
        </div>
    @else
        @php
            $customerPortalUrl = $tenant->customerPortalUrl($branch->code);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=4&data=' . urlencode($customerPortalUrl);
            $hasRolling = $waitingTickets->count() > 5;
        @endphp

    <!-- Top Header TV -->
    <header class="flex items-center justify-between px-8 py-4 border-b border-white/10 shrink-0">
        <div class="flex items-center gap-3">
            @if($tenant->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-11 h-11 rounded-xl object-cover shadow-lg border border-white/10" />
            @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ $tenant->name }}" class="w-11 h-11 rounded-xl object-cover shadow-lg border border-white/10" />
            @else
                <div class="w-11 h-11 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shadow-lg font-black">
                    <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-6 h-6" />
                </div>
            @endif
            <div>
                <p class="font-extrabold text-xl tracking-wide uppercase">{{ $tenant->name }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                    <p class="text-sm text-zinc-400">{{ $branch->name }}</p>
                    @if(! ($tenant->hasFeature('white_label') ?? false))
                        <span class="text-zinc-600">·</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] font-semibold text-zinc-400">
                            <span>Powered by</span>
                            <strong class="text-amber-400 font-bold tracking-wide">{{ config('app.name', 'BarberOS') }}</strong>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 text-right">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-amber-400">
                    {{ now()->translatedFormat('l') }}, <span class="num text-white">{{ now()->format('d') }}</span>
                </p>
                <p class="text-2xl sm:text-3xl font-extrabold num text-white leading-none mt-0.5" wire:ignore>
                    <span data-tv-clock>{{ now()->format('H.i') }}</span> WIB
                </p>
            </div>
        </div>
    </header>

    <!-- Area Tengah: Nomor Dipanggil (Crossfade Fade In / Fade Out Bergantian QR tiap 5s) & Daftar Antrean Berikutnya -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-5 min-h-0 overflow-hidden">
        <!-- Kolom Kiri: Panggilan Antrean Bergantian dengan QR Code (Fade In / Fade Out Tanpa Memanjang) -->
        <div class="lg:col-span-3 flex items-center justify-center p-6 border-b lg:border-b-0 lg:border-r border-white/10 relative overflow-hidden">
            <div
                x-data="{ currentView: 'ticket' }"
                x-init="setInterval(() => { currentView = currentView === 'ticket' ? 'qr' : 'ticket'; }, 5000)"
                class="w-full grid grid-cols-1 grid-rows-1 place-items-center relative"
            >
                <!-- View 1: Nomor Antrean Sedang Dipanggil -->
                <div
                    x-show="currentView === 'ticket'"
                    x-transition:enter="transition-opacity duration-700 ease-in-out"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-700 ease-in-out"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="col-start-1 row-start-1 w-full flex flex-col items-center justify-center text-center"
                >
                    <p class="text-base sm:text-lg tracking-[0.25em] text-amber-400 font-bold uppercase flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>SEDANG DIPANGGIL</span>
                    </p>
                    <p class="text-7xl sm:text-8xl lg:text-9xl font-extrabold num mt-2 sm:mt-3 text-white tracking-tight">
                        {{ $calledTicket?->number ?? '---' }}
                    </p>
                    @if($calledTicket)
                        <p class="text-lg sm:text-xl lg:text-2xl mt-3 sm:mt-4 font-semibold text-zinc-100">
                            Silakan menuju <span class="px-3.5 py-1.5 rounded-2xl bg-amber-500 text-zinc-950 font-extrabold">Kursi {{ $calledTicket->barber?->display_name ?? 'Utama' }}</span>
                        </p>
                    @else
                        <p class="text-sm text-zinc-500 mt-4">Menunggu panggilan antrean berikutnya</p>
                    @endif
                </div>

                <!-- View 2: QR Code untuk Menuju Web Customer / Ambil Antrean Online -->
                <div
                    x-show="currentView === 'qr'"
                    x-transition:enter="transition-opacity duration-700 ease-in-out"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-700 ease-in-out"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="col-start-1 row-start-1 w-full flex flex-col items-center justify-center text-center px-4"
                    style="display: none;"
                >
                    <p class="text-base sm:text-lg lg:text-xl tracking-[0.25em] text-amber-400 font-extrabold uppercase flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-ping"></span>
                        <span>AMBIL ANTREAN ONLINE</span>
                    </p>

                    <div class="mt-3 p-3.5 sm:p-4 rounded-3xl bg-white shadow-2xl border border-white/20">
                        <img
                            src="{{ $qrUrl }}"
                            alt="QR Code Antrean Online"
                            class="w-56 h-56 sm:w-72 sm:h-72 md:w-80 md:h-80 lg:w-[340px] lg:h-[340px] xl:w-[380px] xl:h-[380px] 2xl:w-[420px] 2xl:h-[420px] max-h-[48vh] max-w-full aspect-square object-contain rounded-2xl"
                            loading="eager"
                        />
                    </div>

                    <p class="text-base sm:text-lg lg:text-xl text-white font-extrabold mt-3.5 tracking-wide">
                        Scan QR dari HP untuk ambil nomor antrean
                    </p>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Daftar Antrean Berikutnya (Roll dari Bawah ke Atas Tiap 3 Detik Jika > 5) -->
        <div class="lg:col-span-2 p-6 flex flex-col justify-start overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-white/10 mb-3.5">
                <div>
                    <p class="text-sm tracking-[0.2em] text-zinc-400 font-extrabold uppercase">ANTREAN BERIKUTNYA</p>
                    <span class="text-xs font-bold text-zinc-500 num">{{ $waitingTickets->count() }} antrean menunggu</span>
                </div>
            </div>

            @if($waitingTickets->isEmpty())
                <div class="py-12 text-center text-zinc-500 text-sm">
                    Belum ada antrean menunggu saat ini.
                </div>
            @else
                <!-- Jendela Tampil: Tepat 5 Kartu (5 x 58px + 4 x 10px = 330px) -->
                <div class="{{ $hasRolling ? 'h-[330px] overflow-hidden relative' : '' }}" data-tv-rolling-window>
                    <div
                        class="flex flex-col gap-2.5 transition-transform"
                        data-tv-rolling-track
                        data-has-rolling="{{ $hasRolling ? 'true' : 'false' }}"
                    >
                        @foreach($waitingTickets as $ticket)
                            <div
                                wire:key="waiting-ticket-{{ $ticket->id }}"
                                data-tv-waiting-card
                                class="flex items-center justify-between rounded-2xl bg-white/5 border border-white/10 px-5 py-3 h-[58px] shrink-0"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <b class="text-2xl num text-white shrink-0">{{ $ticket->number }}</b>
                                    <div class="min-w-0 text-left">
                                        @if($ticket->barber)
                                            <p class="text-xs font-bold text-zinc-300 truncate">
                                                Kursi {{ $ticket->barber->display_name }}
                                            </p>
                                        @else
                                            <p class="text-xs font-semibold text-zinc-500 truncate">
                                                Barber Otomatis
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    @if($ticket->hairColor)
                                        <span class="w-3 h-3 rounded-full border border-black/20 shrink-0" style="background-color: {{ $ticket->hairColor->hex_code }}" title="Warna: {{ $ticket->hairColor->name }}"></span>
                                    @endif
                                    <span class="text-zinc-400 text-xs font-bold uppercase tracking-wider">
                                        {{ $ticket->type === 'booking' ? 'Booking' : 'Walk-in' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Bagian Bawah (Footer TV): Status Kursi & Staff Barber yang Bertugas -->
    <footer class="border-t border-white/10 bg-zinc-900/80 px-8 py-3.5 shrink-0">
        <style>
            /* Animasi Gunting Memotong untuk Staff Sedang Bekerja */
            @keyframes scissor-cut-upper {
                0%, 100% { transform: rotate(0deg); }
                50% { transform: rotate(-20deg); }
            }
            @keyframes scissor-cut-lower {
                0%, 100% { transform: rotate(0deg); }
                50% { transform: rotate(20deg); }
            }
            .scissor-blade-upper {
                transform-origin: 12px 12px;
                animation: scissor-cut-upper 0.85s infinite ease-in-out;
            }
            .scissor-blade-lower {
                transform-origin: 12px 12px;
                animation: scissor-cut-lower 0.85s infinite ease-in-out;
            }

            /* Animasi Uap Cangkir Kopi untuk Staff Longgar */
            @keyframes steam-rise-1 {
                0% { transform: translateY(0); opacity: 0; }
                40% { opacity: 0.9; }
                100% { transform: translateY(-5px); opacity: 0; }
            }
            @keyframes steam-rise-2 {
                0% { transform: translateY(0); opacity: 0; }
                40% { opacity: 0.9; }
                100% { transform: translateY(-6px); opacity: 0; }
            }
            @keyframes steam-rise-3 {
                0% { transform: translateY(0); opacity: 0; }
                40% { opacity: 0.9; }
                100% { transform: translateY(-5px); opacity: 0; }
            }
            .steam-wisp-1 { animation: steam-rise-1 1.6s infinite ease-out; }
            .steam-wisp-2 { animation: steam-rise-2 1.6s infinite 0.5s ease-out; }
            .steam-wisp-3 { animation: steam-rise-3 1.6s infinite 1.0s ease-out; }
        </style>

        <div class="flex items-center justify-between gap-3 mb-2.5">
            <p class="text-xs tracking-[0.2em] text-zinc-400 font-extrabold uppercase flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                <span>STATUS STAFF BARBER</span>
            </p>

            <div class="flex items-center gap-3 text-xs font-extrabold">
                <span class="text-emerald-400">{{ $availableCount }} Ready</span>
                <span class="text-zinc-600">·</span>
                <span class="text-amber-400">{{ $workingCount }} Sedang Bekerja</span>
            </div>
        </div>

        @php
            $hasStaffRolling = $barberCards->count() > 4;
            $displayCards = $hasStaffRolling
                ? $barberCards->concat($barberCards->take(4)->map(fn ($c) => array_merge($c, ['is_clone' => true])))
                : $barberCards;
        @endphp

        <div class="{{ $hasStaffRolling ? 'w-full overflow-hidden relative' : '' }}" data-tv-staff-window>
            <div
                wire:ignore.self
                class="{{ $hasStaffRolling ? 'flex items-center gap-3' : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3' }}"
                data-tv-staff-track
                data-has-staff-rolling="{{ $hasStaffRolling ? 'true' : 'false' }}"
                data-total-barbers="{{ $barberCards->count() }}"
            >
                @forelse($displayCards as $card)
                    @php
                        $isClone = $card['is_clone'] ?? false;
                        $wireKey = $isClone
                            ? 'barber-status-clone-' . $card['barber']->id
                            : 'barber-status-' . $card['barber']->id;
                        $isWorking = in_array($card['state'], ['working', 'calling'], true);
                        $isAvailable = $card['state'] === 'available';

                        $cardClass = match ($card['state']) {
                            'working', 'calling' => 'bg-amber-500/10 border-amber-500/30 text-amber-200',
                            'available' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200',
                            default => 'bg-white/5 border-white/10 text-zinc-400 opacity-60',
                        };
                    @endphp
                    <div
                        wire:key="{{ $wireKey }}"
                        data-tv-staff-card
                        @if($isClone) data-is-clone="true" @endif
                        class="rounded-2xl border {{ $cardClass }} p-3 flex items-center justify-between gap-3 transition {{ $hasStaffRolling ? 'w-full sm:w-[calc((100%-12px)/2)] lg:w-[calc((100%-36px)/4)] shrink-0' : '' }}"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            @if($isWorking)
                                <!-- Ikon Animasi Gunting Memotong -->
                                <div class="w-9 h-9 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 grid place-items-center shrink-0" title="Sedang mencukur">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <g class="scissor-blade-upper">
                                            <circle cx="6" cy="6" r="3"></circle>
                                            <path d="M8.5 7.5 20 18"></path>
                                        </g>
                                        <g class="scissor-blade-lower">
                                            <circle cx="6" cy="18" r="3"></circle>
                                            <path d="M8.5 16.5 20 6"></path>
                                        </g>
                                        <circle cx="12" cy="12" r="1.2" fill="currentColor"></circle>
                                    </svg>
                                </div>
                            @elseif($isAvailable)
                                <!-- Ikon Animasi Cangkir Kopi dengan Uap Bergerak -->
                                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 grid place-items-center shrink-0" title="Kursi ready / siap melayani">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path class="steam-wisp-1 stroke-emerald-300" d="M7 3c.5.8.5 1.5 0 2.2"></path>
                                        <path class="steam-wisp-2 stroke-emerald-300" d="M11 2c.5.8.5 1.7 0 2.6"></path>
                                        <path class="steam-wisp-3 stroke-emerald-300" d="M15 3c.5.8.5 1.5 0 2.2"></path>
                                        <path d="M4 8h13v7a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4Z"></path>
                                        <path d="M17 9h1a3 3 0 0 1 0 6h-1"></path>
                                    </svg>
                                </div>
                            @else
                                <!-- Ikon Istirahat -->
                                <div class="w-9 h-9 rounded-xl bg-white/10 text-zinc-400 grid place-items-center font-extrabold text-sm shrink-0">
                                    <x-ui.icon name="coffee" class="w-4 h-4" />
                                </div>
                            @endif

                            <div class="min-w-0">
                                <p class="font-extrabold text-sm text-white truncate leading-tight">{{ $card['name'] }}</p>
                                <p class="text-[11px] text-zinc-400 truncate mt-0.5 leading-tight">{{ $card['specialty'] }}</p>
                            </div>
                        </div>

                        <!-- Status Kanan Simpel -->
                        <div class="shrink-0 text-right">
                            @if($card['state'] === 'working')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-500 text-zinc-950 font-extrabold text-xs num shadow-xs">
                                    <span>{{ $card['activeTicket']?->number }}</span>
                                </span>
                            @elseif($card['state'] === 'calling')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-sky-500 text-white font-extrabold text-xs num shadow-xs">
                                    <span>Panggil {{ $card['activeTicket']?->number }}</span>
                                </span>
                            @elseif($card['state'] === 'break')
                                <span class="px-2.5 py-1 rounded-xl bg-zinc-800 text-zinc-400 font-bold text-xs">
                                    Istirahat
                                </span>
                            @else
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-extrabold text-xs">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <span>Ready</span>
                                    @if($card['waitingCount'] > 0)
                                        <span class="text-[11px] font-semibold text-emerald-200 num">({{ $card['waitingCount'] }})</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-zinc-500 text-xs col-span-full">Belum ada staff barber yang ditugaskan di cabang ini.</p>
                @endforelse
            </div>
        </div>

        @if(! ($tenant->hasFeature('white_label') ?? false))
            <!-- Watermark Platform Resmi untuk Starter & Pro (Hilang di Paket Business / White-Label) -->
            <div class="mt-3 pt-2.5 border-t border-white/10 flex items-center justify-between text-xs text-zinc-400">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Layar Antrean Live ditenagai oleh platform resmi <strong class="text-white font-extrabold tracking-wide">{{ config('app.name', 'BarberOS') }}</strong></span>
                </div>
                <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 font-semibold uppercase tracking-wider">
                    <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-amber-500/80" />
                    <span>Official Platform</span>
                </div>
            </div>
        @endif
    </footer>

    <!-- Script Jam Real-Time, Rolling Antrean Berikutnya & Rolling Status Staff -->
    <script>
        (function() {
            // Jam Digital Real-Time
            if (!window.tvClockStarted) {
                window.tvClockStarted = true;
                setInterval(() => {
                    const clockEl = document.querySelector('[data-tv-clock]');
                    if (clockEl) {
                        const now = new Date();
                        const hours = String(now.getHours()).padStart(2, '0');
                        const minutes = String(now.getMinutes()).padStart(2, '0');
                        clockEl.textContent = `${hours}.${minutes}`;
                    }
                }, 1000);
            }

            // Rolling Antrean Berikutnya dari Bawah ke Atas Tiap 3 Detik Jika > 5
            if (window.tvRollingInterval) {
                clearInterval(window.tvRollingInterval);
            }

            const rollingTrack = document.querySelector('[data-tv-rolling-track]');
            const hasRolling = rollingTrack?.getAttribute('data-has-rolling') === 'true';

            if (rollingTrack && hasRolling && rollingTrack.children.length > 5) {
                const stepGap = 10;
                window.tvRollingInterval = setInterval(() => {
                    const firstCard = rollingTrack.firstElementChild;
                    if (!firstCard) return;

                    const stepDistance = firstCard.offsetHeight + stepGap;
                    rollingTrack.style.transition = 'transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    rollingTrack.style.transform = `translateY(-${stepDistance}px)`;

                    setTimeout(() => {
                        rollingTrack.style.transition = 'none';
                        rollingTrack.appendChild(firstCard);
                        rollingTrack.style.transform = 'translateY(0)';
                    }, 820);
                }, 3000);
            }

            // Rolling Status Staff Barber Secara Horizontal Tiap 3 Detik Jika > 4
            if (window.tvStaffRollingInterval) {
                clearInterval(window.tvStaffRollingInterval);
            }

            const staffTrack = document.querySelector('[data-tv-staff-track]');
            const hasStaffRolling = staffTrack?.getAttribute('data-has-staff-rolling') === 'true';
            const totalBarbers = parseInt(staffTrack?.getAttribute('data-total-barbers') || '0', 10);

            if (staffTrack && hasStaffRolling && totalBarbers > 4) {
                const stepGap = 12; // gap-3 = 12px
                let currentStep = 0;

                window.tvStaffRollingInterval = setInterval(() => {
                    const firstCard = staffTrack.firstElementChild;
                    if (!firstCard) return;

                    currentStep++;
                    const stepDistance = firstCard.offsetWidth + stepGap;

                    staffTrack.style.transition = 'transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    staffTrack.style.transform = `translateX(-${currentStep * stepDistance}px)`;

                    if (currentStep >= totalBarbers) {
                        setTimeout(() => {
                            staffTrack.style.transition = 'none';
                            staffTrack.style.transform = 'translateX(0)';
                            currentStep = 0;
                            void staffTrack.offsetWidth;
                        }, 820);
                    }
                }, 3000);
            }

            // Sintesis Bunyi Bel Pemanggil Premium (Melodi Ascending D Major: D5 -> F#5 -> A5 -> D6 Chime)
            function synthesizeQueueChime(ctx) {
                const now = ctx.currentTime;
                // 4 nada Chime Lonceng Emas Premium (D Major Arpeggio ke oktaf tinggi) - Total durasi pas 3.0 Detik
                // Karakter suara sangat menarik perhatian, cerah, mewah, dan menembus kebisingan salon/barbershop
                const notes = [
                    { freq: 587.33,  start: 0.00, dur: 0.50, vol: 0.40 },  // D5  (Denting pembuka)
                    { freq: 739.99,  start: 0.42, dur: 0.50, vol: 0.45 },  // F#5 (Harmoni tengah cerah)
                    { freq: 880.00,  start: 0.88, dur: 0.55, vol: 0.48 },  // A5  (Nada penegas perhatian)
                    { freq: 1174.66, start: 1.38, dur: 1.62, vol: 0.52 }   // D6  (Puncak kristal lonceng dengan resonansi panjang)
                ];

                notes.forEach(({ freq, start, dur, vol }) => {
                    const noteStartTime = now + start;
                    const noteStopTime = noteStartTime + dur;

                    // 1. Gain Envelope Utama (Crisp bell attack 8ms + Natural exponential decay)
                    const mainGain = ctx.createGain();
                    mainGain.gain.setValueAtTime(0.0001, noteStartTime);
                    mainGain.gain.exponentialRampToValueAtTime(vol, noteStartTime + 0.008);
                    mainGain.gain.exponentialRampToValueAtTime(0.0001, noteStopTime);
                    mainGain.connect(ctx.destination);

                    // 2. Osilator Utama (Sine wave murni kristal)
                    const oscPrimary = ctx.createOscillator();
                    oscPrimary.type = 'sine';
                    oscPrimary.frequency.setValueAtTime(freq, noteStartTime);
                    oscPrimary.connect(mainGain);
                    oscPrimary.start(noteStartTime);
                    oscPrimary.stop(noteStopTime + 0.05);

                    // 3. Osilator Shimmer Acoustic (Detuned +1.5 Hz untuk efek resonansi lonceng fisik asli)
                    const shimmerGain = ctx.createGain();
                    shimmerGain.gain.setValueAtTime(0.0001, noteStartTime);
                    shimmerGain.gain.exponentialRampToValueAtTime(vol * 0.35, noteStartTime + 0.012);
                    shimmerGain.gain.exponentialRampToValueAtTime(0.0001, noteStartTime + dur * 0.85);
                    shimmerGain.connect(ctx.destination);

                    const oscShimmer = ctx.createOscillator();
                    oscShimmer.type = 'sine';
                    oscShimmer.frequency.setValueAtTime(freq + 1.5, noteStartTime);
                    oscShimmer.connect(shimmerGain);
                    oscShimmer.start(noteStartTime);
                    oscShimmer.stop(noteStopTime + 0.05);

                    // 4. Bell Strike Overtone (Dentuman logam lonceng pipa tembaga: ~2.76x frekuensi dengan decay cepat)
                    const overtoneGain = ctx.createGain();
                    overtoneGain.gain.setValueAtTime(0.0001, noteStartTime);
                    overtoneGain.gain.exponentialRampToValueAtTime(vol * 0.25, noteStartTime + 0.006);
                    overtoneGain.gain.exponentialRampToValueAtTime(0.0001, noteStartTime + Math.min(dur * 0.45, 0.4));
                    overtoneGain.connect(ctx.destination);

                    const oscOvertone = ctx.createOscillator();
                    oscOvertone.type = 'triangle';
                    oscOvertone.frequency.setValueAtTime(freq * 2.76, noteStartTime);
                    oscOvertone.connect(overtoneGain);
                    oscOvertone.start(noteStartTime);
                    oscOvertone.stop(noteStartTime + 0.45);
                });
            }

            // Pemanggil Audio Bel Antrean Otomatis
            window.playQueueChime = function() {
                try {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;

                    if (!window.tvAudioCtx) {
                        window.tvAudioCtx = new AudioContextClass();
                    }
                    const ctx = window.tvAudioCtx;

                    if (ctx.state === 'running') {
                        synthesizeQueueChime(ctx);
                    } else if (ctx.state === 'suspended') {
                        ctx.resume().then(() => {
                            if (ctx.state === 'running') {
                                synthesizeQueueChime(ctx);
                            }
                        }).catch(() => {
                            // Senyap tanpa memicu console warning jika diblokir oleh browser sebelum ada interaksi
                        });
                    }
                } catch (e) {
                    // Senyap tanpa mengganggu layar TV
                }
            };

            // Listener pasif di latar belakang (remote TV, keyboard, pointer) untuk auto-resume AudioContext tanpa UI apapun
            if (!window.tvPassiveAudioBound) {
                window.tvPassiveAudioBound = true;
                ['keydown', 'pointerdown', 'touchstart', 'click'].forEach((evt) => {
                    window.addEventListener(evt, () => {
                        try {
                            if (window.tvAudioCtx && window.tvAudioCtx.state === 'suspended') {
                                window.tvAudioCtx.resume().catch(() => {});
                            }
                        } catch (e) {}
                    }, { passive: true });
                });
            }

            // Deteksi Panggilan Antrean Baru / Panggil Ulang
            function checkCalledTicketAudio() {
                const tvRoot = document.querySelector('[data-tv-root]');
                if (!tvRoot) return;

                const currentKey = tvRoot.getAttribute('data-called-key') || '';
                if (currentKey !== '') {
                    if (window.lastTvCalledKey !== undefined && window.lastTvCalledKey !== currentKey) {
                        window.playQueueChime();
                    }
                    window.lastTvCalledKey = currentKey;
                } else {
                    window.lastTvCalledKey = '';
                }
            }

            // Inisialisasi awal tanpa memicu bunyi saat reload pertama
            const tvRootInit = document.querySelector('[data-tv-root]');
            if (tvRootInit && window.lastTvCalledKey === undefined) {
                window.lastTvCalledKey = tvRootInit.getAttribute('data-called-key') || '';
            }

            // Pasang MutationObserver agar setiap polling Livewire langsung memicu bel bila ada panggilan
            if (!window.tvObserverActive) {
                window.tvObserverActive = true;
                const observerTarget = document.querySelector('[data-tv-root]');
                if (observerTarget) {
                    const observer = new MutationObserver(function() {
                        checkCalledTicketAudio();
                    });
                    observer.observe(observerTarget, { attributes: true, attributeFilter: ['data-called-key'] });
                }
            }
        })();
    </script>
    @endif
</div>
