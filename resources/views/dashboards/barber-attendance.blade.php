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
            <span class="font-mono text-xs font-black text-amber-600 bg-amber-500/10 px-2 py-0.5 rounded-lg">
                {{ now()->format('H:i') }} WIB
            </span>
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
                <button
                    type="button"
                    onclick="startAttendanceScanner()"
                    class="w-full py-3.5 px-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-extrabold text-xs flex items-center justify-center gap-2.5 shadow-md shadow-zinc-950/20 transition cursor-pointer"
                >
                    <x-ui.icon name="camera" class="w-4 h-4 text-amber-400 stroke-[2.5]" />
                    <span>Pindai QR Kamera di Meja Kasir</span>
                </button>
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

    <!-- 2. KARTU STATUS KERJA & JAM ISTIRAHAT -->
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
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-zinc-900">
                                {{ $att->date->locale('id')->isoFormat('dddd, D MMM') }}
                            </p>
                            <p class="text-[10px] text-zinc-500 font-mono mt-0.5">
                                {{ $att->check_in_at ? $att->check_in_at->format('H:i') : '--:--' }} - {{ $att->check_out_at ? $att->check_out_at->format('H:i') : '--:--' }}
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
        <div class="flex items-center justify-between">
            <h2 class="font-black text-xs uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-500" />
                <span>Riwayat Pengajuan Izin Saya</span>
            </h2>
            <button
                type="button"
                onclick="document.getElementById('modal-request-time-off').showModal()"
                class="text-xs font-black text-zinc-950 bg-amber-500 hover:bg-amber-400 px-3 py-1.5 rounded-xl transition cursor-pointer shadow-2xs"
            >
                + Ajukan Izin
            </button>
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
    <dialog id="modal-request-time-off" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/70 w-[calc(100%-2rem)] max-w-md mx-auto my-auto overflow-hidden border border-zinc-200">
        <div class="p-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80">
            <div class="flex items-center gap-2">
                <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                <h3 class="font-extrabold text-xs text-zinc-900">Form Pengajuan Izin Staf</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-request-time-off').close()" class="text-zinc-400 hover:text-zinc-700 p-1 cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('barber.time-off.store') }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
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
                    <p>&bull; <strong>Ditolak:</strong> Izin batal &amp; tercatat Alpha (kena potongan gaji).</p>
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
    </dialog>

    <!-- Modal Pemindai QR Kamera -->
    <dialog id="modal-attendance-scanner" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/80 w-[calc(100%-2rem)] max-w-sm mx-auto my-auto overflow-hidden border border-zinc-200">
        <div class="p-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/70">
            <div class="flex items-center gap-2">
                <x-ui.icon name="camera" class="w-4 h-4 text-amber-500" />
                <h3 class="font-bold text-xs text-zinc-800">Pindai QR Absensi Outlet</h3>
            </div>
            <button type="button" onclick="stopAttendanceScanner()" class="text-zinc-400 hover:text-zinc-700 p-1">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <div class="p-5 space-y-3">
            <!-- Box Kotak Viewport Scanner Rapi -->
            <div class="relative w-full aspect-square max-w-[270px] mx-auto rounded-2xl overflow-hidden bg-black border-2 border-amber-500 shadow-lg flex items-center justify-center">
                <!-- 1. Dedicated Html5Qrcode Reader Container (Tidak boleh ditimpa innerHTML saat scanner aktif) -->
                <div id="attendance-qr-reader" class="w-full h-full"></div>

                <!-- 2. Overlay Status Hasil Absensi / Loading Pemrosesan -->
                <div id="attendance-scanner-overlay" class="absolute inset-0 z-20 hidden flex-col items-center justify-center p-4 text-center bg-zinc-950/95 text-white backdrop-blur-sm transition-all duration-200">
                    <div id="scanner-overlay-spinner" class="hidden flex-col items-center space-y-3">
                        <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                        <div class="space-y-1">
                            <p class="font-extrabold text-xs text-white">Memproses Absensi...</p>
                            <p class="text-[11px] text-zinc-400">Mendeteksi lokasi GPS & memverifikasi radius</p>
                        </div>
                    </div>

                    <div id="scanner-overlay-result" class="hidden flex-col items-center justify-center space-y-3">
                        <div id="scanner-result-icon" class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-xl shadow-inner"></div>
                        <p id="scanner-result-title" class="font-black text-sm tracking-wide"></p>
                        <div class="pt-1 flex items-center justify-center">
                            <button type="button" id="btn-scanner-retry" onclick="restartAttendanceScanner()" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs cursor-pointer transition shadow">
                                Pindai Ulang
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Bingkai Kotak Sudut (Target Brackets) -->
                <div id="scanner-brackets" class="absolute inset-4 pointer-events-none z-10">
                    <span class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-amber-400 rounded-tl-lg"></span>
                    <span class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-amber-400 rounded-tr-lg"></span>
                    <span class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-amber-400 rounded-bl-lg"></span>
                    <span class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-amber-400 rounded-br-lg"></span>
                    <!-- Garis Laser Horizontal -->
                    <span class="absolute inset-x-2 top-1/2 -translate-y-1/2 h-0.5 bg-amber-400 shadow-[0_0_8px_#f59e0b] animate-pulse"></span>
                </div>
            </div>

            <div id="scanner-feedback" class="text-center text-xs text-zinc-500 py-1">
                Arahkan kotak pemindai ke QR Code absensi di meja kasir
            </div>
        </div>

        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end">
            <button
                type="button"
                onclick="stopAttendanceScanner()"
                class="w-full py-2.5 rounded-xl border border-zinc-200 bg-white text-zinc-700 font-bold text-xs hover:bg-zinc-100 transition cursor-pointer"
            >
                Tutup Kamera
            </button>
        </div>
    </dialog>

    <style>
        #attendance-qr-reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            position: relative;
        }
        #attendance-qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 14px !important;
        }
        #attendance-qr-reader img,
        #attendance-qr-reader select,
        #attendance-qr-reader button,
        #attendance-qr-reader #attendance-qr-reader__header_message {
            display: none !important;
        }
        #attendance-qr-reader__scan_region {
            min-height: 100% !important;
        }
    </style>

    <!-- Script Vendor HTML5-QRCode Scanner -->
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <script>
        let html5Scanner = null;
        let isScannerProcessing = false;

        function extractToken(decodedText) {
            let text = (decodedText || '').trim();
            if (text.includes('/attend/')) {
                const parts = text.split('/attend/');
                text = parts[1].split('?')[0].split('#')[0];
            }
            return text.replace(/^\/+|\/+$/g, '');
        }

        function showScannerProcessing() {
            const overlay = document.getElementById('attendance-scanner-overlay');
            const spinner = document.getElementById('scanner-overlay-spinner');
            const result = document.getElementById('scanner-overlay-result');
            const brackets = document.getElementById('scanner-brackets');

            if (brackets) brackets.classList.add('hidden');
            if (result) {
                result.classList.add('hidden');
                result.classList.remove('flex');
            }
            if (spinner) {
                spinner.classList.remove('hidden');
                spinner.classList.add('flex');
            }
            if (overlay) {
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
            }
        }

        function showScannerResult(isSuccess, title) {
            const overlay = document.getElementById('attendance-scanner-overlay');
            const spinner = document.getElementById('scanner-overlay-spinner');
            const result = document.getElementById('scanner-overlay-result');
            const icon = document.getElementById('scanner-result-icon');
            const titleEl = document.getElementById('scanner-result-title');
            const brackets = document.getElementById('scanner-brackets');
            const btnRetry = document.getElementById('btn-scanner-retry');

            if (brackets) brackets.classList.add('hidden');
            if (spinner) {
                spinner.classList.add('hidden');
                spinner.classList.remove('flex');
            }

            if (icon) {
                if (isSuccess) {
                    icon.className = 'w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xl shadow-inner';
                    icon.textContent = '✓';
                } else {
                    icon.className = 'w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xl shadow-inner';
                    icon.textContent = '✕';
                }
            }

            if (titleEl) {
                titleEl.textContent = title;
                titleEl.className = isSuccess ? 'font-black text-sm text-emerald-400 tracking-wide' : 'font-black text-sm text-rose-400 tracking-wide';
            }

            if (btnRetry) {
                btnRetry.style.display = isSuccess ? 'none' : 'inline-block';
            }

            if (result) {
                result.classList.remove('hidden');
                result.classList.add('flex');
            }
            if (overlay) {
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
            }
        }

        function hideScannerOverlay() {
            const overlay = document.getElementById('attendance-scanner-overlay');
            const spinner = document.getElementById('scanner-overlay-spinner');
            const result = document.getElementById('scanner-overlay-result');
            const brackets = document.getElementById('scanner-brackets');

            if (brackets) brackets.classList.remove('hidden');
            if (spinner) {
                spinner.classList.add('hidden');
                spinner.classList.remove('flex');
            }
            if (result) {
                result.classList.add('hidden');
                result.classList.remove('flex');
            }
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
            }
        }

        async function safeStopScanner() {
            if (html5Scanner) {
                try {
                    if (html5Scanner.isScanning) {
                        await html5Scanner.stop();
                    }
                } catch (e) {
                    // Ignore transition error
                }
            }
        }

        async function startAttendanceScanner() {
            const modal = document.getElementById('modal-attendance-scanner');
            if (modal) modal.showModal();

            const feedback = document.getElementById('scanner-feedback');
            if (feedback) {
                feedback.className = 'text-center text-xs text-zinc-500 py-1';
                feedback.textContent = 'Arahkan kotak pemindai ke QR Code absensi di meja kasir';
            }

            hideScannerOverlay();
            isScannerProcessing = false;

            if (!window.Html5Qrcode) {
                alert('Pemindai kamera sedang dimuat, harap coba sesaat lagi.');
                return;
            }

            // Hentikan scanner sebelumnya jika ada yang menggantung
            await safeStopScanner();

            try {
                if (!html5Scanner) {
                    html5Scanner = new Html5Qrcode('attendance-qr-reader');
                }

                await html5Scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    onScanSuccess,
                    () => {}
                );
            } catch (err) {
                console.warn('Gagal mengakses kamera:', err);
                if (feedback) {
                    feedback.textContent = 'Gagal mengakses kamera. Pastikan izin kamera aktif: ' + (err.message || err);
                    feedback.className = 'text-center text-xs text-rose-500 py-1 font-semibold';
                }
            }
        }

        async function stopAttendanceScanner() {
            isScannerProcessing = false;
            await safeStopScanner();
            hideScannerOverlay();
            const modal = document.getElementById('modal-attendance-scanner');
            if (modal) modal.close();
        }

        async function restartAttendanceScanner() {
            hideScannerOverlay();
            await startAttendanceScanner();
        }

        async function onScanSuccess(decodedText) {
            // Guard: Cegah pemrosesan berulang dari frame kamera berikutnya
            if (isScannerProcessing) return;
            isScannerProcessing = true;

            // Hentikan pemindaian kamera secara aman tanpa merusak struktur DOM
            await safeStopScanner();

            // Tampilkan status pemrosesan pada overlay
            showScannerProcessing();

            const token = extractToken(decodedText);
            const feedback = document.getElementById('scanner-feedback');

            if (feedback) {
                feedback.className = 'text-center text-xs text-amber-500 py-1 font-semibold';
                feedback.innerHTML = '<span class="animate-pulse">Sedang memverifikasi lokasi & mencatat waktu shift...</span>';
            }

            const sendAttendance = (lat, lng) => {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                if (lat !== null) formData.append('lat', lat);
                if (lng !== null) formData.append('lng', lng);
                @if($todayAttendance && $todayAttendance->check_in_at && !$todayAttendance->check_out_at)
                formData.append('action', 'check_out');
                @else
                formData.append('action', 'check_in');
                @endif

                fetch('/attend/' + encodeURIComponent(token), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(async (res) => {
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = { success: false, message: 'Respons server tidak valid.' };
                    }
                    return { ok: res.ok, status: res.status, body: data };
                })
                .then(({ ok, body }) => {
                    if (ok && body.success) {
                        showScannerResult(true, 'Absensi Berhasil');
                        if (feedback) {
                            feedback.className = 'text-center text-xs text-emerald-600 bg-emerald-50 border border-emerald-200/80 rounded-xl p-2.5 font-bold';
                            feedback.textContent = body.message || 'Absensi berhasil! Memperbarui halaman...';
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);
                    } else {
                        const errMsg = body.message || 'Gagal mencatat absensi. Pastikan Anda berada di area outlet.';
                        const errorTitle = errMsg === 'Belum saatnya absen' ? 'Belum Saatnya Absen' : 'Absensi Ditolak';
                        showScannerResult(false, errorTitle);
                        if (feedback) {
                            feedback.className = 'text-center text-xs text-rose-600 bg-rose-50 border border-rose-200/80 rounded-xl p-2.5 font-medium leading-relaxed';
                            feedback.textContent = errMsg;
                        }
                    }
                })
                .catch((err) => {
                    console.error('Attendance scan fetch error:', err);
                    showScannerResult(false, 'Gangguan Jaringan');
                    if (feedback) {
                        feedback.className = 'text-center text-xs text-rose-600 bg-rose-50 border border-rose-200/80 rounded-xl p-2.5 font-semibold';
                        feedback.textContent = 'Terjadi gangguan jaringan saat menghubungi server. Silakan coba lagi.';
                    }
                });
            };

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => sendAttendance(pos.coords.latitude, pos.coords.longitude),
                    (err) => {
                        console.warn('Geolocation warning:', err);
                        sendAttendance(null, null);
                    },
                    { enableHighAccuracy: true, timeout: 6000 }
                );
            } else {
                sendAttendance(null, null);
            }
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
