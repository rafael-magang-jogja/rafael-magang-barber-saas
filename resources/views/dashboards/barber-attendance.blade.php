@php
    $daysName = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        0 => 'Minggu',
    ];

    $todayDayOfWeek = now()->dayOfWeek;
    $scannerAction = ($todayAttendance && $todayAttendance->check_in_at && !$todayAttendance->check_out_at) ? 'check_out' : 'check_in';
@endphp

<x-layouts.barber
    :title="'Absensi & Jadwal — ' . ($barberMembership?->display_name ?? 'Barber')"
    :barber-membership="$barberMembership"
>
    <!-- Notifikasi Flash -->
    <x-ui.flash-alert />

    <!-- BANNER IZIN HARI INI JIKA DI-ACC OWNER -->
    @if($todayTimeOff)
        <div class="p-4 rounded-3xl bg-emerald-50 border border-emerald-200/90 shadow-2xs space-y-2">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-700 flex items-center justify-center font-black shrink-0">
                    <x-ui.icon name="check-circle" class="w-5 h-5 text-emerald-600" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-sm text-emerald-950">Izin Hari Ini Disetujui ({{ $todayTimeOff->type_label }})</h3>
                    <p class="text-xs text-emerald-700 font-medium">Bebas dari shift kerja hari ini tanpa konsekuensi &amp; jam kerja diakui penuh.</p>
                </div>
            </div>
            @if($todayTimeOff->reason)
                <p class="text-[11px] text-emerald-800 bg-white/80 p-2.5 rounded-xl border border-emerald-200/60">
                    <strong>Alasan:</strong> {{ $todayTimeOff->reason }}
                </p>
            @endif
        </div>
    @endif

    <!-- 1. KARTU ABSENSI HARI INI -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-black">
                    <x-ui.icon name="clock" class="w-4 h-4" />
                </span>
                <div>
                    <h2 class="font-black text-sm text-zinc-950">Absensi Hari Ini</h2>
                    <p class="text-[11px] text-zinc-500">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
                </div>
            </div>
            <livewire:attendance-clock />
        </div>

        <!-- Detail Jam Masuk & Pulang -->
        <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/70 space-y-1">
                <span class="text-[10px] font-bold text-zinc-400 block uppercase tracking-wider">Jam Masuk</span>
                @if($todayAttendance && $todayAttendance->check_in_at)
                    <p class="font-black font-mono text-emerald-700 text-sm">
                        {{ $todayAttendance->check_in_at->format('H:i') }} WIB
                    </p>
                    <span class="text-[10px] text-emerald-600 font-medium block">
                        {{ $todayAttendance->late_minutes > 0 ? "Telat {$todayAttendance->late_minutes}m" : "Tepat Waktu" }}
                    </span>
                @else
                    <p class="font-mono text-zinc-400 text-sm">--:--</p>
                    <span class="text-[10px] text-zinc-400 block">Belum tercatat</span>
                @endif
            </div>

            <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/70 space-y-1">
                <span class="text-[10px] font-bold text-zinc-400 block uppercase tracking-wider">Jam Pulang</span>
                @if($todayAttendance && $todayAttendance->check_out_at)
                    <p class="font-black font-mono text-amber-700 text-sm">
                        {{ $todayAttendance->check_out_at->format('H:i') }} WIB
                    </p>
                    <span class="text-[10px] text-zinc-500 block">Selesai shift</span>
                @else
                    <p class="font-mono text-zinc-400 text-sm">--:--</p>
                    <span class="text-[10px] text-zinc-400 block">Belum pulang</span>
                @endif
            </div>
        </div>

        <!-- Tombol Aksi Absensi & Pengajuan Izin -->
        <div class="space-y-2">
            @if($todayAttendance && $todayAttendance->check_in_at && $todayAttendance->check_out_at)
                <div class="p-3 rounded-2xl bg-zinc-100 text-center text-xs font-bold text-zinc-600 flex items-center justify-center gap-1.5">
                    <x-ui.icon name="check" class="w-4 h-4 text-emerald-600" />
                    <span>Absensi Hari Ini Lengkap (Selesai Shift)</span>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick="openStaffQrModal()"
                        class="flex-1 py-3.5 px-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-extrabold text-xs flex items-center justify-center gap-2.5 shadow-md shadow-zinc-950/20 transition cursor-pointer"
                    >
                        <x-ui.icon name="ticket" class="w-4 h-4 text-amber-400 stroke-[2.5]" />
                        <span>Tampilkan QR Absen Saya</span>
                    </button>
                    <button
                        type="button"
                        onclick="triggerQuickDownloadStaffQr('{{ addslashes($barberMembership?->display_name ?? 'Barber') }}', '{{ addslashes($barberMembership?->branch?->name ?? 'Cabang Outlet') }}', 'barber-staff-qrcode', 'barber-staff-qr-script')"
                        class="p-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs flex items-center justify-center shadow-md shadow-amber-500/20 transition cursor-pointer shrink-0"
                        title="Unduh QR Code Absen ke Galeri HP"
                    >
                        <x-ui.icon name="download" class="w-4 h-4 text-zinc-950" />
                    </button>
                </div>
            @endif

            <button
                type="button"
                onclick="document.getElementById('modal-request-time-off').showModal()"
                class="w-full py-3 px-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs flex items-center justify-center gap-2 transition cursor-pointer shadow-xs"
            >
                <x-ui.icon name="file-text" class="w-4 h-4 text-zinc-950" />
                <span>Ajukan Permohonan Izin (Cuti / Sakit / Libur)</span>
            </button>
        </div>
    </div>

    <!-- 2. KARTU STATUS KERJA & JAM ISTIRAHAT (Hanya tampil jika sudah absen masuk dan belum absen pulang) -->
    @if($todayAttendance && $todayAttendance->check_in_at && ! $todayAttendance->check_out_at)
    @php
        $currentWorkStatus = $barberMembership?->work_status?->value ?? 'active';
        $isOnBreak = $currentWorkStatus === 'break';
    @endphp
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3.5">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-2xl {{ $isOnBreak ? 'bg-amber-500/10 text-amber-600' : 'bg-emerald-500/10 text-emerald-600' }} flex items-center justify-center font-black shrink-0">
                    <x-ui.icon name="{{ $isOnBreak ? 'coffee' : 'scissors' }}" class="w-4 h-4" />
                </span>
                <div>
                    <h2 class="font-black text-sm text-zinc-950">Status Jam Kerja &amp; Istirahat</h2>
                    <p class="text-[11px] text-zinc-500">
                        {{ $isOnBreak ? 'Kursi Anda sedang dijeda sementara' : 'Kursi Anda siap menerima antrean pangkas' }}
                    </p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-xl text-xs font-black uppercase tracking-wider shrink-0 {{ $isOnBreak ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                {{ $isOnBreak ? 'Sedang Istirahat' : 'Aktif (Ready)' }}
            </span>
        </div>

        <div class="p-3 rounded-2xl {{ $isOnBreak ? 'bg-amber-50/70 border border-amber-200/70 text-amber-950' : 'bg-zinc-50 border border-zinc-200/70 text-zinc-600' }} text-xs flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full {{ $isOnBreak ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }} shrink-0"></span>
                <span class="text-xs font-semibold leading-relaxed">
                    {{ $isOnBreak ? 'Masa istirahat sedang berjalan. Antrean pelanggan baru tidak akan diarahkan ke kursi Anda.' : 'Status aktif. Anda dapat memanggil pelanggan dan melayani pangkas rambut.' }}
                </span>
            </div>
        </div>

        <!-- Tombol Aksi: Mulai Istirahat atau Kembali dari Istirahat -->
        <form method="POST" action="{{ route('barber.work-status') }}">
            @csrf
            <input type="hidden" name="work_status" value="{{ $isOnBreak ? 'active' : 'break' }}" />
            @if($isOnBreak)
                <button
                    type="submit"
                    class="w-full py-3.5 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white font-black text-xs flex items-center justify-center gap-2 shadow-md shadow-emerald-950/20 transition cursor-pointer"
                >
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-white" />
                    <span>Kembali dari Istirahat (Siap Bekerja)</span>
                </button>
            @else
                <button
                    type="submit"
                    class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 active:scale-[0.99] text-zinc-950 font-black text-xs flex items-center justify-center gap-2 shadow-md shadow-amber-500/20 transition cursor-pointer"
                >
                    <x-ui.icon name="coffee" class="w-4 h-4 text-zinc-950" />
                    <span>Mulai Jam Istirahat (Break)</span>
                </button>
            @endif
        </form>
    </div>
    @endif

    <!-- 3. JADWAL SHIFT MINGGUAN -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-black text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600" />
                <span>Jadwal Shift Mingguan Saya</span>
            </h2>
            <span class="text-[11px] font-bold text-zinc-400">Senin - Minggu</span>
        </div>

        <div class="space-y-1.5">
            @foreach($daysName as $dayNum => $dayLabel)
                @php
                    $isToday = $dayNum === $todayDayOfWeek;
                    $sched = $weeklySchedules->get($dayNum);
                    $isOff = $sched ? (bool) $sched->is_off : false;
                    $startTime = $sched?->start_time ? substr((string) $sched->start_time, 0, 5) : '09:00';
                    $endTime = $sched?->end_time ? substr((string) $sched->end_time, 0, 5) : '21:00';
                @endphp
                <div class="p-2.5 rounded-2xl border flex items-center justify-between text-xs transition {{ $isToday ? 'bg-amber-500/10 border-amber-300 font-bold shadow-2xs' : 'bg-zinc-50/60 border-zinc-200/70' }}">
                    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        <span class="font-black text-xs {{ $isToday ? 'text-amber-800' : 'text-zinc-700' }} w-14 sm:w-16">
                            {{ $dayLabel }}
                        </span>
                        @if($isToday)
                            <span class="px-1.5 py-0.5 rounded-md bg-amber-500 text-zinc-950 font-black text-[9px] uppercase tracking-wider whitespace-nowrap shrink-0">
                                HARI INI
                            </span>
                        @endif
                    </div>

                    <div class="shrink-0 text-right">
                        @if($isOff)
                            <span class="px-2 py-0.5 rounded-md bg-zinc-200/80 text-zinc-600 font-bold text-[10px] whitespace-nowrap">
                                Libur (OFF)
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 font-mono font-bold text-xs text-zinc-800 whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span>{{ $startTime }} - {{ $endTime }}</span>
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 3. RIWAYAT ABSENSI TERKINI -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <h2 class="font-black text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
            <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-500" />
            <span>Riwayat Absensi (14 Hari)</span>
        </h2>

        @if($recentAttendances->isEmpty())
            <p class="text-center py-4 text-xs text-zinc-400">Belum ada riwayat absensi tercatat.</p>
        @else
            <div class="divide-y divide-zinc-100 text-xs">
                @foreach($recentAttendances as $att)
                    @php
                        $isExcused = in_array($att->status, ['sick', 'leave', 'holiday', 'absent']);
                    @endphp
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-zinc-900">
                                {{ $att->date->locale('id')->isoFormat('dddd, D MMM') }}
                            </p>
                            <p class="text-[10px] text-zinc-500 font-mono mt-0.5">
                                @if($isExcused)
                                    <span class="text-zinc-400 font-medium">-</span>
                                @else
                                    {{ $att->check_in_at ? $att->check_in_at->format('H:i') : '--:--' }} - {{ $att->check_out_at ? $att->check_out_at->format('H:i') : '--:--' }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            @if($att->status === 'present')
                                <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 font-bold text-[10px]">
                                    Hadir
                                </span>
                            @elseif($att->status === 'late')
                                <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 font-bold text-[10px]">
                                    Telat {{ $att->late_minutes }}m
                                </span>
                            @elseif($att->status === 'sick')
                                <span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 font-bold text-[10px]">
                                    Sakit
                                </span>
                            @elseif($att->status === 'leave')
                                <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 font-bold text-[10px]">
                                    Cuti
                                </span>
                            @elseif($att->status === 'holiday')
                                <span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 font-bold text-[10px]">
                                    Libur
                                </span>
                            @elseif($att->status === 'absent')
                                <span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 font-bold text-[10px]">
                                    Alpa
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-600 font-bold text-[10px]">
                                    {{ ucfirst($att->status ?? 'hadir') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 4. RIWAYAT PERMOHONAN IZIN SAYA -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div>
            <h2 class="font-black text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-500" />
                <span>Riwayat Pengajuan Izin Saya</span>
            </h2>
        </div>

        @if($timeOffRequests->isEmpty())
            <p class="text-center py-4 text-xs text-zinc-400">Belum ada riwayat permohonan izin.</p>
        @else
            <div class="divide-y divide-zinc-100 text-xs">
                @foreach($timeOffRequests as $req)
                    <div class="py-2.5 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-zinc-900 num">
                                    {{ $req->date_from->format('d M Y') }} @if(!$req->date_from->isSameDay($req->date_to)) &ndash; {{ $req->date_to->format('d M Y') }} @endif
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $req->type === 'sick' ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($req->type === 'leave' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-purple-50 text-purple-700 border border-purple-200') }}">
                                    {{ $req->type_label }}
                                </span>
                            </div>

                            <div>
                                @if($req->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                        <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                        <span>Disetujui Owner</span>
                                    </span>
                                @elseif($req->status === 'rejected')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                        <x-ui.icon name="x" class="w-3 h-3 text-rose-600" />
                                        <span>Ditolak</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Menunggu Review</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p class="text-zinc-600 text-[11px]"><span class="font-semibold text-zinc-700">Alasan:</span> {{ $req->reason }}</p>

                        @if($req->status === 'rejected' && $req->notes)
                            <p class="text-rose-600 text-[10px] font-semibold bg-rose-50/60 p-2 rounded-lg border border-rose-200/50">
                                Catatan Penolakan: {{ $req->notes }} (Izin dibatalkan &amp; ketidakhadiran menjadi tanggung jawab staf).
                            </p>
                        @endif

                        @if($req->attachment_url)
                            <div class="pt-1">
                                <a href="{{ $req->attachment_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-[11px] font-bold shadow-2xs transition">
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-amber-400" />
                                    <span>Lihat Dokumen Bukti Surat</span>
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- MODAL PENGAJUAN IZIN (CUTI / SAKIT / LIBUR) -->
    <dialog id="modal-request-time-off" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/70 w-[calc(100%-2rem)] max-w-md max-h-[90vh] max-h-[90dvh] mx-auto my-auto overflow-hidden border border-zinc-200">
        <div class="flex flex-col max-h-[90vh] max-h-[90dvh]">
            <div class="p-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80 shrink-0">
                <div class="flex items-center gap-2">
                    <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                    <h3 class="font-extrabold text-xs text-zinc-900">Form Pengajuan Izin Staf</h3>
                </div>
                <button type="button" onclick="document.getElementById('modal-request-time-off').close()" class="text-zinc-400 hover:text-zinc-700 p-1 cursor-pointer">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('barber.time-off.store') }}" enctype="multipart/form-data" class="flex-1 overflow-y-auto overscroll-contain p-5 space-y-4 text-xs">
            @csrf

            <!-- Pilihan Jenis Izin: HANYA 3 SESUAI KETENTUAN -->
            <div class="space-y-1.5">
                <label class="block font-bold text-zinc-700">Jenis Izin <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-zinc-200 hover:border-sky-300 has-checked:border-sky-500 has-checked:bg-sky-50/50 cursor-pointer text-center transition">
                        <input type="radio" name="type" value="leave" checked class="sr-only" />
                        <span class="font-black text-xs text-zinc-800">Cuti</span>
                        <span class="text-[10px] text-zinc-400">Tahunan</span>
                    </label>

                    <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-zinc-200 hover:border-rose-300 has-checked:border-rose-500 has-checked:bg-rose-50/50 cursor-pointer text-center transition">
                        <input type="radio" name="type" value="sick" class="sr-only" />
                        <span class="font-black text-xs text-zinc-800">Sakit</span>
                        <span class="text-[10px] text-zinc-400">Surat Dokter</span>
                    </label>

                    <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-zinc-200 hover:border-purple-300 has-checked:border-purple-500 has-checked:bg-purple-50/50 cursor-pointer text-center transition">
                        <input type="radio" name="type" value="holiday" class="sr-only" />
                        <span class="font-black text-xs text-zinc-800">Izin Libur</span>
                        <span class="text-[10px] text-zinc-400">Off Terjadwal</span>
                    </label>
                </div>
            </div>

            <!-- Rentang Tanggal Izin -->
            <div class="grid grid-cols-2 gap-2.5">
                <div class="space-y-1">
                    <label class="block font-bold text-zinc-700">Tanggal Mulai <span class="text-rose-500">*</span></label>
                    <input type="date" name="date_from" required value="{{ now()->toDateString() }}" class="w-full font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/30" />
                </div>
                <div class="space-y-1">
                    <label class="block font-bold text-zinc-700">Tanggal Selesai <span class="text-rose-500">*</span></label>
                    <input type="date" name="date_to" required value="{{ now()->toDateString() }}" class="w-full font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/30" />
                </div>
            </div>

            <!-- Alasan Wajib -->
            <div class="space-y-1">
                <label class="block font-bold text-zinc-700">Alasan Izin <span class="text-rose-500">*</span></label>
                <textarea name="reason" rows="2.5" required placeholder="Tuliskan alasan izin secara jelas..." class="w-full font-medium p-3 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/30"></textarea>
            </div>

            <!-- Bukti Surat / Keterangan Wajib -->
            <div class="space-y-1.5">
                <label class="block font-bold text-zinc-700">Bukti Surat / Keterangan Dokter <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <input
                        type="file"
                        id="barber-time-off-file"
                        name="attachment"
                        required
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="sr-only"
                        onchange="updateFileNameDisplay(this, 'barber-file-name', 'barber-file-placeholder')"
                    />
                    <label
                        for="barber-time-off-file"
                        class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-dashed border-zinc-300 hover:border-amber-400 bg-zinc-50 hover:bg-amber-50/20 transition cursor-pointer group text-center"
                    >
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 group-hover:bg-amber-500 group-hover:text-zinc-950 flex items-center justify-center transition shadow-2xs">
                            <x-ui.icon name="upload" class="w-5 h-5" />
                        </div>
                        <div id="barber-file-placeholder" class="space-y-0.5">
                            <p class="font-extrabold text-xs text-zinc-800 group-hover:text-amber-800">
                                Klik untuk Pilih Foto / Dokumen
                            </p>
                            <p class="text-[10px] text-zinc-400">
                                Format JPG, PNG, atau PDF (Maksimal 5 MB)
                            </p>
                        </div>
                        <div id="barber-file-name" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600" />
                            <span class="file-text-target truncate max-w-[240px]"></span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Ketentuan Izin Singkat -->
            <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-300/80 text-[11px] space-y-1">
                <p class="font-black text-amber-950 flex items-center gap-1.5">
                    <x-ui.icon name="info" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                    <span>Ketentuan Persetujuan Izin:</span>
                </p>
                <div class="space-y-0.5 text-zinc-700 pl-5 text-[10px] sm:text-[11px]">
                    <p>&bull; <strong>Di-ACC:</strong> Bebas shift kerja &amp; jam kerja diakui penuh.</p>
                    <p>&bull; <strong>Ditolak:</strong> Izin batal &amp; tercatat Alpha.</p>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-request-time-off').close()"
                    class="px-4 py-2.5 rounded-xl font-bold text-zinc-700 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer text-xs"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black transition cursor-pointer shadow-xs text-xs"
                >
                    Kirim Pengajuan Izin
                </button>
            </div>
        </form>
    </div>
</dialog>

    <!-- Modal Tampilkan QR Absensi Staf -->
    <dialog id="modal-staff-qr-code" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/80 w-[calc(100%-2rem)] max-w-sm mx-auto my-auto overflow-hidden border border-zinc-200 bg-white">
        <div class="p-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80">
            <div class="flex items-center gap-2">
                <x-ui.icon name="ticket" class="w-4 h-4 text-amber-500" />
                <h3 class="font-extrabold text-xs text-zinc-900">QR Absensi Barber</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-staff-qr-code').close()" class="text-zinc-400 hover:text-zinc-700 p-1 cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <div class="p-6 text-center space-y-4">
            <!-- Profil Singkat Staf -->
            <div class="space-y-1">
                <h4 class="font-black text-base text-zinc-950">{{ $barberMembership?->display_name ?? 'Barber' }}</h4>
                <p class="text-xs text-zinc-500">{{ $barberMembership?->branch?->name ?? 'Cabang Outlet' }} &bull; Staff Barber</p>
            </div>

            <!-- Box QR Code Berkualitas Tinggi -->
            <div class="p-4 rounded-3xl bg-zinc-50 border border-zinc-200 inline-block shadow-inner mx-auto">
                <div id="barber-staff-qrcode" class="flex items-center justify-center min-w-[210px] min-h-[210px] bg-white p-2 rounded-2xl"></div>
            </div>

            <p class="text-[11px] text-zinc-400 leading-relaxed">
                Tunjukkan QR ke scanner kasir untuk absen, atau unduh gambar QR untuk disimpan di galeri ponsel Anda.
            </p>
        </div>

        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center gap-2">
            <button
                type="button"
                onclick="downloadStaffQr('{{ addslashes($barberMembership?->display_name ?? 'Barber') }}', '{{ addslashes($barberMembership?->branch?->name ?? 'Cabang Outlet') }}', 'barber-staff-qrcode')"
                class="flex-1 py-2.5 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs transition cursor-pointer flex items-center justify-center gap-1.5 shadow-xs"
            >
                <x-ui.icon name="download" class="w-4 h-4 text-zinc-950" />
                <span>Unduh QR Absen</span>
            </button>
            <button
                type="button"
                onclick="document.getElementById('modal-staff-qr-code').close()"
                class="py-2.5 px-4 rounded-xl bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs transition cursor-pointer"
            >
                Tutup
            </button>
        </div>
    </dialog>

    <!-- Script Vendor QRCode Generator -->
    <script src="{{ asset('vendor/qrcode/qrcode.min.js') }}"></script>
    <script
        id="barber-staff-qr-script"
        data-payload="{{ $barberMembership?->getAttendanceQrPayload() }}"
    >
        let qrCodeInstance = null;

        function renderStaffQr(boxId, scriptId) {
            const qrBox = document.getElementById(boxId);
            const scriptEl = document.getElementById(scriptId);
            if (!qrBox || !scriptEl) return;

            if (!qrBox.querySelector('canvas') && typeof QRCode !== 'undefined') {
                const payload = scriptEl.dataset.payload || '';
                if (payload) {
                    qrBox.innerHTML = '';
                    qrCodeInstance = new QRCode(qrBox, {
                        text: payload,
                        width: 210,
                        height: 210,
                        colorDark: '#18181b',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H,
                    });
                }
            }
        }

        function openStaffQrModal() {
            const modal = document.getElementById('modal-staff-qr-code');
            if (!modal) return;
            modal.showModal();
            renderStaffQr('barber-staff-qrcode', 'barber-staff-qr-script');
        }

        function downloadStaffQr(staffName = 'Staff', branchName = '', boxId = 'barber-staff-qrcode') {
            const qrBox = document.getElementById(boxId);
            if (!qrBox) return;

            const canvas = qrBox.querySelector('canvas');
            const img = qrBox.querySelector('img');

            if (!canvas && !img) {
                renderStaffQr('barber-staff-qrcode', 'barber-staff-qr-script');
                setTimeout(() => downloadStaffQr(staffName, branchName, boxId), 150);
                return;
            }

            const exportCanvas = document.createElement('canvas');
            const ctx = exportCanvas.getContext('2d');
            const width = 360;
            const height = 480;
            exportCanvas.width = width;
            exportCanvas.height = height;

            // Background putih bersih
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);

            // Border kartu tipis elegan
            ctx.strokeStyle = '#e4e4e7';
            ctx.lineWidth = 2;
            ctx.strokeRect(1, 1, width - 2, height - 2);

            // Header - Nama Barbershop / Cabang
            ctx.fillStyle = '#f59e0b';
            ctx.font = 'bold 13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText((branchName || 'BARBERSHOP').toUpperCase(), width / 2, 38);

            // Nama Staf
            ctx.fillStyle = '#09090b';
            ctx.font = 'bold 18px sans-serif';
            ctx.fillText(staffName, width / 2, 65);

            ctx.fillStyle = '#71717a';
            ctx.font = '12px sans-serif';
            ctx.fillText('QR KARTU ABSENSI STAF', width / 2, 86);

            // Gambar QR Code di tengah
            const qrSize = 250;
            const qrX = (width - qrSize) / 2;
            const qrY = 110;

            const drawAndDownload = (sourceElement) => {
                ctx.drawImage(sourceElement, qrX, qrY, qrSize, qrSize);

                // Footer petunjuk
                ctx.fillStyle = '#18181b';
                ctx.font = 'bold 13px sans-serif';
                ctx.fillText('Arahkan ke Scanner Absensi', width / 2, 395);

                ctx.fillStyle = '#71717a';
                ctx.font = '11px sans-serif';
                ctx.fillText('Untuk Check-in Masuk & Check-out Pulang', width / 2, 418);

                ctx.fillStyle = '#a1a1aa';
                ctx.font = '10px sans-serif';
                ctx.fillText('Simpan di Galeri HP untuk absen cepat', width / 2, 445);

                const dataUrl = exportCanvas.toDataURL('image/png');
                const link = document.createElement('a');
                const safeName = staffName.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
                link.download = `qr-absen-${safeName}.png`;
                link.href = dataUrl;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            };

            if (canvas) {
                drawAndDownload(canvas);
            } else if (img && img.complete) {
                drawAndDownload(img);
            } else if (img) {
                img.onload = () => drawAndDownload(img);
            }
        }

        function triggerQuickDownloadStaffQr(staffName, branchName, boxId, scriptId) {
            renderStaffQr(boxId, scriptId);
            setTimeout(() => {
                downloadStaffQr(staffName, branchName, boxId);
            }, 100);
        }

        function updateFileNameDisplay(input, nameContainerId, placeholderId) {
            const nameEl = document.getElementById(nameContainerId);
            const placeEl = document.getElementById(placeholderId);
            if (!nameEl) return;
            if (input.files && input.files[0]) {
                const textTarget = nameEl.querySelector('.file-text-target');
                if (textTarget) textTarget.textContent = input.files[0].name;
                nameEl.classList.remove('hidden');
                nameEl.classList.add('inline-flex');
                if (placeEl) placeEl.classList.add('hidden');
            } else {
                nameEl.classList.add('hidden');
                nameEl.classList.remove('inline-flex');
                if (placeEl) placeEl.classList.remove('hidden');
            }
        }
    </script>
</x-layouts.barber>
