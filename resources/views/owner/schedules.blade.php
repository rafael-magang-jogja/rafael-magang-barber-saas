<x-layouts.app title="Jadwal Shift Kerja Barber" subtitle="Jadwal shift kerja mingguan dan jam istirahat barber" role="owner">
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

        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();
    @endphp

    <!-- Sub-Navigation Tab Khusus Manajemen Jadwal & Absen -->
    <x-owner.schedule-tabs active="schedules" />

    <!-- Statistik Ringkas Shift Hari Ini -->
    <div class="schedules-stat-grid stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-3.5 mb-6 transition-all duration-500 ease-in-out">
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-zinc-500">Total Barber Aktif</p>
            <p class="text-2xl font-black text-zinc-900 mt-1 num">{{ $barbers->count() }}</p>
            <p class="text-[11px] text-zinc-400 mt-0.5">Terdaftar di sistem</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-emerald-600">Bertugas Hari Ini</p>
            <p class="text-2xl font-black text-emerald-700 mt-1 num">{{ $todayWorkingCount }}</p>
            <p class="text-[11px] text-zinc-400 mt-0.5">{{ now()->translatedFormat('l') }} ini</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-rose-600">Libur Shift Hari Ini</p>
            <p class="text-2xl font-black text-rose-700 mt-1 num">{{ $todayOffCount }}</p>
            <p class="text-[11px] text-zinc-400 mt-0.5">Jadwal mingguan OFF</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-amber-600">Hari Operasional</p>
            <p class="text-base font-black text-zinc-900 mt-2 truncate">{{ now()->translatedFormat('d M Y') }}</p>
            <p class="text-[11px] text-zinc-400 mt-0.5">Pekan berjalan</p>
        </div>
    </div>

    <!-- Matriks Jadwal Kerja Mingguan -->
    <x-ui.card class="mb-6">
        <x-slot:header>
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5 w-full">
                <div>
                    <h2 class="font-extrabold flex items-center gap-2 text-zinc-900 text-base">
                        <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                        <span>Matriks Jadwal Shift &amp; Jam Istirahat Mingguan</span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Jadwal operasional rutin setiap pekan dari hari Senin sampai Minggu</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Filter Cabang -->
                    <form method="GET" action="{{ route('owner.schedules') }}" class="flex items-center">
                        <x-ui.select
                            name="branch_id"
                            size="sm"
                            placeholder="Semua Cabang ({{ $branches->count() }})"
                            icon="store"
                            :options="$branchFilterOptions"
                            :value="request('branch_id')"
                            search-placeholder="Cari cabang..."
                            class="w-48 sm:w-56"
                            auto-submit
                        />
                    </form>

                    <!-- Indikator Legend -->
                    <div class="flex items-center gap-2 text-[11px] font-semibold text-zinc-500 bg-zinc-50 px-2.5 py-1.5 rounded-xl border border-zinc-200/80">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Shift</span>
                        </span>
                        <span class="text-zinc-300">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Istirahat</span>
                        </span>
                        <span class="text-zinc-300">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-zinc-300"></span>
                            <span>Libur</span>
                        </span>
                    </div>
                </div>
            </div>
        </x-slot:header>

        <div class="overflow-x-auto -mx-4 -mb-4 sm:-mx-5 sm:-mb-5 border-t border-zinc-100">
            <table class="w-full text-xs border-collapse">
                <thead class="bg-zinc-50/80 text-zinc-500 border-b border-zinc-200/80 text-[11px] font-bold">
                    <tr>
                        <th class="py-3 px-4 text-left uppercase tracking-wider min-w-[170px]">Barber</th>
                        @foreach([1, 2, 3, 4, 5, 6, 0] as $dayNum)
                            @php
                                $isTodayCol = ($dayNum === $currentDayOfWeek);
                            @endphp
                            <th class="py-2.5 px-2 text-center min-w-[115px] {{ $isTodayCol ? 'bg-amber-500/10 text-amber-950 border-x border-amber-300/40' : 'text-zinc-600' }}">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-xs font-black uppercase tracking-wider {{ $isTodayCol ? 'text-amber-900' : 'text-zinc-700' }}">{{ $daysName[$dayNum] }}</span>
                                    @if($isTodayCol)
                                        <span class="mt-0.5 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-amber-500 text-[8.5px] font-black text-white tracking-wider leading-none shadow-2xs">
                                            <span class="w-1 h-1 rounded-full bg-white animate-pulse"></span>
                                            HARI INI
                                        </span>
                                    @else
                                        <span class="mt-0.5 text-[8.5px] text-transparent select-none leading-none">-</span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                        <th class="py-3 px-3 text-center uppercase tracking-wider w-14">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white">
                    @forelse($barbers as $barber)
                        @php
                            $barberSchedules = $schedules->get($barber->id, collect());
                        @endphp
                        <tr class="hover:bg-zinc-50/60 transition-colors group">
                            <!-- Barber & Cabang (Atas Bawah Tanpa Icon) -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-zinc-900 text-amber-400 font-black text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($barber->display_name ?? $barber->user?->name ?? 'B', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-zinc-900 truncate">{{ $barber->display_name ?? $barber->user?->name }}</p>
                                        <p class="text-[11px] text-zinc-400 font-semibold truncate mt-0.5">{{ $barber->branch?->name ?? 'Semua Cabang' }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- 7 Hari Kerja -->
                            @foreach([1, 2, 3, 4, 5, 6, 0] as $dayNum)
                                @php
                                    $sched = $barberSchedules->firstWhere('day_of_week', $dayNum);
                                    $isTodayCol = ($dayNum === $currentDayOfWeek);
                                @endphp
                                <td class="py-2.5 px-1.5 text-center align-middle {{ $isTodayCol ? 'bg-amber-500/[0.04] border-x border-amber-300/30' : '' }}">
                                    @if(!$sched || $sched->is_off)
                                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg {{ $isTodayCol ? 'bg-rose-50 text-rose-500 border border-rose-200/60 font-black' : 'bg-zinc-100/70 text-zinc-400 font-bold' }} text-[10px] tracking-wider uppercase">
                                            Libur
                                        </span>
                                    @else
                                        <div class="inline-flex flex-col items-center justify-center py-1.5 px-2 rounded-xl {{ $isTodayCol ? 'bg-white border-amber-300 shadow-2xs ring-1 ring-amber-400/20' : 'bg-zinc-50 border-zinc-200/80 group-hover:bg-white group-hover:border-zinc-300' }} border transition w-full max-w-[110px]">
                                            <!-- Jam Shift -->
                                            <span class="text-xs font-extrabold {{ $isTodayCol ? 'text-amber-950 font-black' : 'text-zinc-900' }} num tracking-tight leading-tight">
                                                {{ substr($sched->start_time, 0, 5) }} - {{ substr($sched->end_time, 0, 5) }}
                                            </span>
                                            <!-- Jam Istirahat -->
                                            @if($sched->break_start && $sched->break_end)
                                                <div class="mt-1 flex items-center justify-center gap-1 text-[9.5px] font-semibold text-zinc-500 leading-none" title="Istirahat: {{ substr($sched->break_start, 0, 5) }} - {{ substr($sched->break_end, 0, 5) }}">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                                    <span class="num text-zinc-600">Ist {{ substr($sched->break_start, 0, 5) }}-{{ substr($sched->break_end, 0, 5) }}</span>
                                                </div>
                                            @else
                                                <div class="mt-1 text-[9.5px] text-zinc-400 font-medium leading-none">
                                                    Non-stop
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach

                            <!-- Tombol Aksi (Icon SVG Only - Buka Halaman Baru) -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <a
                                    href="{{ route('owner.schedules.edit', $barber) }}"
                                    title="Edit Shift &amp; Istirahat: {{ $barber->display_name ?? $barber->user?->name }}"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-zinc-100 hover:bg-zinc-900 hover:text-white text-zinc-700 transition-all cursor-pointer shadow-2xs active:scale-95 group/btn"
                                >
                                    <x-ui.icon name="edit" class="w-4 h-4 text-amber-600 group-hover/btn:text-amber-400" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-12 text-zinc-400 italic">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-ui.icon name="calendar" class="w-8 h-8 text-zinc-300" />
                                    <span>Belum ada data barber terdaftar di cabang ini.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-layouts.app>
