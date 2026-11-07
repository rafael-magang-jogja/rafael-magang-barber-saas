@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
    $effectiveRole = $currentTenantCtx->role() ?? request()->attributes->get('current_role') ?? 'cashier';
    $isCashierOrAdmin = in_array($effectiveRole, ['admin', 'cashier'], true) && $effectiveRole !== 'owner';

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

    $statusFilterOptions = [
        ['value' => 'all', 'label' => 'Semua Status'],
        ['value' => 'present', 'label' => 'Hadir Tepat Waktu'],
        ['value' => 'late', 'label' => 'Terlambat'],
        ['value' => 'sick', 'label' => 'Sakit'],
        ['value' => 'leave', 'label' => 'Izin / Cuti'],
        ['value' => 'holiday', 'label' => 'Libur'],
        ['value' => 'absent', 'label' => 'Alpa / Tidak Hadir'],
    ];

    $staffSelectOptions = ($branchStaff ?? collect())->map(fn ($b) => [
        'value' => (string) $b->id,
        'label' => $b->display_name ?? $b->user?->name,
        'sublabel' => $b->role?->value === 'admin' ? 'Kasir / Admin' : ($b->role?->value === 'barber' ? 'Barber' : 'Staf'),
    ])->all();
@endphp

<x-layouts.app title="Absensi & Jadwal Kerja" subtitle="Status absensi harian, jadwal operasional Anda, dan kehadiran staf di cabang" :role="$effectiveRole">
    <div class="space-y-6">
        <!-- BANNER IZIN HARI INI JIKA DI-ACC OWNER -->
        @if($todayTimeOff)
            <div class="p-4 sm:p-5 rounded-3xl bg-emerald-50 border border-emerald-200/90 shadow-2xs space-y-2">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-700 flex items-center justify-center font-black shrink-0">
                        <x-ui.icon name="check-circle" class="w-5 h-5 text-emerald-600" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-sm sm:text-base text-emerald-950">Izin Hari Ini Disetujui ({{ $todayTimeOff->type_label }})</h3>
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

        <!-- ============================================================ -->
        <!-- 1. KARTU UTAMA: STATUS ABSENSI KASIR HARI INI               -->
        <!-- ============================================================ -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4 sm:space-y-5">
            <!-- Header Profil & Jam Realtime -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center justify-center shrink-0">
                        <x-ui.icon name="clock" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-black text-lg text-zinc-900 tracking-tight truncate">
                                {{ $membership?->display_name ?? auth()->user()->name }}
                            </h2>
                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 border border-zinc-200 font-bold text-[10px] uppercase shrink-0">
                                {{ $membership?->role?->value ?? 'Kasir / Admin' }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5 truncate">
                            {{ $branch?->name ?? 'Cabang Utama' }} · {{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
                    <livewire:attendance-clock :show-pulse="true" />
                </div>
            </div>

            <!-- Detail Jam Masuk & Jam Pulang (Grid Responsif) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <!-- Kartu Jam Masuk -->
                <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Jam Masuk</span>
                        @if($todayAttendance && $todayAttendance->check_in_at)
                            @if($todayAttendance->late_minutes > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-rose-500/10 text-rose-700 font-bold text-[10px] whitespace-nowrap">
                                    Telat {{ $todayAttendance->late_minutes }}m
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 font-bold text-[10px] whitespace-nowrap">
                                    Tepat Waktu
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-200/70 text-zinc-500 font-medium text-[10px] whitespace-nowrap">
                                Belum Tercatat
                            </span>
                        @endif
                    </div>

                    @if($todayAttendance && $todayAttendance->check_in_at)
                        <p class="font-black font-mono text-emerald-700 text-lg sm:text-xl">
                            {{ $todayAttendance->check_in_at->format('H:i') }} <span class="text-xs font-semibold text-emerald-600">WIB</span>
                        </p>
                        <p class="text-[11px] text-zinc-500 flex items-center gap-1">
                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Absen masuk berhasil terverifikasi</span>
                        </p>
                    @else
                        <p class="font-mono text-zinc-400 text-lg sm:text-xl font-bold">--:-- <span class="text-xs font-normal">WIB</span></p>
                        <p class="text-[11px] text-zinc-400">Pindai QR meja kasir saat mulai bertugas</p>
                    @endif
                </div>

                <!-- Kartu Jam Pulang -->
                <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Jam Pulang</span>
                        @if($todayAttendance && $todayAttendance->check_out_at)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 font-bold text-[10px] whitespace-nowrap">
                                Selesai Shift
                            </span>
                        @elseif($todayAttendance && $todayAttendance->check_in_at)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 font-bold text-[10px] whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                Sedang Bertugas
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-200/70 text-zinc-500 font-medium text-[10px] whitespace-nowrap">
                                Belum Aktif
                            </span>
                        @endif
                    </div>

                    @if($todayAttendance && $todayAttendance->check_out_at)
                        <p class="font-black font-mono text-amber-700 text-lg sm:text-xl">
                            {{ $todayAttendance->check_out_at->format('H:i') }} <span class="text-xs font-semibold text-amber-600">WIB</span>
                        </p>
                        <p class="text-[11px] text-zinc-500 flex items-center gap-1">
                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Shift selesai &amp; absensi lengkap</span>
                        </p>
                    @else
                        <p class="font-mono text-zinc-400 text-lg sm:text-xl font-bold">--:-- <span class="text-xs font-normal">WIB</span></p>
                        <p class="text-[11px] text-zinc-400">Pindai QR meja kasir saat jam pulang</p>
                    @endif
                </div>
            </div>

            <!-- Tombol Aksi Absensi & Pengajuan Izin -->
            <div class="space-y-2">
                @if($todayAttendance && $todayAttendance->check_in_at && $todayAttendance->check_out_at)
                    <div class="w-full py-3 px-4 rounded-2xl bg-zinc-100 border border-zinc-200/80 text-zinc-700 font-bold text-xs sm:text-sm flex items-center justify-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-700 flex items-center justify-center">
                            <x-ui.icon name="check" class="w-3.5 h-3.5 stroke-[2.5]" />
                        </div>
                        <span>Absensi Hari Ini Lengkap</span>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="openStaffQrModal()"
                            class="flex-1 py-3.5 px-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-extrabold text-xs sm:text-sm flex items-center justify-center gap-2.5 shadow-md shadow-zinc-950/20 transition cursor-pointer"
                        >
                            <x-ui.icon name="ticket" class="w-4 h-4 text-amber-400 stroke-[2.5]" />
                            <span>Tampilkan QR Absen Saya</span>
                        </button>
                        <button
                            type="button"
                            onclick="triggerQuickDownloadStaffQr('{{ addslashes($membership?->display_name ?? auth()->user()->name) }}', '{{ addslashes($branch?->name ?? 'Cabang Outlet') }}', 'cashier-staff-qrcode', 'cashier-staff-qr-script')"
                            class="p-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs sm:text-sm flex items-center justify-center shadow-md shadow-amber-500/20 transition cursor-pointer shrink-0"
                            title="Unduh QR Code Absen ke Galeri HP"
                        >
                            <x-ui.icon name="download" class="w-4 h-4 text-zinc-950" />
                        </button>
                    </div>
                @endif

                <button
                    type="button"
                    onclick="document.getElementById('modal-request-time-off').showModal()"
                    class="w-full py-3 px-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 transition cursor-pointer shadow-xs"
                >
                    <x-ui.icon name="file-text" class="w-4 h-4 text-zinc-950" />
                    <span>Ajukan Permohonan Izin (Cuti / Sakit / Libur)</span>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 2. JADWAL KERJA MINGGUAN SAYA                               -->
        <!-- ============================================================ -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm">
                            <x-ui.icon name="calendar" class="w-4 h-4 text-amber-500" />
                            <span>Jadwal Shift Kerja Saya</span>
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">Jam operasional dan hari kerja mingguan yang dijadwalkan</p>
                    </div>
                    <span class="text-xs font-bold text-zinc-400">7 Hari</span>
                </div>
            </x-slot:header>

            <!-- Mode Mobile & Tablet: Tampilan List Baris Rapi (Sama seperti di Barber) -->
            <div class="space-y-1.5 lg:hidden">
                @foreach($daysName as $dayNum => $dayLabel)
                    @php
                        $isToday = $dayNum === $todayDayOfWeek;
                        $sched = $schedules->get($dayNum);
                        $branchHours = $branch?->opening_hours[$dayNum] ?? null;

                        $isOff = false;
                        $startTime = '09:00';
                        $endTime = '21:00';

                        if ($sched) {
                            $isOff = (bool) $sched->is_off;
                            $startTime = $sched->start_time ? substr((string) $sched->start_time, 0, 5) : '09:00';
                            $endTime = $sched->end_time ? substr((string) $sched->end_time, 0, 5) : '21:00';
                        } elseif ($branchHours) {
                            $isOff = empty($branchHours['is_open']);
                            $startTime = $branchHours['open'] ?? '09:00';
                            $endTime = $branchHours['close'] ?? '21:00';
                        }
                    @endphp
                    <div class="p-2.5 sm:p-3 rounded-2xl border flex items-center justify-between text-xs transition {{ $isToday ? 'bg-amber-500/10 border-amber-300 font-bold shadow-2xs' : 'bg-zinc-50/60 border-zinc-200/70 hover:bg-zinc-50' }}">
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
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-bold text-xs text-zinc-800 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                        <span>{{ $startTime }} - {{ $endTime }}</span>
                                    </span>
                                    <span class="hidden sm:inline-flex px-1.5 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 font-bold text-[10px] whitespace-nowrap">
                                        Shift Masuk
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Mode Desktop (Layar Lebar >= 1024px): Grid 7 Kolom Sejajar -->
            <div class="hidden lg:grid lg:grid-cols-7 lg:gap-2.5">
                @foreach($daysName as $dayNum => $dayLabel)
                    @php
                        $isToday = $dayNum === $todayDayOfWeek;
                        $sched = $schedules->get($dayNum);
                        $branchHours = $branch?->opening_hours[$dayNum] ?? null;

                        $isOff = false;
                        $startTime = '09:00';
                        $endTime = '21:00';

                        if ($sched) {
                            $isOff = (bool) $sched->is_off;
                            $startTime = $sched->start_time ? substr((string) $sched->start_time, 0, 5) : '09:00';
                            $endTime = $sched->end_time ? substr((string) $sched->end_time, 0, 5) : '21:00';
                        } elseif ($branchHours) {
                            $isOff = empty($branchHours['is_open']);
                            $startTime = $branchHours['open'] ?? '09:00';
                            $endTime = $branchHours['close'] ?? '21:00';
                        }
                    @endphp
                    <div class="p-3 rounded-2xl border text-center transition {{ $isToday ? 'bg-amber-50/70 border-amber-300 ring-2 ring-amber-400/20' : 'bg-white border-zinc-200/80' }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-black {{ $isToday ? 'text-amber-800' : 'text-zinc-700' }}">
                                {{ $dayLabel }}
                            </span>
                            @if($isToday)
                                <span class="px-1.5 py-0.2 rounded bg-amber-500 text-zinc-950 font-black text-[9px]">
                                    HARI INI
                                </span>
                            @endif
                        </div>

                        @if($isOff)
                            <div class="py-2">
                                <span class="px-2 py-1 rounded-lg bg-zinc-100 text-zinc-500 font-bold text-[10px]">
                                    LIBUR (OFF)
                                </span>
                            </div>
                        @else
                            <div class="py-1 space-y-0.5">
                                <p class="text-xs font-black text-zinc-900 font-mono">
                                    {{ $startTime }} - {{ $endTime }}
                                </p>
                                <span class="text-[10px] text-emerald-600 font-bold block">
                                    Shift Masuk
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <!-- ============================================================ -->
        <!-- 3. RIWAYAT & KEHADIRAN STAF CABANG (TERPADU 1 BAGIAN)        -->
        <!-- ============================================================ -->
        @php
            $branchDisplayName = $branch?->name ? (str_starts_with($branch->name, 'Cabang') ? $branch->name : 'Cabang ' . $branch->name) : 'Cabang';
            $isTodayPeriod = ($period ?? 'today') === 'today';
            $totalTableRows = $recentAttendances->count() + ($isTodayPeriod ? ($todayUnattendedStaff ?? collect())->count() : 0);
        @endphp

        <x-ui.card data-pagination-container="cashier-attendance">
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                    <div>
                        <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm">
                            <x-ui.icon name="users" class="w-4 h-4 text-emerald-600" />
                            <span>Riwayat &amp; Kehadiran {{ $branchDisplayName }}</span>
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Rentang: {{ \Carbon\Carbon::parse($dateFrom)->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->translatedFormat('d M Y') }}
                        </p>
                    </div>

                    <!-- Mini KPI Badges di Header -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="px-2.5 py-1 rounded-xl bg-zinc-100 text-zinc-700 text-xs font-bold">
                            Total: <strong class="text-zinc-950">{{ $totalRecords ?? 0 }}</strong>
                        </span>
                        <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200/60">
                            Tepat Waktu: <strong class="text-emerald-800">{{ $presentCount ?? 0 }}</strong>
                        </span>
                        @if(($lateCount ?? 0) > 0)
                            <span class="px-2.5 py-1 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200/60">
                                Telat: <strong class="text-amber-800">{{ $lateCount }} ({{ $totalLateMinutes }}m)</strong>
                            </span>
                        @endif
                        @if(($leaveCount ?? 0) > 0)
                            <span class="px-2.5 py-1 rounded-xl bg-sky-50 text-sky-700 text-xs font-bold border border-sky-200/60">
                                Izin/Sakit: <strong class="text-sky-800">{{ $leaveCount }}</strong>
                            </span>
                        @endif
                    </div>
                </div>
            </x-slot:header>

            <!-- Filter Toolbar Terpadu (Menyatu di dalam Card) -->
            <div class="p-3.5 sm:p-4 border-b border-zinc-100 bg-zinc-50/50">
                <form method="GET" action="{{ route('cashier.attendance') }}" class="space-y-3">
                    <!-- Baris 1: Preset Periode (Hari Ini, 7 Hari, Bulan Ini) -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                        <div class="w-full sm:w-auto grid grid-cols-3 sm:inline-flex items-center gap-1 p-1 rounded-xl bg-zinc-200/70 text-xs font-bold text-zinc-600">
                            <a
                                href="{{ route('cashier.attendance', array_merge(request()->except('page'), ['period' => 'today'])) }}"
                                class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ ($period ?? 'today') === 'today' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                            >
                                Hari Ini
                            </a>
                            <a
                                href="{{ route('cashier.attendance', array_merge(request()->except('page'), ['period' => '7days'])) }}"
                                class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ ($period ?? '') === '7days' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                            >
                                7 Hari
                            </a>
                            <a
                                href="{{ route('cashier.attendance', array_merge(request()->except('page'), ['period' => 'this_month'])) }}"
                                class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ ($period ?? '') === 'this_month' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                            >
                                Bulan Ini
                            </a>
                        </div>

                        <span class="text-xs text-zinc-500 font-medium hidden sm:inline">
                            Periode aktif: <strong class="text-zinc-800">{{ ($period ?? 'today') === 'today' ? 'Hari Ini' : (($period ?? '') === '7days' ? '7 Hari Terakhir' : (($period ?? '') === 'this_month' ? 'Bulan Ini' : 'Kustom')) }}</strong>
                        </span>
                    </div>

                    <!-- Baris 2: Dropdown Filter Status & Staf -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-2.5">
                        <!-- Filter Status -->
                        <x-ui.select
                            name="status"
                            size="sm"
                            icon="filter"
                            :options="$statusFilterOptions"
                            :value="$statusFilter ?? 'all'"
                            auto-submit
                        />

                        <!-- Filter Staf / Barber -->
                        <x-ui.select
                            name="barber_id"
                            size="sm"
                            icon="scissors"
                            placeholder="Semua Staf & Barber"
                            :options="$staffSelectOptions"
                            :value="request('barber_id')"
                            search-placeholder="Cari nama staf..."
                            auto-submit
                        />
                    </div>

                    <!-- Rentang Kustom (Jika dipilih) -->
                    @if(($period ?? '') === 'custom')
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-zinc-200/60 text-xs font-semibold">
                            <span class="text-zinc-500 text-xs font-bold">Periode:</span>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-2.5 py-1.5 rounded-xl border border-zinc-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 flex-1 sm:flex-initial" />
                            <span class="text-zinc-400">s/d</span>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="px-2.5 py-1.5 rounded-xl border border-zinc-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 flex-1 sm:flex-initial" />
                            <button type="submit" class="px-4 py-1.5 rounded-xl bg-zinc-900 text-white font-bold text-xs">Cari</button>
                        </div>
                    @endif
                </form>
            </div>

            <!-- Tabel Data Kehadiran -->
            @if($totalTableRows === 0)
                <div class="text-center py-10 px-4 text-zinc-400 italic text-xs">
                    Belum ada data absensi untuk filter dan periode yang dipilih.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[760px]">
                        <thead>
                            <tr class="border-b border-zinc-200/80 text-[11px] font-bold text-zinc-500 bg-zinc-50/60">
                                <th class="py-3 px-3.5 whitespace-nowrap">Tanggal</th>
                                <th class="py-3 px-3.5 whitespace-nowrap">Nama Staf</th>
                                <th class="py-3 px-3.5 whitespace-nowrap text-center">Jam Masuk</th>
                                <th class="py-3 px-3.5 whitespace-nowrap text-center">Jam Pulang</th>
                                <th class="py-3 px-3.5 whitespace-nowrap text-center">Status</th>
                                <th class="py-3 px-3.5 whitespace-nowrap text-right pr-4">Keterangan / Keterlambatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                            {{-- 1. Data Rekap Absensi yang Sudah Tercatat --}}
                            @foreach($recentAttendances as $att)
                                @php
                                    $isExcused = in_array($att->status, ['sick', 'leave', 'holiday', 'absent']);
                                    $staffName = $att->membership?->user?->name ?? $att->membership?->display_name ?? 'Staf';
                                    $roleLabel = $att->membership?->role?->value === 'admin' ? 'Kasir' : ($att->membership?->role?->value === 'barber' ? 'Barber' : 'Owner');
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition">
                                    <td class="py-3 px-3.5 font-semibold whitespace-nowrap">
                                        <p class="font-bold text-zinc-900">{{ $att->date->locale('id')->isoFormat('D MMM Y') }}</p>
                                        <p class="text-[10px] text-zinc-400 font-semibold">{{ $att->date->locale('id')->isoFormat('dddd') }}</p>
                                    </td>
                                    <td class="py-3 px-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                {{ strtoupper(substr($staffName, 0, 2)) }}
                                            </div>
                                            <div>
                                                <p class="font-bold text-zinc-900 leading-snug">{{ $staffName }}</p>
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold {{ $att->membership?->role?->value === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700' }}">
                                                    {{ $roleLabel }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3.5 num font-bold text-zinc-800 text-center whitespace-nowrap">
                                        @if($isExcused)
                                            <span class="text-zinc-400 font-semibold">-</span>
                                        @elseif($att->check_in_at)
                                            <span class="text-zinc-900 font-extrabold">{{ $att->check_in_at->format('H:i') }}</span>
                                            <span class="text-[10px] text-zinc-400 font-normal">WIB</span>
                                        @else
                                            <span class="text-zinc-400 italic">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3.5 num font-bold text-zinc-800 text-center whitespace-nowrap">
                                        @if($isExcused)
                                            <span class="text-zinc-400 font-semibold">-</span>
                                        @elseif($att->check_out_at)
                                            <span class="text-zinc-900 font-extrabold">{{ $att->check_out_at->format('H:i') }}</span>
                                            <span class="text-[10px] text-zinc-400 font-normal">WIB</span>
                                        @else
                                            <span class="text-amber-600 font-bold text-[11px] bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/60">
                                                Aktif Bertugas
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                        @if($att->status === 'present')
                                            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200/60">
                                                Hadir Tepat Waktu
                                            </span>
                                        @elseif($att->status === 'late')
                                            <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200/60">
                                                Terlambat Masuk
                                            </span>
                                        @elseif($att->status === 'leave')
                                            <span class="px-2.5 py-1 rounded-full bg-sky-50 text-sky-700 font-bold text-[11px] border border-sky-200/60">
                                                Izin / Cuti
                                            </span>
                                        @elseif($att->status === 'sick')
                                            <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-bold text-[11px] border border-indigo-200/60">
                                                Sakit
                                            </span>
                                        @elseif($att->status === 'holiday')
                                            <span class="px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200/50 font-bold text-[11px]">
                                                Libur
                                            </span>
                                        @elseif($att->status === 'absent')
                                            <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200/50 font-bold text-[11px]">
                                                Alpa / Tidak Hadir
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 font-bold text-[11px] border border-zinc-200/50">
                                                {{ ucfirst($att->status ?? 'hadir') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3.5 text-right pr-4 font-mono whitespace-nowrap">
                                        @if($isExcused)
                                            <span class="text-zinc-400 font-semibold">{{ ucfirst($att->status) }}</span>
                                        @elseif($att->late_minutes > 0)
                                            <span class="inline-flex items-center gap-1 text-rose-700 font-bold text-xs num">
                                                <x-ui.icon name="clock" class="w-3 h-3 text-rose-500 shrink-0" />
                                                <span>+{{ $att->late_minutes }} mnt</span>
                                            </span>
                                        @else
                                            <span class="text-emerald-600 font-medium">Tepat Waktu</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            {{-- 2. Data Staf yang Belum Absen Masuk Hari Ini (Disatukan ke Tabel) --}}
                            @if($isTodayPeriod && isset($todayUnattendedStaff) && $todayUnattendedStaff->isNotEmpty())
                                @foreach($todayUnattendedStaff as $unattStaff)
                                    @php
                                        $sched = ($allStaffSchedulesToday ?? collect())->get($unattStaff->id);
                                        $isStaffOff = $sched ? (bool) $sched->is_off : false;
                                        $staffName = $unattStaff->display_name ?? $unattStaff->user?->name ?? 'Staf';
                                        $roleLabel = $unattStaff->role?->value === 'admin' ? 'Kasir' : ($unattStaff->role?->value === 'barber' ? 'Barber' : 'Owner');
                                    @endphp
                                    <tr class="hover:bg-zinc-50/60 transition bg-zinc-50/20">
                                        <td class="py-3 px-3.5 font-semibold whitespace-nowrap">
                                            <p class="font-bold text-zinc-900">{{ now()->locale('id')->isoFormat('D MMM Y') }}</p>
                                            <p class="text-[10px] text-zinc-400 font-semibold">{{ now()->locale('id')->isoFormat('dddd') }}</p>
                                        </td>
                                        <td class="py-3 px-3.5 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-zinc-200/80 text-zinc-600 flex items-center justify-center font-bold text-xs shrink-0">
                                                    {{ strtoupper(substr($staffName, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <p class="font-bold text-zinc-800 leading-snug">{{ $staffName }}</p>
                                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold {{ $unattStaff->role?->value === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700' }}">
                                                        {{ $roleLabel }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3.5 num text-zinc-400 text-center whitespace-nowrap">
                                            -
                                        </td>
                                        <td class="py-3 px-3.5 num text-zinc-400 text-center whitespace-nowrap">
                                            -
                                        </td>
                                        <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                            @if($isStaffOff)
                                                <span class="px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 font-bold text-[11px] border border-zinc-200/50">
                                                    Off (Libur)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/60">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    Belum Masuk
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3.5 text-right pr-4 font-mono whitespace-nowrap">
                                            @if($isStaffOff)
                                                <span class="text-zinc-400 font-semibold">Jadwal Libur</span>
                                            @elseif($sched && $sched->start_time)
                                                <span class="text-amber-700 font-bold text-xs num">
                                                    Shift: {{ substr((string) $sched->start_time, 0, 5) }} - {{ substr((string) $sched->end_time, 0, 5) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400 italic">Belum Hadir</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($recentAttendances->hasPages())
                <div class="p-4 border-t border-zinc-100">
                    {{ $recentAttendances->links() }}
                </div>
            @endif
        </x-ui.card>

        <!-- ============================================================ -->
        <!-- 5. RIWAYAT PERMOHONAN IZIN SAYA                             -->
        <!-- ============================================================ -->
        <x-ui.card>
            <x-slot:header>
                <div>
                    <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm">
                        <x-ui.icon name="calendar" class="w-4 h-4 text-amber-500" />
                        <span>Riwayat Pengajuan Izin Saya</span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Status persetujuan permohonan cuti, sakit, dan izin libur oleh Owner</p>
                </div>
            </x-slot:header>

            @if($timeOffRequests->isEmpty())
                <div class="text-center py-8 text-xs text-zinc-400 italic">
                    Belum ada riwayat permohonan izin yang diajukan.
                </div>
            @else
                <div class="divide-y divide-zinc-100 text-xs">
                    @foreach($timeOffRequests as $req)
                        <div class="py-3 space-y-1.5">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-zinc-900 num text-xs">
                                        {{ $req->date_from->format('d M Y') }} @if(!$req->date_from->isSameDay($req->date_to)) &ndash; {{ $req->date_to->format('d M Y') }} @endif
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $req->type === 'sick' ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($req->type === 'leave' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-purple-50 text-purple-700 border border-purple-200') }}">
                                        {{ $req->type_label }}
                                    </span>
                                </div>

                                <div>
                                    @if($req->status === 'approved')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                            <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                            <span>Disetujui Owner</span>
                                        </span>
                                    @elseif($req->status === 'rejected')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                            <x-ui.icon name="x" class="w-3 h-3 text-rose-600" />
                                            <span>Ditolak</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            <span>Menunggu Review</span>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <p class="text-zinc-600 text-xs"><span class="font-semibold text-zinc-700">Alasan:</span> {{ $req->reason }}</p>

                            @if($req->status === 'rejected' && $req->notes)
                                <p class="text-rose-600 text-[11px] font-semibold bg-rose-50/60 p-2.5 rounded-xl border border-rose-200/50">
                                    Catatan Penolakan: {{ $req->notes }} (Izin dibatalkan &amp; ketidakhadiran menjadi tanggung jawab staf).
                                </p>
                            @endif

                            @if($req->attachment_url)
                                <div class="pt-1">
                                    <a href="{{ $req->attachment_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold shadow-2xs transition">
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-amber-400" />
                                        <span>Lihat Dokumen Bukti Surat</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    <!-- MODAL PENGAJUAN IZIN KASIR (CUTI / SAKIT / LIBUR) -->
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

            <form method="POST" action="{{ route('cashier.time-off.store') }}" enctype="multipart/form-data" class="flex-1 overflow-y-auto overscroll-contain p-5 space-y-4 text-xs">
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
                        id="cashier-time-off-file"
                        name="attachment"
                        required
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="sr-only"
                        onchange="updateFileNameDisplay(this, 'cashier-file-name', 'cashier-file-placeholder')"
                    />
                    <label
                        for="cashier-time-off-file"
                        class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-dashed border-zinc-300 hover:border-amber-400 bg-zinc-50 hover:bg-amber-50/20 transition cursor-pointer group text-center"
                    >
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 group-hover:bg-amber-500 group-hover:text-zinc-950 flex items-center justify-center transition shadow-2xs">
                            <x-ui.icon name="upload" class="w-5 h-5" />
                        </div>
                        <div id="cashier-file-placeholder" class="space-y-0.5">
                            <p class="font-extrabold text-xs text-zinc-800 group-hover:text-amber-800">
                                Klik untuk Pilih Foto / Dokumen
                            </p>
                            <p class="text-[10px] text-zinc-400">
                                Format JPG, PNG, atau PDF (Maksimal 5 MB)
                            </p>
                        </div>
                        <div id="cashier-file-name" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-800 text-[11px] font-bold">
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
                <h3 class="font-extrabold text-xs text-zinc-900">QR Absensi Kasir / Admin</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-staff-qr-code').close()" class="text-zinc-400 hover:text-zinc-700 p-1 cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <div class="p-6 text-center space-y-4">
            <!-- Profil Singkat Staf -->
            <div class="space-y-1">
                <h4 class="font-black text-base text-zinc-950">{{ $membership?->display_name ?? auth()->user()->name }}</h4>
                <p class="text-xs text-zinc-500">{{ $branch?->name ?? 'Cabang Outlet' }} &bull; Kasir / Admin</p>
            </div>

            <!-- Box QR Code Berkualitas Tinggi -->
            <div class="p-4 rounded-3xl bg-zinc-50 border border-zinc-200 inline-block shadow-inner mx-auto">
                <div id="cashier-staff-qrcode" class="flex items-center justify-center min-w-[210px] min-h-[210px] bg-white p-2 rounded-2xl"></div>
            </div>

            <p class="text-[11px] text-zinc-400 leading-relaxed">
                Tunjukkan QR ke scanner kasir untuk absen, atau unduh gambar QR untuk disimpan di galeri ponsel Anda.
            </p>
        </div>

        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center gap-2">
            <button
                type="button"
                onclick="downloadStaffQr('{{ addslashes($membership?->display_name ?? auth()->user()->name) }}', '{{ addslashes($branch?->name ?? 'Cabang Outlet') }}', 'cashier-staff-qrcode')"
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
        id="cashier-staff-qr-script"
        data-payload="{{ $membership?->getAttendanceQrPayload() }}"
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
            renderStaffQr('cashier-staff-qrcode', 'cashier-staff-qr-script');
        }

        function downloadStaffQr(staffName = 'Staff', branchName = '', boxId = 'cashier-staff-qrcode') {
            const qrBox = document.getElementById(boxId);
            if (!qrBox) return;

            const canvas = qrBox.querySelector('canvas');
            const img = qrBox.querySelector('img');

            if (!canvas && !img) {
                renderStaffQr('cashier-staff-qrcode', 'cashier-staff-qr-script');
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
</x-layouts.app>
