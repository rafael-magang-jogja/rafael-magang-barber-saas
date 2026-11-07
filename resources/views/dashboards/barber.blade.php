<x-layouts.barber
    :title="'Staff — ' . ($barberMembership?->display_name ?? 'Barber')"
    :barber-membership="$barberMembership"
>
    @php
        $hasCheckedInToday = $todayAttendance !== null && $todayAttendance->check_in_at !== null && $todayAttendance->check_out_at === null;
        $hasCheckedOutToday = $todayAttendance !== null && $todayAttendance->check_out_at !== null;
    @endphp

    <!-- Notifikasi / Flash Messages Auto-Dismiss (3 Detik & Gradasi) -->
    <x-ui.flash-alert />

    @if(! $hasCheckedInToday)
        @if($hasCheckedOutToday)
            <div class="p-3.5 rounded-2xl bg-zinc-100 border border-zinc-200/90 flex items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-zinc-200 text-zinc-700 grid place-items-center shrink-0 font-bold">
                        <x-ui.icon name="clock" class="w-4 h-4 text-zinc-600" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-extrabold text-zinc-900 truncate">Shift Selesai (Sudah Absen Pulang)</p>
                        <p class="text-[11px] text-zinc-500">Anda sudah absen pulang pukul {{ $todayAttendance->check_out_at->format('H:i') }} WIB.</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-xl bg-zinc-200 text-zinc-700 font-bold text-[11px] shrink-0">
                    Shift Selesai
                </span>
            </div>
        @else
            <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-700 grid place-items-center shrink-0 font-bold">
                        <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-extrabold text-rose-950 truncate">Belum Absen Masuk Hari Ini</p>
                        <p class="text-[11px] text-rose-800">Scan QR cabang untuk mulai mengambil antrean.</p>
                    </div>
                </div>
                <a
                    href="{{ route('barber.attendance') }}"
                    class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs shrink-0 transition shadow-xs"
                >
                    Absen Sekarang
                </a>
            </div>
        @endif
    @endif

    <!-- Ringkasan Kinerja Hari Ini (Mobile Stat Grid) -->
    <div class="grid grid-cols-3 gap-2">
        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Selesai</span>
            <p class="text-xl font-black text-zinc-950 num mt-0.5">{{ $completedToday }}</p>
            <span class="text-[10px] text-zinc-400">pelanggan</span>
        </div>

        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Komisi</span>
            <p class="text-xs font-black text-emerald-700 num mt-1 truncate">
                Rp{{ number_format($commissionToday, 0, ',', '.') }}
            </p>
            <span class="text-[10px] text-zinc-400">hari ini</span>
        </div>

        <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs text-center">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Menunggu</span>
            <p class="text-xl font-black text-amber-600 num mt-0.5">{{ $waitingQueue->count() }}</p>
            <span class="text-[10px] text-zinc-400">di cabang</span>
        </div>
    </div>

    <!-- SECTION 1: PELANGGAN DI KURSI ANDA (TUGAS AKTIF) -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <x-ui.icon name="users" class="w-3.5 h-3.5 text-amber-600" />
                <span>Pelanggan di Kursi Anda</span>
            </h2>
            @if($servingTicket)
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
            @endif
        </div>

        @if($servingTicket)
            <!-- KARTU TUGAS AKTIF -->
            <div class="rounded-3xl bg-white border-2 {{ $servingTicket->status === \App\Enums\QueueStatus::InService ? 'border-emerald-500' : 'border-amber-500' }} shadow-lg shadow-zinc-950/5 p-4 space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-3xl font-black text-zinc-950 num">{{ $servingTicket->number }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $servingTicket->type === 'booking' ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700' }}">
                                {{ $servingTicket->type === 'booking' ? 'Booking' : 'Walk-in' }}
                            </span>
                        </div>
                        <p class="font-bold text-sm text-zinc-900 mt-1">
                            {{ $servingTicket->customerProfile?->name ?? 'Pelanggan' }}
                        </p>
                        @if($servingTicket->customerProfile?->phone)
                            <p class="text-xs text-zinc-500">{{ $servingTicket->customerProfile->phone }}</p>
                        @endif
                    </div>

                    <!-- Badge Status Aktif -->
                    <div>
                        @if($servingTicket->status === \App\Enums\QueueStatus::Called)
                            <span class="px-2.5 py-1 rounded-xl bg-amber-500 text-zinc-950 text-xs font-black animate-pulse inline-flex items-center gap-1 shadow-sm">
                                <x-ui.icon name="megaphone" class="w-3.5 h-3.5" />
                                <span>Dipanggil</span>
                            </span>
                        @elseif($servingTicket->status === \App\Enums\QueueStatus::InService)
                            <span class="px-2.5 py-1 rounded-xl bg-emerald-600 text-white text-xs font-black inline-flex items-center gap-1 shadow-sm">
                                <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                                <span>Sedang Dicukur</span>
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Rincian Layanan & Catatan -->
                <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-zinc-700">
                        <span class="text-zinc-500">Layanan:</span>
                        <span class="font-bold text-zinc-900 text-right">{{ $servingTicket->items->pluck('service_name')->join(' + ') }}</span>
                    </div>

                    @if($servingTicket->hairColor)
                        <div class="flex items-center justify-between text-zinc-700">
                            <span class="text-zinc-500">Warna Rambut:</span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-zinc-900">
                                <span class="w-3 h-3 rounded-full border border-black/20 shrink-0" @style(['background-color: ' . $servingTicket->hairColor->hex_code])></span>
                                <span>{{ $servingTicket->hairColor->name }}</span>
                            </span>
                        </div>
                    @endif

                    @if(!empty($servingTicket->notes))
                        <div class="pt-1.5 border-t border-zinc-200/60">
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Catatan Gaya Rambut:</span>
                            <p class="text-zinc-900 font-medium mt-0.5">{{ $servingTicket->notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- AKSI KONTROL TIKET AKTIF -->
                @if($servingTicket->status === \App\Enums\QueueStatus::Called)
                    <div class="space-y-2.5 pt-1">
                        <!-- Info Toleransi Waktu Panggilan (1 Menit) & Status Lewat -->
                        <div class="flex items-center justify-between text-[11px] text-zinc-500 px-1">
                            <span class="flex items-center gap-1">
                                <x-ui.icon name="clock" class="w-3.5 h-3.5 text-zinc-400" />
                                <span>Dipanggil: {{ $servingTicket->called_at ? $servingTicket->called_at->format('H:i') . ' WIB' : 'Baru saja' }}</span>
                            </span>
                            <span class="font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60" id="skip-tolerance-badge">
                                Toleransi: 1 menit
                            </span>
                        </div>

                        @if($servingTicket->skip_count >= 1)
                            <div class="p-2.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-1.5 font-bold">
                                    <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                    <span>Panggilan Ke-2 (Terakhir)</span>
                                </span>
                                <span class="text-[10px] text-rose-700 font-semibold">Dilewati lagi = Batal Otomatis</span>
                            </div>
                        @endif

                        @php
                            $isTicketPaid = $servingTicket->order !== null && $servingTicket->order->payment_status === \App\Enums\PaymentStatus::Paid;
                        @endphp

                        @if($isTicketPaid)
                            <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between text-xs font-bold text-emerald-800">
                                <span class="flex items-center gap-1.5">
                                    <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span>Pembayaran Sudah Lunas</span>
                                </span>
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-md bg-white border border-emerald-200 text-emerald-700">
                                    Siap Dicukur
                                </span>
                            </div>

                            <!-- Tombol Utama: Mulai Cukur (Customer Hadir & Sudah Bayar) -->
                            <form method="POST" action="{{ route('cashier.tickets.transition', $servingTicket) }}">
                                @csrf
                                <input type="hidden" name="status" value="in_service" />
                                <button
                                    type="submit"
                                    class="w-full py-3.5 px-5 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-[0.99] text-zinc-950 font-black text-sm flex items-center justify-center gap-2 shadow-md shadow-zinc-950/10 transition cursor-pointer"
                                >
                                    <x-ui.icon name="scissors" class="w-5 h-5 text-zinc-950" />
                                    <span>Pelanggan Hadir &bull; Mulai Cukur</span>
                                </button>
                            </form>
                        @else
                            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-950 text-left space-y-1">
                                <div class="flex items-center gap-1.5 font-extrabold text-xs text-rose-700">
                                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                                    <span>Belum Bayar: Wajib Lunas Sebelum Dimulai</span>
                                </div>
                                <p class="text-[11px] text-rose-800 leading-tight">
                                    Sesuai ketentuan, pelanggan wajib menyelesaikan pembayaran di kasir atau via QRIS sebelum potong rambut dimulai. Mohon arahkan pelanggan ke kasir terlebih dahulu.
                                </p>
                            </div>

                            <button
                                type="button"
                                disabled
                                class="w-full py-3.5 px-5 rounded-2xl bg-zinc-200 text-zinc-400 font-bold text-sm flex items-center justify-center gap-2 cursor-not-allowed select-none border border-zinc-300"
                                title="Pelanggan wajib melunasi pembayaran di kasir sebelum mulai cukur"
                            >
                                <x-ui.icon name="lock" class="w-4 h-4 text-zinc-400" />
                                <span>Menunggu Pembayaran Kasir</span>
                            </button>
                        @endif

                        <!-- Tombol Alternatif: Panggil Ulang (TV) & Lewati Antrean (Mundur 1 / Batal Otomatis) -->
                        <div class="grid grid-cols-2 gap-2 pt-0.5">
                            <!-- 1. Tombol Panggil Ulang -->
                            <form method="POST" action="{{ route('cashier.tickets.recall', $servingTicket) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full py-2.5 px-3 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-[0.99] text-zinc-800 font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer border border-zinc-200/80 shadow-2xs"
                                    title="Panggil ulang nomor antrean ke audio layar TV"
                                >
                                    <x-ui.icon name="megaphone" class="w-3.5 h-3.5 text-zinc-600" />
                                    <span>Panggil Ulang</span>
                                </button>
                            </form>

                            <!-- 2. Tombol Lewati Antrean (Terkunci selama 1 menit pertama panggilan) -->
                            @php
                                $isLastCall = ($servingTicket->skip_count >= 1);
                                $calledTimestamp = $servingTicket->called_at ? $servingTicket->called_at->timestamp : now()->timestamp;
                                $secondsElapsed = max(0, now()->timestamp - $calledTimestamp);
                                $isSkipUnlocked = $secondsElapsed >= 60;
                                $remainingSeconds = max(0, 60 - $secondsElapsed);
                            @endphp
                            <button
                                type="button"
                                id="btn-skip-ticket"
                                data-called-timestamp="{{ $calledTimestamp }}"
                                data-is-last-call="{{ $isLastCall ? '1' : '0' }}"
                                @if($isSkipUnlocked)
                                    onclick="openSkipModal()"
                                    class="w-full py-2.5 px-3 rounded-xl {{ $isLastCall ? 'bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300' }} active:scale-[0.99] font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-2xs"
                                @else
                                    disabled
                                    class="w-full py-2.5 px-3 rounded-xl bg-zinc-100 text-zinc-400 border border-zinc-200 font-bold text-xs flex items-center justify-center gap-1.5 cursor-not-allowed select-none shadow-2xs transition"
                                @endif
                                title="{{ $isLastCall ? 'Batalkan tiket otomatis (2x tidak hadir)' : 'Mundurkan 1 posisi antrean' }}"
                            >
                                <x-ui.icon name="skip-forward" class="w-3.5 h-3.5 {{ $isSkipUnlocked ? ($isLastCall ? 'text-rose-600' : 'text-amber-700') : 'text-zinc-400' }}" id="skip-btn-icon" />
                                <span id="skip-btn-text">
                                    @if($isSkipUnlocked)
                                        {{ $isLastCall ? 'Lewati (Batal)' : 'Lewati (Mundur 1)' }}
                                    @else
                                        Lewati ({{ $remainingSeconds }}s)
                                    @endif
                                </span>
                            </button>
                        </div>
                        <p class="text-[10px] text-center text-zinc-400" id="skip-hint-text">
                            @if($isSkipUnlocked)
                                Tekan 'Panggil Ulang' jika suara belum terdengar, atau 'Lewati' jika customer belum datang.
                            @else
                                Tombol Lewati akan terbuka setelah 1 menit panggilan (<span id="skip-timer-counter" class="font-bold text-amber-600 num">{{ $remainingSeconds }} detik lagi</span>).
                            @endif
                        </p>
                    </div>
                @elseif($servingTicket->status === \App\Enums\QueueStatus::InService)
                    <form method="POST" action="{{ route('cashier.tickets.transition', $servingTicket) }}" class="space-y-1.5 pt-1">
                        @csrf
                        <input type="hidden" name="status" value="completed" />
                        <button
                            type="submit"
                            class="w-full py-3.5 px-5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white font-black text-sm flex items-center justify-center gap-2 shadow-md shadow-zinc-950/15 transition cursor-pointer"
                        >
                            <x-ui.icon name="check" class="w-5 h-5 stroke-[2.5]" />
                            <span>Selesai Cukur</span>
                        </button>
                        <p class="text-[10px] text-center text-zinc-400 leading-tight">
                            Menekan Selesai Cukur akan menyelesaikan pengerjaan layanan. Pembayaran telah diselesaikan di muka.
                        </p>
                    </form>
                @endif
            </div>
        @else
            <!-- KARTU KURSI KOSONG / IDLE -->
            <div class="rounded-3xl bg-white border border-zinc-200/80 shadow-sm p-5 text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 grid place-items-center mx-auto">
                    <x-ui.icon name="check" class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-zinc-900">Kursi Anda Sedang Kosong</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Tugas sebelumnya telah selesai. Anda siap memanggil pelanggan berikutnya sesuai giliran antrean di bawah.
                    </p>
                </div>
            </div>
        @endif
    </div>

    <!-- SECTION 2: ANTREAN MENUNGGU DI CABANG (SESUAI ANTRIAN) -->
    <div class="space-y-2 pt-1">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <x-ui.icon name="ticket" class="w-3.5 h-3.5 text-amber-600" />
                <span>Antrean Menunggu di Cabang</span>
            </h2>
            <span class="text-xs font-bold text-zinc-500 num">{{ $waitingQueue->count() }} Menunggu</span>
        </div>

        @if($waitingQueue->isNotEmpty())
            @php
                $nextTicket = $waitingQueue->first();
                $remainingTickets = $waitingQueue->slice(1);
                $isBarberReady = $barberMembership?->work_status === \App\Enums\WorkStatus::Active;
            @endphp

            <!-- KARTU GILIRAN BERIKUTNYA -->
            <div class="rounded-3xl bg-white border-2 border-zinc-200 shadow-md p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-800 text-[10px] font-black uppercase tracking-wider">
                        Giliran Berikutnya
                    </span>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl font-black text-zinc-950 num">{{ $nextTicket->number }}</span>
                            <span class="font-bold text-xs text-zinc-800">{{ $nextTicket->customerProfile?->name ?? 'Pelanggan' }}</span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            {{ $nextTicket->items->pluck('service_name')->join(', ') }}
                        </p>
                    </div>

                    @if($nextTicket->hairColor)
                        <span class="w-4 h-4 rounded-full border border-black/20 shrink-0" @style(['background-color: ' . $nextTicket->hairColor->hex_code]) title="{{ $nextTicket->hairColor->name }}"></span>
                    @endif
                </div>

                @if(!empty($nextTicket->notes))
                    <div class="p-2 rounded-xl bg-zinc-50 border border-zinc-200/60 text-xs">
                        <span class="text-[10px] text-zinc-400 font-bold block">Catatan:</span>
                        <p class="text-zinc-800 font-medium">{{ $nextTicket->notes }}</p>
                    </div>
                @endif

                <!-- TOMBOL PANGGIL / KONDISI TUGAS -->
                @if($hasActiveTask)
                    <!-- TERKUNCI KARENA BELUM MENYELESAIKAN TUGAS SEBELUMNYA -->
                    <div class="space-y-1">
                        <button
                            type="button"
                            disabled
                            class="w-full min-h-[44px] px-4 py-2.5 rounded-2xl bg-zinc-100 text-zinc-400 border border-zinc-200 font-bold text-xs flex items-center justify-center gap-1.5 cursor-not-allowed"
                        >
                            <x-ui.icon name="ticket" class="w-4 h-4" />
                            <span>Selesaikan Layanan Saat Ini Terlebih Dahulu</span>
                        </button>
                        <p class="text-[10px] text-center text-zinc-500">
                            Anda sedang menangani tiket <b>{{ $servingTicket->number }}</b>. Selesaikan cukur sebelum memanggil pelanggan berikutnya.
                        </p>
                    </div>
                @elseif(! $hasCheckedInToday)
                    <!-- BELUM ABSEN MASUK ATAU SUDAH ABSEN PULANG -->
                    <div class="p-4 rounded-2xl {{ $hasCheckedOutToday ? 'bg-zinc-100 border border-zinc-200' : 'bg-rose-50 border border-rose-200' }} text-center space-y-2">
                        <div class="flex items-center justify-center gap-2 {{ $hasCheckedOutToday ? 'text-zinc-800' : 'text-rose-800' }} font-bold text-xs">
                            <x-ui.icon name="{{ $hasCheckedOutToday ? 'clock' : 'alert-triangle' }}" class="w-4 h-4 {{ $hasCheckedOutToday ? 'text-zinc-600' : 'text-rose-600' }} shrink-0" />
                            <span>{{ $hasCheckedOutToday ? 'Shift Kerja Anda Hari Ini Telah Selesai' : 'Anda Belum Melakukan Absen Masuk Hari Ini' }}</span>
                        </div>
                        <p class="text-[11px] {{ $hasCheckedOutToday ? 'text-zinc-600' : 'text-rose-700' }}">
                            {{ $hasCheckedOutToday ? 'Anda sudah absen pulang pada pukul ' . $todayAttendance->check_out_at->format('H:i') . ' WIB, sehingga tidak dapat mengambil antrean baru.' : 'Lakukan scan QR kehadiran di cabang Anda terlebih dahulu untuk dapat memanggil dan melayani antrean pelanggan.' }}
                        </p>
                        @if(! $hasCheckedOutToday)
                            <a
                                href="{{ route('barber.attendance') }}"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-xs transition"
                            >
                                <x-ui.icon name="clock" class="w-3.5 h-3.5 text-white" />
                                <span>Buka Menu Absensi Sekarang</span>
                            </a>
                        @endif
                    </div>
                @elseif(! $isBarberReady)
                    <!-- STATUS KERJA BUKAN READY -->
                    <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-center space-y-1">
                        <p class="text-xs font-bold text-amber-900">Status Anda Sedang {{ $barberMembership?->work_status?->value === 'break' ? 'Istirahat' : 'Off' }}</p>
                        <p class="text-[11px] text-amber-700">
                            Ubah status kerja Anda di pojok kanan atas menjadi <b>Ready</b> untuk dapat memanggil antrean ini.
                        </p>
                    </div>
                @else
                    <!-- STAFF READY DAN SUDAH SELESAI TUGAS SEBELUMNYA: BISA PANGGIL SESUAI ANTRIAN -->
                    <form method="POST" action="{{ route('cashier.tickets.transition', $nextTicket) }}">
                        @csrf
                        <input type="hidden" name="status" value="called" />
                        <input type="hidden" name="barber_membership_id" value="{{ $barberMembership->id }}" />
                        <x-ui.button
                            type="submit"
                            size="lg"
                            class="w-full bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black shadow-lg"
                        >
                            <x-ui.icon name="megaphone" class="w-5 h-5" />
                            <span>Panggil Antrean Ini ({{ $nextTicket->number }})</span>
                        </x-ui.button>
                    </form>
                @endif
            </div>

            <!-- ANTREAN LAINNYA DI BELAKANGNYA (JIKA ADA) -->
            @if($remainingTickets->isNotEmpty())
                <div class="pt-1">
                    <p class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider mb-2">Antrean Selanjutnya:</p>
                    <div class="space-y-2">
                        @foreach($remainingTickets as $rem)
                            <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex items-center justify-between gap-2 text-xs">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-black text-zinc-900 num">{{ $rem->number }}</span>
                                        <span class="text-zinc-400">·</span>
                                        <span class="font-bold text-zinc-800">{{ $rem->customerProfile?->name ?? 'Pelanggan' }}</span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 mt-0.5 truncate">
                                        {{ $rem->items->pluck('service_name')->join(', ') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <!-- TIDAK ADA ANTREAN MENUNGGU -->
            <div class="rounded-3xl bg-white border border-zinc-200/80 shadow-sm p-6 text-center space-y-1">
                <p class="font-extrabold text-xs text-zinc-800">Tidak Ada Antrean Menunggu</p>
                <p class="text-[11px] text-zinc-400">Semua pelanggan pada cabang ini telah terlayani.</p>
            </div>
        @endif
    </div>

    @if($servingTicket && $servingTicket->status === \App\Enums\QueueStatus::Called)
        @php
            $isLastCall = ($servingTicket->skip_count >= 1);
        @endphp
        <!-- Mini Popup Modal Konfirmasi Lewati Antrean -->
        <div id="skip-ticket-modal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200">
            <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200/80 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <!-- Header Icon & Judul -->
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 rounded-2xl {{ $isLastCall ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-amber-50 text-amber-600 border border-amber-200' }} grid place-items-center shrink-0">
                        <x-ui.icon name="{{ $isLastCall ? 'alert-triangle' : 'skip-forward' }}" class="w-6 h-6 stroke-[2.5]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base text-zinc-950">
                            {{ $isLastCall ? 'Lewati & Batalkan Antrean?' : 'Lewati Antrean Ini?' }}
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            {{ $isLastCall 
                                ? 'Ini adalah panggilan ke-2. Melewati antrean ini akan membatalkan tiket secara otomatis.' 
                                : 'Pelanggan belum hadir. Urutan antrean akan dimundurkan 1 posisi dan antrean berikutnya akan dipanggil.' }}
                        </p>
                    </div>
                </div>

                <!-- Box Rincian Tiket -->
                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-500 font-medium">Nomor Antrean:</span>
                        <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-black text-xs num">{{ $servingTicket->number }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-500 font-medium">Pelanggan:</span>
                        <span class="font-extrabold text-zinc-900 truncate">{{ $servingTicket->customerProfile?->name ?? 'Pelanggan' }}</span>
                    </div>
                    @if($isLastCall)
                        <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs font-bold text-rose-800 flex items-center gap-1.5">
                            <x-ui.icon name="x-circle" class="w-4 h-4 text-rose-600 shrink-0" />
                            <span>Tiket akan otomatis dibatalkan (No-Show).</span>
                        </div>
                    @else
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs font-bold text-amber-900 flex items-center gap-1.5">
                            <x-ui.icon name="clock" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Dimundurkan 1 antrean (kesempatan ke-2).</span>
                        </div>
                    @endif
                </div>

                <!-- Tombol Aksi Batal / Ya, Lewati -->
                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    <button
                        type="button"
                        onclick="closeSkipModal()"
                        class="min-h-[42px] px-4 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-extrabold text-xs transition cursor-pointer text-center"
                    >
                        Batal
                    </button>
                    <form
                        method="POST"
                        action="{{ route('cashier.tickets.skip', $servingTicket) }}"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="w-full min-h-[42px] px-4 py-2.5 rounded-xl {{ $isLastCall ? 'bg-rose-600 hover:bg-rose-500 active:scale-95 text-white shadow-rose-600/20' : 'bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 shadow-amber-500/20' }} font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-md transition cursor-pointer text-center"
                        >
                            <x-ui.icon name="skip-forward" class="w-4 h-4" />
                            <span>{{ $isLastCall ? 'Ya, Batalkan' : 'Ya, Lewati' }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Script Modal & Poller Otomatis -->
    <script>
        function openSkipModal() {
            const modal = document.getElementById('skip-ticket-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSkipModal() {
            const modal = document.getElementById('skip-ticket-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.getElementById('skip-ticket-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSkipModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSkipModal();
            }
        });

        // Countdown Timer: Tombol Lewati hanya terbuka setelah 1 menit panggilan
        (function () {
            const btn = document.getElementById('btn-skip-ticket');
            if (!btn) return;

            const calledTimestamp = parseInt(btn.dataset.calledTimestamp || '0', 10);
            const isLastCall = btn.dataset.isLastCall === '1';
            const icon = document.getElementById('skip-btn-icon');
            const text = document.getElementById('skip-btn-text');
            const hint = document.getElementById('skip-hint-text');
            const counter = document.getElementById('skip-timer-counter');

            function updateSkipCountdown() {
                if (!calledTimestamp) return;
                const nowSec = Math.floor(Date.now() / 1000);
                const elapsed = Math.max(0, nowSec - calledTimestamp);
                const remaining = Math.max(0, 60 - elapsed);

                if (remaining > 0) {
                    btn.disabled = true;
                    btn.onclick = null;
                    btn.className = 'w-full py-2.5 px-3 rounded-xl bg-zinc-100 text-zinc-400 border border-zinc-200 font-bold text-xs flex items-center justify-center gap-1.5 cursor-not-allowed select-none shadow-2xs transition';
                    if (icon) icon.className = 'w-3.5 h-3.5 text-zinc-400';
                    if (text) text.textContent = `Lewati (${remaining}s)`;
                    if (counter) counter.textContent = `${remaining} detik lagi`;
                } else {
                    btn.disabled = false;
                    btn.onclick = openSkipModal;
                    if (isLastCall) {
                        btn.className = 'w-full py-2.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-300 active:scale-[0.99] font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-2xs';
                        if (icon) icon.className = 'w-3.5 h-3.5 text-rose-600';
                        if (text) text.textContent = 'Lewati (Batal)';
                    } else {
                        btn.className = 'w-full py-2.5 px-3 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 active:scale-[0.99] font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-2xs';
                        if (icon) icon.className = 'w-3.5 h-3.5 text-amber-700';
                        if (text) text.textContent = 'Lewati (Mundur 1)';
                    }
                    if (hint) {
                        hint.textContent = "Tekan 'Panggil Ulang' jika suara belum terdengar, atau 'Lewati' jika customer belum datang.";
                    }
                }
            }

            updateSkipCountdown();
            const skipTimer = setInterval(() => {
                updateSkipCountdown();
                const nowSec = Math.floor(Date.now() / 1000);
                if ((nowSec - calledTimestamp) >= 60) {
                    clearInterval(skipTimer);
                }
            }, 1000);
        })();

        (function () {
            setInterval(() => {
                const skipModal = document.getElementById('skip-ticket-modal');
                if (skipModal && !skipModal.classList.contains('hidden')) {
                    return;
                }
                // Jangan reload jika user sedang berinteraksi dengan form input
                if (document.activeElement && ['INPUT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement.tagName)) {
                    return;
                }
                window.location.reload();
            }, 8000);
        })();
    </script>
</x-layouts.barber>
