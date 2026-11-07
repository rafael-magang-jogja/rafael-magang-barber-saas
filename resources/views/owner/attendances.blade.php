<x-layouts.app title="Rekap & Log Absensi Barber" subtitle="Catatan kehadiran harian dan keterlambatan barber" role="owner">
    @php
        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $statusFilterOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'present', 'label' => 'Hadir Tepat Waktu'],
            ['value' => 'late', 'label' => 'Terlambat'],
            ['value' => 'sick', 'label' => 'Sakit'],
            ['value' => 'leave', 'label' => 'Izin / Cuti'],
            ['value' => 'holiday', 'label' => 'Libur'],
            ['value' => 'absent', 'label' => 'Alpa / Tidak Hadir'],
        ];

        $attendanceStatusOptions = [
            ['value' => 'present', 'label' => 'Hadir Tepat Waktu'],
            ['value' => 'late', 'label' => 'Terlambat'],
            ['value' => 'sick', 'label' => 'Sakit'],
            ['value' => 'leave', 'label' => 'Izin / Cuti'],
            ['value' => 'holiday', 'label' => 'Libur'],
            ['value' => 'absent', 'label' => 'Alpa / Tanpa Keterangan'],
        ];

        $barberSelectOptions = $barbers->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->display_name ?? $b->user?->name,
            'sublabel' => $b->branch?->name ?? 'Semua Cabang',
        ])->all();
    @endphp

    <!-- Sub-Navigation Tab Khusus Manajemen Jadwal & Absen -->
    <x-owner.schedule-tabs active="attendances" />

    <!-- Statistik Kehadiran Sesuai Periode -->
    <div class="stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-5 sm:mb-6 transition-all duration-500 ease-in-out">
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-zinc-500">Total Absensi</p>
            <p class="text-xl sm:text-2xl font-black text-zinc-900 mt-1 num">{{ $totalRecords }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Sesuai rentang filter</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-emerald-600">Tepat Waktu</p>
            <p class="text-xl sm:text-2xl font-black text-emerald-700 mt-1 num">{{ $presentCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Disiplin jadwal</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs {{ $lateCount > 0 ? 'ring-2 ring-amber-500/20' : '' }}">
            <p class="text-xs font-semibold text-amber-600">Terlambat Masuk</p>
            <p class="text-xl sm:text-2xl font-black text-amber-700 mt-1 num">{{ $lateCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Akumulasi: <span class="font-bold text-amber-700">{{ $totalLateMinutes }} mnt</span></p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-sky-600">Izin / Sakit / Alpa</p>
            <p class="text-xl sm:text-2xl font-black text-sky-700 mt-1 num">{{ $leaveCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Tidak bertugas</p>
        </div>
    </div>

    <!-- Filter & Tombol Catat Absensi Manual -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs mb-5 sm:mb-6">
        <form method="GET" action="{{ route('owner.attendances') }}" class="space-y-3">
            <!-- Baris 1: Preset Periode & Tombol Aksi -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                <!-- Preset Periode (Mobile: Full Width Kanan-Kiri 3 Kolom, Tablet/Desktop: Inline Flex Pill) -->
                <div class="w-full sm:w-auto grid grid-cols-3 sm:inline-flex items-center gap-1 p-1 rounded-xl bg-zinc-100 text-xs font-bold text-zinc-600">
                    <a
                        href="{{ route('owner.attendances', array_merge(request()->query(), ['period' => 'today'])) }}"
                        class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ $period === 'today' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                    >
                        Hari Ini
                    </a>
                    <a
                        href="{{ route('owner.attendances', array_merge(request()->query(), ['period' => '7days'])) }}"
                        class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ $period === '7days' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                    >
                        7 Hari
                    </a>
                    <a
                        href="{{ route('owner.attendances', array_merge(request()->query(), ['period' => 'this_month'])) }}"
                        class="text-center py-2 sm:py-1.5 px-2.5 sm:px-3 rounded-lg whitespace-nowrap transition {{ $period === 'this_month' ? 'bg-white text-zinc-950 shadow-2xs' : 'hover:text-zinc-900' }}"
                    >
                        Bulan Ini
                    </a>
                </div>

                <!-- Tombol Catat Absensi Manual -->
                <button
                    type="button"
                    onclick="document.getElementById('modal-create-attendance').showModal()"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold text-xs transition shadow-xs cursor-pointer w-full sm:w-auto shrink-0 whitespace-nowrap"
                >
                    <x-ui.icon name="plus" class="w-4 h-4" />
                    <span>Catat Absensi Manual</span>
                </button>
            </div>

            <!-- Baris 2: Dropdown Filter (Cabang, Status, Barber) Terstruktur Rapi -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-2.5">
                <!-- Filter Cabang -->
                <x-ui.select
                    name="branch_id"
                    size="sm"
                    icon="store"
                    placeholder="Semua Cabang"
                    :options="$branchFilterOptions"
                    :value="request('branch_id')"
                    search-placeholder="Cari cabang..."
                    auto-submit
                />

                <!-- Filter Status -->
                <x-ui.select
                    name="status"
                    size="sm"
                    icon="filter"
                    :options="$statusFilterOptions"
                    :value="$statusFilter"
                    auto-submit
                />

                <!-- Filter Barber -->
                <x-ui.select
                    name="barber_id"
                    size="sm"
                    icon="scissors"
                    placeholder="Semua Barber"
                    :options="$barberSelectOptions"
                    :value="request('barber_id')"
                    search-placeholder="Cari barber..."
                    auto-submit
                />
            </div>

            <!-- Rentang Kustom (Jika dipilih) -->
            @if($period === 'custom')
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-zinc-100 text-xs font-semibold">
                    <span class="text-zinc-500 text-xs font-bold">Periode:</span>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-2.5 py-1.5 rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 flex-1 sm:flex-initial" />
                    <span class="text-zinc-400">s/d</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="px-2.5 py-1.5 rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 flex-1 sm:flex-initial" />
                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-zinc-900 text-white font-bold text-xs">Cari</button>
                </div>
            @endif
        </form>
    </div>

    <!-- Tabel Rekap & Log Absensi -->
    <x-ui.card data-pagination-container="owner-attendances">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-2 w-full">
                <div>
                    <h2 class="font-bold flex items-center gap-2 text-zinc-900">
                        <x-ui.icon name="check" class="w-4 h-4 text-emerald-600" />
                        <span>Log Absensi &amp; Riwayat Kehadiran</span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Rentang: {{ \Carbon\Carbon::parse($dateFrom)->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->translatedFormat('d M Y') }}</p>
                </div>
                <span class="text-xs text-zinc-400">Total {{ $attendances->total() }} catatan absensi</span>
            </div>
        </x-slot:header>

        <!-- Desktop Table View (Responsif dengan horizontal scroll halus di mode Tablet) -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-xs min-w-[880px] text-left">
                <thead class="text-zinc-500 border-b border-zinc-100 bg-zinc-50/60">
                    <tr class="text-[11px] font-bold">
                        <th class="py-3 px-3.5 whitespace-nowrap">Tanggal</th>
                        <th class="py-3 px-3.5 whitespace-nowrap">Staf / Barber</th>
                        <th class="py-3 px-3.5 whitespace-nowrap">Cabang</th>
                        <th class="py-3 px-3.5 whitespace-nowrap text-center">Jam Masuk</th>
                        <th class="py-3 px-3.5 whitespace-nowrap text-center">Jam Pulang</th>
                        <th class="py-3 px-3.5 whitespace-nowrap text-center">Keterlambatan</th>
                        <th class="py-3 px-3.5 whitespace-nowrap text-center">Status</th>
                        <th class="py-3 px-3.5 whitespace-nowrap text-right pr-4 font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 font-medium text-zinc-800">
                    @forelse($attendances as $att)
                        @php
                            $isExcused = in_array($att->status, ['sick', 'leave', 'holiday', 'absent']);
                            $staffName = $att->membership?->display_name ?? $att->membership?->user?->name ?? 'Staf';
                            $roleLabel = $att->membership?->role?->value === 'admin' ? 'Kasir' : ($att->membership?->specialty ?: 'Barber');
                        @endphp
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="py-3 px-3.5 font-bold text-zinc-900 whitespace-nowrap">
                                <p class="text-xs">{{ $att->date->translatedFormat('d M Y') }}</p>
                                <p class="text-[10px] text-zinc-400 font-semibold">{{ $att->date->translatedFormat('l') }}</p>
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($staffName, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-extrabold text-zinc-900 leading-snug">{{ $staffName }}</p>
                                        <p class="text-[10px] text-zinc-400 font-normal leading-none mt-0.5">{{ $roleLabel }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3.5 font-semibold text-zinc-600 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-md bg-zinc-100 text-zinc-700 text-[11px] font-bold">
                                    {{ $att->branch?->name ?? 'Cabang Utama' }}
                                </span>
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
                                    <span class="text-zinc-400 italic">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                @if($isExcused)
                                    <span class="text-zinc-400 font-semibold">-</span>
                                @elseif($att->late_minutes > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50 num">
                                        <x-ui.icon name="clock" class="w-3 h-3 text-rose-600" />
                                        <span>+{{ $att->late_minutes }} mnt</span>
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-medium">Tepat Waktu</span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                @if($att->status === 'present')
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200/50">Hadir</span>
                                @elseif($att->status === 'late')
                                    <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200/50">Terlambat</span>
                                @elseif($att->status === 'leave')
                                    <span class="px-2.5 py-1 rounded-full bg-sky-50 text-sky-700 font-bold text-[11px] border border-sky-200/50">Cuti</span>
                                @elseif($att->status === 'sick')
                                    <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Izin Sakit</span>
                                @elseif($att->status === 'holiday')
                                    <span class="px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 font-bold text-[11px] border border-purple-200/50">Libur</span>
                                @elseif($att->status === 'absent')
                                    <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Alpa</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 font-bold text-[11px] border border-zinc-200/50">{{ ucfirst($att->status) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 text-right pr-4 whitespace-nowrap">
                                <button
                                    type="button"
                                    data-att-id="{{ $att->id }}"
                                    data-staff-name="{{ $staffName }}"
                                    data-att-date="{{ $att->date->format('d M Y') }}"
                                    data-check-in="{{ $att->check_in_at ? $att->check_in_at->format('H:i') : '' }}"
                                    data-check-out="{{ $att->check_out_at ? $att->check_out_at->format('H:i') : '' }}"
                                    data-status="{{ $att->status }}"
                                    data-late="{{ (int) $att->late_minutes }}"
                                    data-branch-id="{{ $att->branch_id }}"
                                    onclick="openEditAttendanceModal(Number(this.dataset.attId), this.dataset.staffName, this.dataset.attDate, this.dataset.checkIn, this.dataset.checkOut, this.dataset.status, Number(this.dataset.late), Number(this.dataset.branchId))"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition cursor-pointer"
                                >
                                    <x-ui.icon name="edit" class="w-3 h-3 text-amber-600" />
                                    <span>Koreksi</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-10 text-zinc-400 italic">Belum ada data absensi untuk filter dan periode yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile & Tablet Card List View -->
        <div class="divide-y divide-zinc-100 lg:hidden">
            @forelse($attendances as $att)
                @php
                    $isExcused = in_array($att->status, ['sick', 'leave', 'holiday', 'absent']);
                    $staffName = $att->membership?->display_name ?? $att->membership?->user?->name ?? 'Staf';
                    $roleLabel = $att->membership?->role?->value === 'admin' ? 'Kasir' : ($att->membership?->specialty ?: 'Barber');
                @endphp
                <div class="p-3.5 sm:p-4 hover:bg-zinc-50/50 transition">
                    <!-- Top: Avatar, Name, Branch, Status & Tombol Koreksi -->
                    <div class="flex items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                {{ strtoupper(substr($staffName, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-zinc-900 truncate">
                                    {{ $staffName }}
                                </p>
                                <p class="text-[11px] text-zinc-400 truncate">
                                    {{ $roleLabel }} &bull; {{ $att->branch?->name ?? 'Cabang Utama' }}
                                </p>
                            </div>
                        </div>

                        <!-- Status & Koreksi -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($att->status === 'present')
                                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200/50">Hadir</span>
                            @elseif($att->status === 'late')
                                <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200/50">Terlambat</span>
                            @elseif($att->status === 'leave')
                                <span class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 font-bold text-[11px] border border-sky-200/50">Cuti</span>
                            @elseif($att->status === 'sick')
                                <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Izin Sakit</span>
                            @elseif($att->status === 'holiday')
                                <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 font-bold text-[11px] border border-purple-200/50">Libur</span>
                            @elseif($att->status === 'absent')
                                <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Alpa</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-600 font-bold text-[11px] border border-zinc-200/50">{{ ucfirst($att->status) }}</span>
                            @endif

                            <button
                                type="button"
                                data-att-id="{{ $att->id }}"
                                data-staff-name="{{ $staffName }}"
                                data-att-date="{{ $att->date->format('d M Y') }}"
                                data-check-in="{{ $att->check_in_at ? $att->check_in_at->format('H:i') : '' }}"
                                data-check-out="{{ $att->check_out_at ? $att->check_out_at->format('H:i') : '' }}"
                                data-status="{{ $att->status }}"
                                data-late="{{ (int) $att->late_minutes }}"
                                data-branch-id="{{ $att->branch_id }}"
                                onclick="openEditAttendanceModal(Number(this.dataset.attId), this.dataset.staffName, this.dataset.attDate, this.dataset.checkIn, this.dataset.checkOut, this.dataset.status, Number(this.dataset.late), Number(this.dataset.branchId))"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-[11px] font-bold transition cursor-pointer"
                            >
                                <x-ui.icon name="edit" class="w-3 h-3 text-amber-600" />
                                <span>Koreksi</span>
                            </button>
                        </div>
                    </div>

                    <!-- Bottom: Data Ringkas (Tanggal, Jam Masuk, Jam Pulang, Keterlambatan) -->
                    <div class="mt-3 pt-2.5 border-t border-zinc-100 grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs">
                        <!-- Tanggal -->
                        <div class="order-1 sm:order-1">
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider">Tanggal</span>
                            <p class="font-bold text-zinc-900 text-xs mt-0.5 leading-tight">{{ $att->date->translatedFormat('d M Y') }}</p>
                            <p class="text-[10px] text-zinc-400 font-medium">{{ $att->date->translatedFormat('l') }}</p>
                        </div>

                        <!-- Jam Masuk -->
                        <div class="order-3 sm:order-2">
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider">Jam Masuk</span>
                            <div class="mt-0.5">
                                @if($isExcused)
                                    <span class="text-zinc-400 font-semibold text-xs">-</span>
                                @elseif($att->check_in_at)
                                    <span class="font-extrabold text-zinc-900 num text-xs">{{ $att->check_in_at->format('H:i') }}</span>
                                    <span class="text-[10px] text-zinc-400 font-normal">WIB</span>
                                @else
                                    <span class="text-zinc-400 italic text-xs">-</span>
                                @endif
                            </div>
                        </div>

                        <!-- Jam Pulang -->
                        <div class="order-4 sm:order-3">
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider">Jam Pulang</span>
                            <div class="mt-0.5">
                                @if($isExcused)
                                    <span class="text-zinc-400 font-semibold text-xs">-</span>
                                @elseif($att->check_out_at)
                                    <span class="font-extrabold text-zinc-900 num text-xs">{{ $att->check_out_at->format('H:i') }}</span>
                                    <span class="text-[10px] text-zinc-400 font-normal">WIB</span>
                                @else
                                    <span class="text-zinc-400 italic text-xs">-</span>
                                @endif
                            </div>
                        </div>

                        <!-- Keterlambatan -->
                        <div class="order-2 sm:order-4">
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider">Keterlambatan</span>
                            <div class="mt-0.5">
                                @if($isExcused)
                                    <span class="text-zinc-400 font-semibold text-xs">-</span>
                                @elseif($att->late_minutes > 0)
                                    <span class="inline-flex items-center gap-1 text-rose-700 font-bold text-xs num">
                                        <x-ui.icon name="clock" class="w-3 h-3 text-rose-500 shrink-0" />
                                        <span>+{{ $att->late_minutes }} mnt</span>
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-semibold text-xs">Tepat Waktu</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-10 px-4 text-zinc-400 italic text-xs">
                    Belum ada data absensi untuk filter dan periode yang dipilih.
                </div>
            @endforelse
        </div>

        @if($attendances->hasPages())
            <div class="p-4 border-t border-zinc-100">
                {{ $attendances->links() }}
            </div>
        @endif
    </x-ui.card>

    <!-- Modal Form Catat Absensi Manual -->
    <dialog id="modal-create-attendance" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/50 w-[calc(100%-2rem)] max-w-lg mx-auto my-auto">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900 flex items-center gap-2">
                    <x-ui.icon name="plus" class="w-4 h-4 text-amber-600" />
                    <span>Catat Absensi Manual Baru</span>
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Input absensi jika barber berhalangan check-in via perangkat</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-attendance').close()" class="text-zinc-400 hover:text-zinc-700">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('owner.attendances.store') }}" class="p-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.select
                    label="Pilih Barber *"
                    name="membership_id"
                    icon="scissors"
                    placeholder="-- Pilih Barber --"
                    :options="$barberSelectOptions"
                    search-placeholder="Cari nama barber..."
                    required
                />
                <x-ui.select
                    label="Pilih Cabang *"
                    name="branch_id"
                    icon="store"
                    :options="$branchFilterOptions"
                    :value="$branches->first()?->id"
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tanggal Absensi <span class="text-rose-500">*</span></label>
                    <input type="date" name="date" required value="{{ now()->toDateString() }}" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <x-ui.select
                    label="Status Kehadiran *"
                    name="status"
                    icon="check"
                    :options="$attendanceStatusOptions"
                    value="present"
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Jam Masuk (Check-in)</label>
                    <input type="time" name="check_in_time" value="10:00" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Jam Pulang (Check-out)</label>
                    <input type="time" name="check_out_time" value="21:00" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Keterlambatan (menit)</label>
                    <input type="number" name="late_minutes" value="0" min="0" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-create-attendance').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="md" variant="primary">
                    <span>Simpan Absensi</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <!-- Modal Form Koreksi / Edit Absensi -->
    <dialog id="modal-edit-attendance" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/50 w-[calc(100%-2rem)] max-w-lg mx-auto my-auto">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900 flex items-center gap-2">
                    <x-ui.icon name="edit" class="w-4 h-4 text-amber-600" />
                    <span>Koreksi Absensi: <span id="modal-edit-att-barber" class="text-amber-700"></span></span>
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Tanggal: <span id="modal-edit-att-date" class="font-bold text-zinc-600"></span></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-edit-attendance').close()" class="text-zinc-400 hover:text-zinc-700">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form id="form-edit-attendance" method="POST" action="" class="p-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.select
                    id="edit-att-branch-wrapper"
                    label="Pilih Cabang"
                    name="branch_id"
                    icon="store"
                    :options="$branchFilterOptions"
                />
                <x-ui.select
                    id="edit-att-status-wrapper"
                    label="Status Kehadiran *"
                    name="status"
                    icon="check"
                    :options="$attendanceStatusOptions"
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Jam Masuk (Check-in)</label>
                    <input type="time" id="edit-att-in" name="check_in_time" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Jam Pulang (Check-out)</label>
                    <input type="time" id="edit-att-out" name="check_out_time" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Keterlambatan (menit)</label>
                    <input type="number" id="edit-att-late" name="late_minutes" min="0" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-edit-attendance').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="md" variant="primary">
                    <span>Simpan Koreksi</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <script>
        function openEditAttendanceModal(id, barberName, dateStr, checkIn, checkOut, status, lateMinutes, branchId) {
            document.getElementById('modal-edit-att-barber').textContent = barberName;
            document.getElementById('modal-edit-att-date').textContent = dateStr;
            document.getElementById('form-edit-attendance').action = `/owner/attendances/${id}`;

            document.getElementById('edit-att-in').value = checkIn;
            document.getElementById('edit-att-out').value = checkOut;
            document.getElementById('edit-att-late').value = lateMinutes;

            const branchOpt = document.querySelector('#edit-att-branch-wrapper [data-select-option][data-value="' + branchId + '"]');
            if (branchOpt) branchOpt.click();

            const statusOpt = document.querySelector('#edit-att-status-wrapper [data-select-option][data-value="' + status + '"]');
            if (statusOpt) statusOpt.click();

            document.getElementById('modal-edit-attendance').showModal();
        }
    </script>
</x-layouts.app>
