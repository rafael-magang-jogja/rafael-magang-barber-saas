<x-layouts.app title="Atur Jadwal Shift & Istirahat" subtitle="Jadwal operasional dan jam istirahat mingguan barber" role="owner">
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

        $branchOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();
    @endphp

    <!-- Tombol Kembali -->
    <div class="mb-4">
        <a
            href="{{ route('owner.schedules') }}"
            class="inline-flex items-center gap-2 text-xs font-bold text-zinc-500 hover:text-zinc-950 transition group"
        >
            <span class="p-1.5 rounded-xl bg-white border border-zinc-200 group-hover:border-zinc-300 shadow-2xs">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 text-zinc-600" />
            </span>
            <span>Kembali ke Matriks Jadwal Mingguan</span>
        </a>
    </div>

    <!-- Info Profil Barber -->
    <x-ui.card class="mb-5 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3.5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-zinc-950 text-amber-400 font-black text-base flex items-center justify-center shrink-0 shadow-2xs ring-1 ring-zinc-800">
                    {{ strtoupper(substr($barber->display_name ?? $barber->user?->name ?? 'B', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-sm sm:text-base font-extrabold text-zinc-900 truncate">{{ $barber->display_name ?? $barber->user?->name }}</h2>
                        <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 text-[10px] font-bold">Barber</span>
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5 truncate">
                        <span>{{ $barber->user?->email ?? $barber->user?->phone ?? '-' }}</span>
                        <span class="text-zinc-300 mx-1">·</span>
                        <span class="text-zinc-400 font-medium">Spesialisasi:</span>
                        <span class="font-semibold text-zinc-700">{{ $barber->specialty ?: 'Barber Reguler' }}</span>
                    </p>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 text-xs text-zinc-600 bg-zinc-50 px-3 py-1.5 rounded-xl border border-zinc-200/80 shrink-0 self-start sm:self-auto">
                <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                <span class="text-zinc-400 font-medium">Cabang Terdaftar:</span>
                <span class="font-bold text-zinc-900">{{ $barber->branch?->name ?? 'Semua Cabang' }}</span>
            </div>
        </div>
    </x-ui.card>

    <!-- Form Pengaturan Shift Mingguan -->
    <form method="POST" action="{{ route('owner.schedules.update', $barber) }}" class="space-y-5 sm:space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="membership_id" value="{{ $barber->id }}" />

        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5 w-full min-w-0 pb-3.5 border-b border-zinc-100">
                    <!-- Judul & Deskripsi Header -->
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-sm sm:text-base text-zinc-900 flex items-center gap-2">
                            <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Jadwal Operasional 7 Hari</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5 leading-relaxed">
                            Atur jam masuk, pulang, istirahat rutin, atau tandai libur shift
                        </p>
                    </div>

                    <!-- Pilih Cabang Penugasan (Mobile & Tablet Friendly) -->
                    <div class="w-full sm:w-auto shrink-0 sm:self-start lg:self-auto">
                        <div class="flex items-center justify-between sm:justify-start gap-2.5 bg-zinc-50/90 sm:bg-zinc-50/70 p-1.5 sm:px-3 sm:py-1.5 rounded-xl border border-zinc-200/80 shadow-2xs">
                            <span class="text-xs font-bold text-zinc-600 whitespace-nowrap flex items-center gap-1.5 pl-1.5 sm:pl-0 shrink-0">
                                <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                <span>Cabang Penugasan:</span>
                            </span>
                            <x-ui.select
                                name="branch_id"
                                size="sm"
                                align="right"
                                icon="store"
                                :options="$branchOptions"
                                :value="(string) ($barber->branch_id ?? $branches->first()?->id)"
                                search-placeholder="Cari cabang..."
                                class="flex-1 min-w-0 sm:flex-initial sm:w-52"
                            />
                        </div>
                    </div>
                </div>
            </x-slot:header>

            <!-- Aksi Cepat / Template Shift -->
            <div class="mb-5 p-3 sm:p-3.5 rounded-2xl bg-amber-500/10 border border-amber-200/80">
                <!-- Teks "Template Shift Cepat" DITARUH DI ATAS -->
                <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-amber-200/60">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0">
                            <x-ui.icon name="zap" class="w-3.5 h-3.5 text-amber-700" />
                        </span>
                        <div>
                            <span class="text-xs font-extrabold text-amber-950 block leading-tight">Template Shift Cepat</span>
                            <span class="text-[10px] text-amber-800/80 font-medium block sm:hidden">Pilih template untuk terapkan ke hari aktif</span>
                        </div>
                    </div>
                    <span class="text-[11px] text-amber-800/80 font-semibold hidden sm:inline">Terapkan otomatis ke hari kerja aktif</span>
                </div>

                <!-- Shortcut 3 Baris pada Mode Mobile & Tablet (2 Kolom di Baris 1-2, 1 Full-Width di Baris 3) -->
                <div class="grid grid-cols-2 xl:flex xl:flex-wrap xl:items-center gap-2">
                    <!-- Baris 1: Kolom 1 -->
                    <button type="button" onclick="applyTemplate('10:00', '21:00', '13:00', '14:00')" class="px-2.5 py-2 rounded-xl bg-white border border-amber-300/80 text-amber-950 font-bold hover:bg-amber-100/70 transition text-xs shadow-2xs cursor-pointer flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-1.5 text-center">
                        <span>10:00 - 21:00</span>
                        <span class="text-amber-700/80 font-semibold text-[11px]">(Ist: 13-14)</span>
                    </button>

                    <!-- Baris 1: Kolom 2 -->
                    <button type="button" onclick="applyTemplate('09:00', '18:00', '12:00', '13:00')" class="px-2.5 py-2 rounded-xl bg-white border border-amber-300/80 text-amber-950 font-bold hover:bg-amber-100/70 transition text-xs shadow-2xs cursor-pointer flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-1.5 text-center">
                        <span>09:00 - 18:00</span>
                        <span class="text-amber-700/80 font-semibold text-[11px]">(Ist: 12-13)</span>
                    </button>

                    <!-- Baris 2: Kolom 1 -->
                    <button type="button" onclick="applyTemplate('13:00', '22:00', '17:00', '18:00')" class="px-2.5 py-2 rounded-xl bg-white border border-amber-300/80 text-amber-950 font-bold hover:bg-amber-100/70 transition text-xs shadow-2xs cursor-pointer flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-1.5 text-center">
                        <span>13:00 - 22:00</span>
                        <span class="text-amber-700/80 font-semibold text-[11px]">(Ist: 17-18)</span>
                    </button>

                    <!-- Baris 2: Kolom 2 -->
                    <button type="button" onclick="clearBreak()" class="px-2.5 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-700 font-bold hover:bg-zinc-100 transition text-xs shadow-2xs cursor-pointer flex items-center justify-center gap-1.5 text-center">
                        <span>Non-stop (Tanpa Istirahat)</span>
                    </button>

                    <!-- Baris 3: Full Width (col-span-2) -->
                    <button type="button" onclick="toggleSundayOff()" class="col-span-2 xl:col-auto px-2.5 py-2 rounded-xl bg-white border border-rose-200 text-rose-700 font-bold hover:bg-rose-50 transition text-xs shadow-2xs cursor-pointer flex items-center justify-center gap-1.5 text-center">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                        <span>Liburkan Hari Minggu</span>
                    </button>
                </div>
            </div>

            <!-- List Jadwal 7 Hari (Dioptimalkan Penuh untuk Tablet & Mobile) -->
            <div class="space-y-2.5">
                @foreach([1, 2, 3, 4, 5, 6, 0] as $dayNum)
                    @php
                        $sched = $schedules->firstWhere('day_of_week', $dayNum);
                        $isOff = $sched ? (bool)$sched->is_off : false;
                        $startTime = $sched?->start_time ? substr($sched->start_time, 0, 5) : '10:00';
                        $endTime = $sched?->end_time ? substr($sched->end_time, 0, 5) : '21:00';
                        $breakStart = $sched?->break_start ? substr($sched->break_start, 0, 5) : '13:00';
                        $breakEnd = $sched?->break_end ? substr($sched->break_end, 0, 5) : '14:00';
                    @endphp
                    <div
                        id="row-day-{{ $dayNum }}"
                        class="p-3 sm:p-3.5 rounded-xl border transition-all {{ $isOff ? 'bg-zinc-100/60 border-zinc-200 opacity-60' : 'bg-zinc-50/70 border-zinc-200/80 hover:bg-zinc-50' }}"
                    >
                        <input type="hidden" name="days[{{ $dayNum }}][day_of_week]" value="{{ $dayNum }}" />
                        <input type="hidden" name="days[{{ $dayNum }}][is_off]" value="0" />

                        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5 sm:gap-3">
                            <!-- Baris Header Hari & Toggle Libur/Masuk -->
                            <div class="flex items-center justify-between xl:justify-start gap-3 xl:w-48 shrink-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg {{ $isOff ? 'bg-zinc-200 text-zinc-500' : 'bg-amber-500/15 text-amber-800' }} font-black text-xs flex items-center justify-center shrink-0" id="badge-day-icon-{{ $dayNum }}">
                                        {{ substr($daysName[$dayNum], 0, 3) }}
                                    </span>
                                    <span class="font-extrabold text-zinc-900 text-sm tracking-tight">{{ $daysName[$dayNum] }}</span>
                                </div>

                                <!-- Checkbox Tandai Libur / Bekerja -->
                                <label
                                    id="label-off-{{ $dayNum }}"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold cursor-pointer transition select-none {{ $isOff ? 'bg-rose-50 border-rose-200 text-rose-700 shadow-2xs' : 'bg-white hover:bg-zinc-100/70 border-zinc-200 text-zinc-600 shadow-2xs' }}"
                                >
                                    <input
                                        type="checkbox"
                                        id="is-off-{{ $dayNum }}"
                                        name="days[{{ $dayNum }}][is_off]"
                                        value="1"
                                        @checked($isOff)
                                        data-day="{{ $dayNum }}"
                                        onchange="toggleDay(Number(this.dataset.day))"
                                        class="rounded border-zinc-300 text-rose-600 focus:ring-rose-500 w-4 h-4 cursor-pointer"
                                    />
                                    <span id="text-off-{{ $dayNum }}">{{ $isOff ? 'Libur Shift' : 'Masuk Kerja' }}</span>
                                </label>
                            </div>

                            <!-- Group Jam Shift Kerja & Jam Istirahat -->
                            <div class="branch-hours-group flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-2.5 grow xl:justify-end transition-opacity duration-300 {{ $isOff ? 'opacity-40 pointer-events-none' : '' }}" id="inputs-group-{{ $dayNum }}">
                                <!-- Jam Shift Kerja (Masuk - Pulang) -->
                                <div class="flex items-center justify-between sm:justify-start gap-1 sm:gap-1.5 bg-white px-2 sm:px-2.5 py-1.5 rounded-xl border border-zinc-200/80 shadow-2xs shrink-0 flex-nowrap whitespace-nowrap" id="box-shift-{{ $dayNum }}">
                                    <span class="text-zinc-500 font-bold text-[11px] shrink-0 flex items-center gap-1">
                                        <x-ui.icon name="clock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <span>Shift:</span>
                                    </span>
                                    <input
                                        type="time"
                                        id="start-time-{{ $dayNum }}"
                                        name="days[{{ $dayNum }}][start_time]"
                                        value="{{ $startTime }}"
                                        class="w-16 sm:w-18 px-1 py-1 text-xs font-bold text-zinc-800 bg-white border border-zinc-200 rounded-lg shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none num text-center cursor-pointer"
                                    />
                                    <span class="text-zinc-300 font-bold px-0.5">-</span>
                                    <input
                                        type="time"
                                        id="end-time-{{ $dayNum }}"
                                        name="days[{{ $dayNum }}][end_time]"
                                        value="{{ $endTime }}"
                                        class="w-16 sm:w-18 px-1 py-1 text-xs font-bold text-zinc-800 bg-white border border-zinc-200 rounded-lg shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none num text-center cursor-pointer"
                                    />
                                </div>

                                <!-- Jam Istirahat Rutin (Mulai - Selesai) -->
                                <div class="flex items-center justify-between sm:justify-start gap-1 sm:gap-1.5 bg-amber-50/70 px-2 sm:px-2.5 py-1.5 rounded-xl border border-amber-200/80 shadow-2xs shrink-0 flex-nowrap whitespace-nowrap" id="box-break-{{ $dayNum }}">
                                    <span class="text-amber-800 font-bold text-[11px] shrink-0 flex items-center gap-1">
                                        <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                        <span>Istirahat:</span>
                                    </span>
                                    <input
                                        type="time"
                                        id="break-start-{{ $dayNum }}"
                                        name="days[{{ $dayNum }}][break_start]"
                                        value="{{ $breakStart }}"
                                        placeholder="--:--"
                                        class="w-16 sm:w-18 px-1 py-1 text-xs font-bold text-zinc-700 bg-white border border-amber-200 rounded-lg shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none num text-center cursor-pointer"
                                    />
                                    <span class="text-amber-300 font-bold px-0.5">-</span>
                                    <input
                                        type="time"
                                        id="break-end-{{ $dayNum }}"
                                        name="days[{{ $dayNum }}][break_end]"
                                        value="{{ $breakEnd }}"
                                        placeholder="--:--"
                                        class="w-16 sm:w-18 px-1 py-1 text-xs font-bold text-zinc-700 bg-white border border-amber-200 rounded-lg shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none num text-center cursor-pointer"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Footer Action Buttons -->
            <x-slot:footer>
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <x-ui.button :href="route('owner.schedules')" variant="outline" size="md" class="w-full sm:w-auto justify-center">
                        <span>Batal</span>
                    </x-ui.button>

                    <x-ui.button type="submit" variant="primary" size="md" class="w-full sm:w-auto justify-center shadow-xs">
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Jadwal Shift &amp; Istirahat</span>
                    </x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>

    <script>
        function toggleDay(dayNum) {
            const cb = document.getElementById(`is-off-${dayNum}`);
            const isOff = cb ? cb.checked : false;

            const row = document.getElementById(`row-day-${dayNum}`);
            const dayIcon = document.getElementById(`badge-day-icon-${dayNum}`);
            const labelOff = document.getElementById(`label-off-${dayNum}`);
            const textOff = document.getElementById(`text-off-${dayNum}`);
            const inputsGroup = document.getElementById(`inputs-group-${dayNum}`);

            if (isOff) {
                if (inputsGroup) {
                    inputsGroup.classList.add('opacity-40', 'pointer-events-none');
                }
                if (row) {
                    row.classList.remove('bg-zinc-50/70', 'border-zinc-200/80', 'hover:bg-zinc-50');
                    row.classList.add('bg-zinc-100/60', 'border-zinc-200', 'opacity-60');
                }
                if (dayIcon) {
                    dayIcon.className = 'w-7 h-7 rounded-lg bg-zinc-200 text-zinc-500 font-black text-xs flex items-center justify-center shrink-0';
                }
                if (labelOff) {
                    labelOff.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold cursor-pointer transition select-none bg-rose-50 border-rose-200 text-rose-700 shadow-2xs';
                }
                if (textOff) {
                    textOff.textContent = 'Libur Shift';
                }
            } else {
                if (inputsGroup) {
                    inputsGroup.classList.remove('opacity-40', 'pointer-events-none');
                }
                if (row) {
                    row.classList.remove('bg-zinc-100/60', 'border-zinc-200', 'opacity-60');
                    row.classList.add('bg-zinc-50/70', 'border-zinc-200/80', 'hover:bg-zinc-50');
                }
                if (dayIcon) {
                    dayIcon.className = 'w-7 h-7 rounded-lg bg-amber-500/15 text-amber-800 font-black text-xs flex items-center justify-center shrink-0';
                }
                if (labelOff) {
                    labelOff.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold cursor-pointer transition select-none bg-white hover:bg-zinc-100/70 border-zinc-200 text-zinc-600 shadow-2xs';
                }
                if (textOff) {
                    textOff.textContent = 'Masuk Kerja';
                }
            }
        }

        function applyTemplate(start, end, breakStart, breakEnd) {
            const days = [1, 2, 3, 4, 5, 6, 0];
            days.forEach(day => {
                const cb = document.getElementById(`is-off-${day}`);
                if (!cb || !cb.checked) {
                    const startEl = document.getElementById(`start-time-${day}`);
                    const endEl = document.getElementById(`end-time-${day}`);
                    const breakStartEl = document.getElementById(`break-start-${day}`);
                    const breakEndEl = document.getElementById(`break-end-${day}`);
                    if (startEl) startEl.value = start;
                    if (endEl) endEl.value = end;
                    if (breakStartEl) breakStartEl.value = breakStart;
                    if (breakEndEl) breakEndEl.value = breakEnd;
                }
            });
        }

        function clearBreak() {
            const days = [1, 2, 3, 4, 5, 6, 0];
            days.forEach(day => {
                const breakStartEl = document.getElementById(`break-start-${day}`);
                const breakEndEl = document.getElementById(`break-end-${day}`);
                if (breakStartEl) breakStartEl.value = '';
                if (breakEndEl) breakEndEl.value = '';
            });
        }

        function toggleSundayOff() {
            const sundayCb = document.getElementById('is-off-0');
            if (sundayCb) {
                sundayCb.checked = true;
                toggleDay(0);
            }
        }
    </script>
</x-layouts.app>
