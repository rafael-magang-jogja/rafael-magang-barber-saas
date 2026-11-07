<x-layouts.app title="Pengaturan Jam Kerja & Toleransi Absen" subtitle="Jam operasional cabang, toleransi keterlambatan, dan radius absensi" role="owner">
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

        $currentHours = $selectedBranch?->opening_hours ?? [];
    @endphp

    <!-- Sub-Navigation Tab Khusus Manajemen Jadwal & Absen -->
    <x-owner.schedule-tabs active="settings" />

    <!-- Pemilih Cabang (Jika ada lebih dari 1 cabang) -->
    @if($branches->count() > 1)
        <div class="mb-6 p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="mb-2 sm:mb-2.5">
                <span class="text-[11px] font-black text-zinc-400 uppercase tracking-wider block">Pilih Cabang:</span>
            </div>
            <!-- Mode Mobile: 2 kolom (kanan kiri tanpa scroll), Mode Tablet/Desktop: flex rapi -->
            <div class="grid grid-cols-2 sm:flex sm:flex-wrap sm:items-center gap-2">
                @foreach($branches as $b)
                    <a
                        href="{{ route('owner.schedule-settings', ['branch_id' => $b->id]) }}"
                        class="px-3 py-2 sm:px-3.5 sm:py-2 rounded-xl text-xs font-bold transition flex items-center justify-center sm:justify-start gap-1.5 sm:gap-2 {{ $selectedBranch?->id === $b->id ? 'bg-amber-500 text-zinc-950 shadow-2xs font-black border border-amber-500' : 'bg-zinc-50 hover:bg-zinc-100 border border-zinc-200/80 text-zinc-700 hover:text-zinc-950' }}"
                    >
                        <x-ui.icon name="store" class="w-3.5 h-3.5 sm:w-4 sm:h-4 {{ $selectedBranch?->id === $b->id ? 'text-zinc-950' : 'text-zinc-400' }}" />
                        <span class="truncate">{{ $b->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form id="form-branch-settings" method="POST" action="{{ route('owner.schedule-settings.update', $selectedBranch) }}" class="space-y-4 sm:space-y-5 pb-6">
        @csrf
        @method('PUT')

        <!-- SECTION 1: Jam Operasional & Istirahat Mingguan (Hero Card, Responsif Mobile, Tablet & Desktop) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
                    <div>
                        <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm sm:text-base">
                            <span class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                                <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                            </span>
                            <span>Jam Operasional &amp; Istirahat: {{ $selectedBranch?->name }}</span>
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">Atur jadwal buka-tutup outlet dan jam istirahat harian</p>
                    </div>

                    <!-- Preset Cepat Jam Kerja -->
                    <div class="flex flex-wrap items-center gap-1.5 self-start sm:self-auto">
                        <button
                            type="button"
                            onclick="setAllBranchHours('10:00', '21:00', '12:00', '13:00')"
                            class="px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-extrabold transition cursor-pointer shadow-2xs"
                        >
                            Set 10:00 – 21:00
                        </button>
                        <button
                            type="button"
                            onclick="setAllBranchHours('09:00', '22:00', '12:00', '13:00')"
                            class="px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-extrabold transition cursor-pointer shadow-2xs"
                        >
                            Set 09:00 – 22:00
                        </button>
                        <button
                            type="button"
                            onclick="toggleSundayOff()"
                            class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/80 text-xs font-extrabold transition cursor-pointer shadow-2xs"
                        >
                            Liburkan Minggu
                        </button>
                    </div>
                </div>
            </x-slot:header>

            <!-- Feedback Toast saat aksi salin diterapkan -->
            <div id="copy-feedback-toast" class="hidden opacity-0 transition-opacity duration-300 text-xs font-bold text-emerald-800 bg-emerald-50 px-3.5 py-2 rounded-xl border border-emerald-200 mb-3 flex items-center gap-2">
                <x-ui.icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                <span id="copy-feedback-text">Jam operasional berhasil disalin.</span>
            </div>

            <!-- Table Header khusus Mode Desktop (Hanya muncul di Desktop >= lg agar tidak merusak Tablet) -->
            <div class="hidden lg:grid grid-cols-12 gap-3 px-4 py-2.5 rounded-xl bg-zinc-100/70 border border-zinc-200/60 text-[11px] font-black uppercase tracking-wider text-zinc-500 mb-2.5">
                <div class="col-span-3">Hari &amp; Status Outlet</div>
                <div class="col-span-4">Jam Operasional (Buka — Tutup)</div>
                <div class="col-span-4">Jam Istirahat (Mulai — Selesai)</div>
                <div class="col-span-1 text-right">Salin</div>
            </div>

            <div class="space-y-2.5">
                @foreach([1, 2, 3, 4, 5, 6, 0] as $dayNum)
                    @php
                        $dayData = $currentHours[$dayNum] ?? [];
                        $isOpen = isset($dayData['is_open']) ? (bool) $dayData['is_open'] : true;
                        $openTime = $dayData['open'] ?? '10:00';
                        $closeTime = $dayData['close'] ?? '21:00';
                        $breakStart = $dayData['break_start'] ?? '12:00';
                        $breakEnd = $dayData['break_end'] ?? '13:00';
                    @endphp
                    <div
                        id="branch-row-{{ $dayNum }}"
                        class="p-3 sm:p-3.5 lg:px-4 lg:py-3 rounded-2xl border transition-all duration-200 {{ $isOpen ? 'bg-white border-zinc-200/80 shadow-2xs hover:border-zinc-300' : 'bg-zinc-50/60 border-zinc-200/60 opacity-60' }}"
                    >
                        <div class="flex flex-col lg:grid lg:grid-cols-12 lg:items-center gap-2.5 sm:gap-3">
                            <!-- Baris 1 di Tablet/Mobile, Kolom 1 di Desktop (lg:col-span-3) -->
                            <div class="flex items-center justify-between lg:justify-start gap-3 lg:col-span-3">
                                <div class="flex items-center gap-2.5 sm:gap-3">
                                    <span class="font-black text-sm text-zinc-900 tracking-tight w-16 sm:w-20 shrink-0">{{ $daysName[$dayNum] }}</span>
                                    <label
                                        id="status-badge-{{ $dayNum }}"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-bold cursor-pointer transition select-none {{ $isOpen ? 'bg-emerald-50 border-emerald-200 text-emerald-700 shadow-2xs' : 'bg-zinc-100 border-zinc-200 text-zinc-500' }}"
                                    >
                                        <input
                                            type="hidden"
                                            name="opening_hours[{{ $dayNum }}][is_open]"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            id="branch-open-status-{{ $dayNum }}"
                                            name="opening_hours[{{ $dayNum }}][is_open]"
                                            value="1"
                                            @checked($isOpen)
                                            data-day="{{ $dayNum }}"
                                            onchange="toggleBranchDayRow(Number(this.dataset.day))"
                                            class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-3.5 h-3.5 cursor-pointer"
                                        />
                                        <span id="label-branch-status-{{ $dayNum }}" class="font-extrabold">{{ $isOpen ? 'Buka' : 'Tutup' }}</span>
                                    </label>
                                </div>

                                <!-- Tombol Salin hanya tampil di kanan atas pada mode Tablet & Mobile -->
                                <button
                                    type="button"
                                    data-day="{{ $dayNum }}"
                                    onclick="copyDayHoursToAll(Number(this.dataset.day))"
                                    title="Salin jam hari {{ $daysName[$dayNum] }} ke seluruh hari lain"
                                    class="lg:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-zinc-500 hover:text-amber-700 hover:bg-amber-50 transition cursor-pointer border border-zinc-200/80 bg-zinc-50/50"
                                >
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-400" />
                                    <span>Salin</span>
                                </button>
                            </div>

                            <!-- Baris 2 di Tablet (2 kolom seimbang / 1 kolom jika sidebar aktif), Kolom 2 & 3 di Desktop (lg:col-span-4 masing-masing) -->
                            <div class="branch-day-hours-grid grid grid-cols-1 sm:grid-cols-2 lg:contents gap-2 sm:gap-3">
                                <!-- Jam Operasional: lg:col-span-4 -->
                                <div class="lg:col-span-4">
                                    <div
                                        id="branch-hours-{{ $dayNum }}"
                                        class="flex items-center justify-between sm:justify-start gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 bg-zinc-50/70 shadow-2xs transition-all whitespace-nowrap flex-nowrap shrink-0 overflow-hidden {{ $isOpen ? '' : 'opacity-40 pointer-events-none' }}"
                                    >
                                        <div class="flex items-center gap-1.5 shrink-0 whitespace-nowrap">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                            <span class="text-[11px] font-bold text-zinc-600 shrink-0 whitespace-nowrap">Buka:</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0 flex-nowrap whitespace-nowrap">
                                            <input
                                                type="time"
                                                id="branch-open-{{ $dayNum }}"
                                                name="opening_hours[{{ $dayNum }}][open]"
                                                value="{{ substr($openTime, 0, 5) }}"
                                                @disabled(!$isOpen)
                                                class="w-[68px] sm:w-[72px] lg:w-20 px-1 py-1 text-xs font-black text-zinc-900 bg-white hover:bg-zinc-50 rounded-lg border border-zinc-200/90 shadow-2xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 num text-center cursor-pointer transition shrink-0"
                                            />
                                            <span class="text-zinc-300 font-black px-0.5 select-none shrink-0">—</span>
                                            <input
                                                type="time"
                                                id="branch-close-{{ $dayNum }}"
                                                name="opening_hours[{{ $dayNum }}][close]"
                                                value="{{ substr($closeTime, 0, 5) }}"
                                                @disabled(!$isOpen)
                                                class="w-[68px] sm:w-[72px] lg:w-20 px-1 py-1 text-xs font-black text-zinc-900 bg-white hover:bg-zinc-50 rounded-lg border border-zinc-200/90 shadow-2xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 num text-center cursor-pointer transition shrink-0"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Jam Istirahat Harian: lg:col-span-4 -->
                                <div class="lg:col-span-4">
                                    <div
                                        id="branch-break-{{ $dayNum }}"
                                        class="flex items-center justify-between sm:justify-start gap-1.5 px-3 py-1.5 rounded-xl border border-amber-200/90 bg-amber-50/50 shadow-2xs transition-all whitespace-nowrap flex-nowrap shrink-0 overflow-hidden {{ $isOpen ? '' : 'opacity-40 pointer-events-none' }}"
                                    >
                                        <div class="flex items-center gap-1.5 shrink-0 whitespace-nowrap">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                            <span class="text-[11px] font-bold text-amber-800 shrink-0 whitespace-nowrap">Istirahat:</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0 flex-nowrap whitespace-nowrap">
                                            <input
                                                type="time"
                                                id="branch-break-start-{{ $dayNum }}"
                                                name="opening_hours[{{ $dayNum }}][break_start]"
                                                value="{{ !empty($breakStart) ? substr($breakStart, 0, 5) : '12:00' }}"
                                                @disabled(!$isOpen)
                                                class="w-[68px] sm:w-[72px] lg:w-20 px-1 py-1 text-xs font-black text-zinc-900 bg-white hover:bg-zinc-50 rounded-lg border border-amber-200/90 shadow-2xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 num text-center cursor-pointer transition shrink-0"
                                            />
                                            <span class="text-amber-300 font-black px-0.5 select-none shrink-0">—</span>
                                            <input
                                                type="time"
                                                id="branch-break-end-{{ $dayNum }}"
                                                name="opening_hours[{{ $dayNum }}][break_end]"
                                                value="{{ !empty($breakEnd) ? substr($breakEnd, 0, 5) : '13:00' }}"
                                                @disabled(!$isOpen)
                                                class="w-[68px] sm:w-[72px] lg:w-20 px-1 py-1 text-xs font-black text-zinc-900 bg-white hover:bg-zinc-50 rounded-lg border border-amber-200/90 shadow-2xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 num text-center cursor-pointer transition shrink-0"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kolom 4 di Desktop (lg:col-span-1): Tombol Salin Desktop -->
                            <div class="hidden lg:block lg:col-span-1 text-right">
                                <button
                                    type="button"
                                    data-day="{{ $dayNum }}"
                                    onclick="copyDayHoursToAll(Number(this.dataset.day))"
                                    title="Salin jam hari {{ $daysName[$dayNum] }} ke seluruh hari lain"
                                    class="inline-flex items-center justify-center p-2 rounded-xl text-zinc-400 hover:text-amber-600 hover:bg-amber-50 transition cursor-pointer"
                                >
                                    <x-ui.icon name="file-text" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <!-- SECTION 2: Pengaturan Tambahan (2 Kolom Seimbang) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Kolom Kiri: Toleransi Absen & Booking + QR Check-in Cabang -->
            <div class="space-y-6">
                <!-- Kebijakan Keterlambatan & Booking Slot -->
                <x-ui.card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm">
                                <x-ui.icon name="clock" class="w-4 h-4 text-amber-600" />
                                <span>Toleransi Absen &amp; Interval Booking</span>
                            </h2>
                            <p class="text-xs text-zinc-500 mt-0.5">Aturan batas check-in barber dan jeda waktu booking pelanggan</p>
                        </div>
                    </x-slot:header>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                        <!-- Batas Toleransi Keterlambatan -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-1.5 min-w-0">
                                <label class="text-xs font-bold text-zinc-700 truncate">
                                    Toleransi Terlambat <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[10px] text-zinc-400 shrink-0 font-medium">Check-in</span>
                            </div>
                            <div class="flex rounded-xl border border-zinc-200 bg-white overflow-hidden shadow-2xs focus-within:ring-2 focus-within:ring-amber-500/40 focus-within:border-amber-500 transition-all">
                                <input
                                    type="number"
                                    name="late_tolerance_minutes"
                                    required
                                    min="0"
                                    max="120"
                                    value="{{ old('late_tolerance_minutes', $selectedBranch?->late_tolerance_minutes ?? 15) }}"
                                    class="w-full text-xs font-bold px-3.5 py-2.5 bg-transparent border-0 text-zinc-900 focus:outline-none focus:ring-0 num"
                                />
                                <span class="inline-flex items-center px-3.5 py-2.5 bg-zinc-100 text-xs font-bold text-zinc-600 border-l border-zinc-200 select-none shrink-0">
                                    Menit
                                </span>
                            </div>
                            <p class="text-[11px] text-zinc-400 leading-snug">
                                Batas check-in setelah jam shift sebelum dianggap terlambat.
                            </p>
                        </div>

                        <!-- Durasi Slot Interval Booking -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-1.5 min-w-0">
                                <label class="text-xs font-bold text-zinc-700 truncate">
                                    Interval Booking <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[10px] text-zinc-400 shrink-0 font-medium">Reservasi</span>
                            </div>
                            <div class="flex rounded-xl border border-zinc-200 bg-white overflow-hidden shadow-2xs focus-within:ring-2 focus-within:ring-amber-500/40 focus-within:border-amber-500 transition-all">
                                <input
                                    type="number"
                                    name="booking_slot_minutes"
                                    required
                                    min="10"
                                    max="180"
                                    value="{{ old('booking_slot_minutes', $selectedBranch?->booking_slot_minutes ?? 30) }}"
                                    class="w-full text-xs font-bold px-3.5 py-2.5 bg-transparent border-0 text-zinc-900 focus:outline-none focus:ring-0 num"
                                />
                                <span class="inline-flex items-center px-3.5 py-2.5 bg-zinc-100 text-xs font-bold text-zinc-600 border-l border-zinc-200 select-none shrink-0">
                                    Menit
                                </span>
                            </div>
                            <p class="text-[11px] text-zinc-400 leading-snug">
                                Jeda interval waktu antar jadwal booking pangkas.
                            </p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <!-- Kolom Kanan: Link Google Maps & Radius Absensi (Panjang Otomatis Mengikuti Sisi Kiri) -->
            <div class="h-full flex flex-col">
                <!-- Lokasi GPS Cabang via Link Google Maps & Setting Radius -->
                <x-ui.card class="h-full flex flex-col">
                    <x-slot:header>
                        <div>
                            <h2 class="font-bold flex items-center gap-2 text-zinc-900 text-sm">
                                <x-ui.icon name="map-pin" class="w-4 h-4 text-emerald-600" />
                                <span>Lokasi GPS &amp; Radius Absensi</span>
                            </h2>
                            <p class="text-xs text-zinc-500 mt-0.5">Link Google Maps dan batas toleransi jarak absen</p>
                        </div>
                    </x-slot:header>

                    <div class="flex-1 flex flex-col space-y-4">
                        <!-- 1. Input Link Google Maps & Status Koordinat -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="input-maps-url" class="text-xs font-bold text-zinc-700">Link Google Maps</label>
                                <a
                                    id="btn-preview-maps"
                                    href="{{ $selectedBranch?->lat && $selectedBranch?->lng ? 'https://www.google.com/maps?q=' . $selectedBranch->lat . ',' . $selectedBranch->lng : '#' }}"
                                    target="_blank"
                                    class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1 {{ $selectedBranch?->lat && $selectedBranch?->lng ? '' : 'hidden' }}"
                                >
                                    <span>Cek di Maps</span>
                                    <x-ui.icon name="external-link" class="w-3 h-3" />
                                </a>
                            </div>

                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                                    <x-ui.icon name="map-pin" class="w-4 h-4 text-emerald-600" />
                                </div>
                                <input
                                    type="text"
                                    id="input-maps-url"
                                    name="maps_url"
                                    placeholder="Tempel link Google Maps outlet..."
                                    value="{{ old('maps_url', $selectedBranch?->maps_url) }}"
                                    oninput="parseMapsUrlLive(this.value)"
                                    class="w-full text-xs font-medium pl-9 pr-3.5 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition shadow-2xs"
                                />
                            </div>

                            <!-- Info Bar Koordinat & Tombol Atur Manual -->
                            <div id="maps-status-info" class="flex items-center justify-between px-2.5 py-1.5 rounded-xl bg-zinc-50 border border-zinc-200/70 text-[11px]">
                                <span id="maps-coords-text" class="flex items-center gap-1.5 font-mono text-zinc-600 truncate">
                                    @if($selectedBranch?->lat && $selectedBranch?->lng)
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block shrink-0"></span>
                                        <span class="font-mono text-zinc-800 font-bold">Lat: {{ $selectedBranch->lat }}, Lng: {{ $selectedBranch->lng }}</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-zinc-300 inline-block shrink-0"></span>
                                        <span class="font-sans text-zinc-400">Koordinat terdeteksi otomatis dari link</span>
                                    @endif
                                </span>
                                <button type="button" onclick="toggleManualCoords()" class="text-[11px] font-bold text-amber-600 hover:text-amber-700 transition cursor-pointer shrink-0 ml-2">
                                    Atur manual
                                </button>
                            </div>

                            <!-- Input Manual Koordinat (Collapsible) -->
                            <div id="manual-coords-container" class="hidden mt-2 p-3 rounded-xl bg-zinc-50 border border-zinc-200 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Koordinat Pin Manual</span>
                                    <span class="text-[10px] text-zinc-400">Presisi hingga 7 desimal</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-zinc-600 mb-0.5">Latitude</label>
                                        <input
                                            type="text"
                                            id="manual-lat"
                                            value="{{ old('lat', $selectedBranch?->lat) }}"
                                            placeholder="-6.260718"
                                            oninput="syncManualCoords()"
                                            class="w-full text-xs font-mono font-bold px-2.5 py-1.5 bg-white rounded-lg border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-zinc-600 mb-0.5">Longitude</label>
                                        <input
                                            type="text"
                                            id="manual-lng"
                                            value="{{ old('lng', $selectedBranch?->lng) }}"
                                            placeholder="106.813305"
                                            oninput="syncManualCoords()"
                                            class="w-full text-xs font-mono font-bold px-2.5 py-1.5 bg-white rounded-lg border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Inputs untuk Lat dan Lng Terpilih -->
                        <input type="hidden" id="hidden-lat" name="lat" value="{{ old('lat', $selectedBranch?->lat) }}" />
                        <input type="hidden" id="hidden-lng" name="lng" value="{{ old('lng', $selectedBranch?->lng) }}" />

                        <!-- 2. Live Pratinjau Peta Interaktif (Google Maps Embed) -->
                        <div id="map-preview-wrapper" class="relative rounded-2xl overflow-hidden border border-zinc-200/90 shadow-2xs bg-zinc-100 flex-1 flex flex-col min-h-[300px] sm:min-h-[340px] lg:min-h-[360px]">
                            @php
                                $hasCoords = $selectedBranch?->lat && $selectedBranch?->lng;
                                $mapEmbedUrl = $hasCoords ? "https://maps.google.com/maps?q={$selectedBranch->lat},{$selectedBranch->lng}&hl=id&z=17&output=embed" : '';
                            @endphp

                            <div id="map-preview-placeholder" class="flex-1 py-16 px-4 flex flex-col items-center justify-center text-center space-y-2.5 {{ $hasCoords ? 'hidden' : '' }}">
                                <div class="w-12 h-12 rounded-2xl bg-white border border-zinc-200 text-zinc-400 grid place-items-center shadow-2xs">
                                    <x-ui.icon name="map-pin" class="w-6 h-6 text-emerald-500" />
                                </div>
                                <p class="text-xs font-bold text-zinc-800">Pratinjau Peta Lokasi Outlet</p>
                                <p class="text-[11px] text-zinc-400 max-w-xs leading-relaxed">
                                    Tempel link Google Maps di atas untuk melihat visual titik lokasi pin outlet langsung di peta.
                                </p>
                            </div>

                            <iframe
                                id="map-preview-iframe"
                                src="{{ $mapEmbedUrl }}"
                                class="w-full flex-1 min-h-[300px] sm:min-h-[340px] lg:min-h-[360px] border-0 {{ $hasCoords ? '' : 'hidden' }}"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                title="Pratinjau Lokasi Google Maps Cabang"
                            ></iframe>

                            <div id="map-badge-overlay" class="absolute top-3 right-3 z-10 pointer-events-none px-3 py-1.5 rounded-xl bg-zinc-950/85 backdrop-blur-xs text-white text-[11px] font-mono font-bold flex items-center gap-2 shadow-md border border-white/10 {{ $hasCoords ? '' : 'hidden' }}">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Titik Koordinat Terverifikasi</span>
                            </div>
                        </div>

                        <!-- 3. Setting Radius Absensi (Meter) -->
                        <div class="space-y-2 pt-3 border-t border-zinc-100">
                            <div class="flex items-center justify-between">
                                <label for="input-radius-meters" class="text-xs font-bold text-zinc-700">
                                    Radius Absensi <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[10px] text-zinc-400">Jarak geofence maksimal</span>
                            </div>

                            <!-- Input Group (Nilai & Satuan) + Pilihan Cepat Preset -->
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                                <div class="sm:col-span-5 flex rounded-xl border border-zinc-200 bg-white overflow-hidden shadow-2xs focus-within:ring-2 focus-within:ring-amber-500/40 focus-within:border-amber-500">
                                    <input
                                        type="number"
                                        id="input-radius-meters"
                                        name="radius_meters"
                                        required
                                        min="5"
                                        max="5000"
                                        value="{{ old('radius_meters', $selectedBranch?->radius_meters ?? 50) }}"
                                        oninput="updateRadiusButtonsHighlight(this.value)"
                                        class="w-full text-xs font-bold px-3 py-2 bg-transparent border-0 text-zinc-900 focus:outline-none focus:ring-0 num"
                                    />
                                    <span class="inline-flex items-center px-3 py-2 bg-zinc-100 text-xs font-bold text-zinc-600 border-l border-zinc-200 select-none shrink-0">
                                        Meter
                                    </span>
                                </div>

                                <div class="sm:col-span-7 grid grid-cols-4 gap-1.5">
                                    @foreach([25 => '25m', 50 => '50m', 100 => '100m', 200 => '200m'] as $meters => $label)
                                        <button
                                            type="button"
                                            onclick="setRadius(Number(this.dataset.radius))"
                                            data-radius="{{ $meters }}"
                                            class="preset-radius-btn py-2 px-1 rounded-xl border text-xs font-bold text-center transition cursor-pointer"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- 4. Catatan Geofence Footer -->
                        <div class="pt-3 border-t border-zinc-100 flex items-start gap-2.5 text-zinc-500 text-[11px] leading-relaxed">
                            <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                            <span>Pastikan titik koordinat Google Maps tepat di lokasi fisik outlet agar sistem validasi batas jarak (radius geofence) absensi barber berjalan presisi.</span>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>

        <!-- SECTION 3: Tombol Simpan Pengaturan (Bar Bawah Jelas & Menonjol di Semua Mode) -->
        <div id="bottom-save-bar" class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="store" class="w-5 h-5 text-amber-600" />
                </div>
                <div class="min-w-0">
                    <h4 class="text-xs sm:text-sm font-extrabold text-zinc-900 leading-snug">Simpan Perubahan Pengaturan Cabang</h4>
                    <p class="text-[11px] text-zinc-500 mt-0.5">Jam operasional, toleransi check-in, dan radius absensi akan langsung diperbarui.</p>
                </div>
            </div>
            <x-ui.button type="submit" form="form-branch-settings" size="md" variant="primary" class="w-full md:w-auto justify-center shadow-xs font-black px-6 py-2.5 shrink-0 cursor-pointer">
                <x-ui.icon name="check" class="w-4 h-4" />
                <span>Simpan Pengaturan Cabang</span>
            </x-ui.button>
        </div>
    </form>

    <script>
        function toggleBranchDayRow(day) {
            const checkbox = document.getElementById(`branch-open-status-${day}`);
            const isChecked = checkbox ? checkbox.checked : true;

            const rowEl = document.getElementById(`branch-row-${day}`);
            const badgeEl = document.getElementById(`status-badge-${day}`);
            const labelEl = document.getElementById(`label-branch-status-${day}`);
            const hoursEl = document.getElementById(`branch-hours-${day}`);
            const breakEl = document.getElementById(`branch-break-${day}`);

            const openInput = document.getElementById(`branch-open-${day}`);
            const closeInput = document.getElementById(`branch-close-${day}`);
            const breakStartInput = document.getElementById(`branch-break-start-${day}`);
            const breakEndInput = document.getElementById(`branch-break-end-${day}`);

            if (isChecked) {
                if (openInput) openInput.disabled = false;
                if (closeInput) closeInput.disabled = false;
                if (breakStartInput) breakStartInput.disabled = false;
                if (breakEndInput) breakEndInput.disabled = false;

                if (rowEl) {
                    rowEl.classList.remove('opacity-60', 'bg-zinc-50/60', 'border-zinc-200/60');
                    rowEl.classList.add('bg-white', 'border-zinc-200/80', 'shadow-2xs');
                }
                if (hoursEl) {
                    hoursEl.classList.remove('opacity-40', 'pointer-events-none');
                }
                if (breakEl) {
                    breakEl.classList.remove('opacity-40', 'pointer-events-none');
                }
                if (badgeEl) {
                    badgeEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-bold cursor-pointer transition select-none bg-emerald-50 border-emerald-200 text-emerald-700 shadow-2xs';
                }
                if (labelEl) {
                    labelEl.textContent = 'Buka';
                }
            } else {
                if (openInput) openInput.disabled = true;
                if (closeInput) closeInput.disabled = true;
                if (breakStartInput) breakStartInput.disabled = true;
                if (breakEndInput) breakEndInput.disabled = true;

                if (rowEl) {
                    rowEl.classList.add('opacity-60', 'bg-zinc-50/60', 'border-zinc-200/60');
                    rowEl.classList.remove('bg-white', 'border-zinc-200/80', 'shadow-2xs');
                }
                if (hoursEl) {
                    hoursEl.classList.add('opacity-40', 'pointer-events-none');
                }
                if (breakEl) {
                    breakEl.classList.add('opacity-40', 'pointer-events-none');
                }
                if (badgeEl) {
                    badgeEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-bold cursor-pointer transition select-none bg-zinc-100 border-zinc-200 text-zinc-500';
                }
                if (labelEl) {
                    labelEl.textContent = 'Tutup';
                }
            }
        }

        function setAllBranchHours(openTime, closeTime, breakStart = '12:00', breakEnd = '13:00') {
            const days = [1, 2, 3, 4, 5, 6, 0];
            days.forEach(day => {
                const openInput = document.getElementById(`branch-open-${day}`);
                const closeInput = document.getElementById(`branch-close-${day}`);
                const breakStartInput = document.getElementById(`branch-break-start-${day}`);
                const breakEndInput = document.getElementById(`branch-break-end-${day}`);
                const statusCheckbox = document.getElementById(`branch-open-status-${day}`);

                if (openInput) openInput.value = openTime;
                if (closeInput) closeInput.value = closeTime;
                if (breakStartInput) breakStartInput.value = breakStart;
                if (breakEndInput) breakEndInput.value = breakEnd;
                if (statusCheckbox) {
                    statusCheckbox.checked = true;
                    toggleBranchDayRow(day);
                }
            });

            showCopyFeedback(`Jam operasional seluruh hari diset: ${openTime} – ${closeTime}`);
        }

        function toggleSundayOff() {
            const sundayCheckbox = document.getElementById('branch-open-status-0');
            if (sundayCheckbox) {
                sundayCheckbox.checked = !sundayCheckbox.checked;
                toggleBranchDayRow(0);
                showCopyFeedback(sundayCheckbox.checked ? 'Hari Minggu diatur Buka operasional.' : 'Hari Minggu diatur Libur / Tutup.');
            }
        }

        function copyDayHoursToAll(sourceDay) {
            const daysName = {
                1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'
            };
            const openInput = document.getElementById(`branch-open-${sourceDay}`);
            const closeInput = document.getElementById(`branch-close-${sourceDay}`);
            const breakStartInput = document.getElementById(`branch-break-start-${sourceDay}`);
            const breakEndInput = document.getElementById(`branch-break-end-${sourceDay}`);

            if (!openInput || !closeInput) return;

            const openTime = openInput.value;
            const closeTime = closeInput.value;
            const breakStart = breakStartInput ? breakStartInput.value : '12:00';
            const breakEnd = breakEndInput ? breakEndInput.value : '13:00';

            const days = [1, 2, 3, 4, 5, 6, 0];
            days.forEach(day => {
                if (day === sourceDay) return;
                const oIn = document.getElementById(`branch-open-${day}`);
                const cIn = document.getElementById(`branch-close-${day}`);
                const bsIn = document.getElementById(`branch-break-start-${day}`);
                const beIn = document.getElementById(`branch-break-end-${day}`);
                if (oIn) oIn.value = openTime;
                if (cIn) cIn.value = closeTime;
                if (bsIn) bsIn.value = breakStart;
                if (beIn) beIn.value = breakEnd;
            });

            showCopyFeedback(`Jam operasional ${daysName[sourceDay]} (${openTime}–${closeTime}) berhasil disalin ke seluruh hari.`);
        }

        function showCopyFeedback(msg) {
            const toast = document.getElementById('copy-feedback-toast');
            const text = document.getElementById('copy-feedback-text');
            if (toast && text) {
                text.textContent = msg;
                toast.classList.remove('hidden');
                requestAnimationFrame(() => toast.classList.remove('opacity-0'));
                setTimeout(() => {
                    toast.classList.add('opacity-0');
                    setTimeout(() => toast.classList.add('hidden'), 300);
                }, 3000);
            }
        }

        function setRadius(meters) {
            const input = document.getElementById('input-radius-meters');
            if (input) {
                input.value = meters;
                updateRadiusButtonsHighlight(meters);
            }
        }

        function updateRadiusButtonsHighlight(val) {
            const currentVal = parseInt(val, 10);
            const buttons = document.querySelectorAll('.preset-radius-btn');
            buttons.forEach(btn => {
                const r = parseInt(btn.getAttribute('data-radius'), 10);
                if (r === currentVal) {
                    btn.className = 'preset-radius-btn py-1.5 px-2 rounded-lg border-2 border-amber-500 bg-amber-500/10 text-amber-900 font-extrabold text-xs text-center transition cursor-pointer shadow-2xs';
                } else {
                    btn.className = 'preset-radius-btn py-1.5 px-2 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-bold text-xs text-center transition cursor-pointer';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const initialRadius = document.getElementById('input-radius-meters')?.value || 50;
            updateRadiusButtonsHighlight(initialRadius);
        });

        function toggleManualCoords() {
            const container = document.getElementById('manual-coords-container');
            if (container) {
                container.classList.toggle('hidden');
            }
        }

        function updateMapPreviewIframe(lat, lng) {
            const iframe = document.getElementById('map-preview-iframe');
            const placeholder = document.getElementById('map-preview-placeholder');
            const badge = document.getElementById('map-badge-overlay');

            if (lat && lng) {
                if (iframe) {
                    iframe.src = `https://maps.google.com/maps?q=${lat},${lng}&hl=id&z=17&output=embed`;
                    iframe.classList.remove('hidden');
                }
                if (placeholder) placeholder.classList.add('hidden');
                if (badge) badge.classList.remove('hidden');
            } else {
                if (iframe) {
                    iframe.classList.add('hidden');
                    iframe.src = '';
                }
                if (placeholder) placeholder.classList.remove('hidden');
                if (badge) badge.classList.add('hidden');
            }
        }

        function syncManualCoords() {
            const mLat = document.getElementById('manual-lat')?.value.trim();
            const mLng = document.getElementById('manual-lng')?.value.trim();
            const hiddenLat = document.getElementById('hidden-lat');
            const hiddenLng = document.getElementById('hidden-lng');
            const coordsText = document.getElementById('maps-coords-text');
            const btnPreview = document.getElementById('btn-preview-maps');

            if (mLat && mLng) {
                if (hiddenLat) hiddenLat.value = mLat;
                if (hiddenLng) hiddenLng.value = mLng;
                if (coordsText) {
                    coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500 inline-block shrink-0"></span><span class="font-mono text-zinc-800 font-bold">Lat: ${mLat}, Lng: ${mLng}</span>`;
                }
                if (btnPreview) {
                    btnPreview.href = `https://www.google.com/maps?q=${mLat},${mLng}`;
                    btnPreview.classList.remove('hidden');
                }
                updateMapPreviewIframe(mLat, mLng);
            }
        }

        let resolveTimeout = null;

        function resetCoordinatesDisplay() {
            const hiddenLat = document.getElementById('hidden-lat');
            const hiddenLng = document.getElementById('hidden-lng');
            const manualLat = document.getElementById('manual-lat');
            const manualLng = document.getElementById('manual-lng');
            const coordsText = document.getElementById('maps-coords-text');
            const btnPreview = document.getElementById('btn-preview-maps');

            if (hiddenLat) hiddenLat.value = '';
            if (hiddenLng) hiddenLng.value = '';
            if (manualLat) manualLat.value = '';
            if (manualLng) manualLng.value = '';

            if (coordsText) {
                coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-zinc-300 inline-block shrink-0"></span><span class="font-sans text-zinc-400">Koordinat terdeteksi otomatis dari link</span>`;
            }
            if (btnPreview) {
                btnPreview.classList.add('hidden');
            }
            updateMapPreviewIframe(null, null);
        }

        // Ekstraksi presisi tinggi koordinat dari link Google Maps secara live
        function parseMapsUrlLive(url) {
            clearTimeout(resolveTimeout);

            if (!url || typeof url !== 'string' || url.trim() === '') {
                resetCoordinatesDisplay();
                return;
            }
            url = url.trim();

            let lat = null;
            let lng = null;

            let decoded = url;
            try { decoded = decodeURIComponent(url); } catch (e) {}

            // 1. PRIORITAS TERTINGGI: Exact Pin Data Protobuf (!3dlat!4dlng atau !4dlng!3dlat)
            let match = decoded.match(/!3d(-?\d{1,2}\.\d+).*?!4d(-?\d{1,3}\.\d+)/);
            if (match) {
                lat = match[1];
                lng = match[2];
            }

            if (!lat) {
                match = decoded.match(/!4d(-?\d{1,3}\.\d+).*?!3d(-?\d{1,2}\.\d+)/);
                if (match) {
                    lat = match[2];
                    lng = match[1];
                }
            }

            // 2. PRIORITAS 2: Path /place/lat,lng
            if (!lat) {
                match = decoded.match(/\/place\/(-?\d{1,2}\.\d+)(?:,|%2C|\+)(-?\d{1,3}\.\d+)/i);
                if (match) {
                    lat = match[1];
                    lng = match[2];
                }
            }

            // 3. PRIORITAS 3: Query param q=lat,lng atau query=lat,lng atau ll=lat,lng atau daddr=lat,lng
            if (!lat) {
                match = decoded.match(/[?&](?:q|query|ll|daddr|destination)=(-?\d{1,2}\.\d+)(?:,|%2C|\+)(-?\d{1,3}\.\d+)/i);
                if (match) {
                    lat = match[1];
                    lng = match[2];
                }
            }

            // 4. PRIORITAS 4: Parameter static map markers / center
            if (!lat) {
                match = decoded.match(/(?:markers|center)=(?:color:[^%|&]+%7C)?(-?\d{1,2}\.\d+)(?:,|%2C)(-?\d{1,3}\.\d+)/i);
                if (match) {
                    lat = match[1];
                    lng = match[2];
                }
            }

            // 5. PRIORITAS 5: Raw coordinates: "-6.260718, 106.813305" atau "geo:-6.260718,106.813305"
            if (!lat) {
                match = decoded.match(/(?:geo:)?(-?\d{1,2}\.\d{4,})\s*,\s*(-?\d{1,3}\.\d{4,})/i);
                if (match) {
                    lat = match[1];
                    lng = match[2];
                }
            }

            // 6. PRIORITAS TERAKHIR (FALLBACK): Camera viewport center (@lat,lng)
            if (!lat) {
                match = decoded.match(/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/);
                if (match) {
                    lat = match[1];
                    lng = match[2];
                }
            }

            const coordsText = document.getElementById('maps-coords-text');
            const btnPreview = document.getElementById('btn-preview-maps');
            const hiddenLat = document.getElementById('hidden-lat');
            const hiddenLng = document.getElementById('hidden-lng');
            const manualLat = document.getElementById('manual-lat');
            const manualLng = document.getElementById('manual-lng');

            if (lat && lng) {
                hiddenLat.value = lat;
                hiddenLng.value = lng;
                if (manualLat) manualLat.value = lat;
                if (manualLng) manualLng.value = lng;

                if (coordsText) {
                    coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500 inline-block shrink-0"></span><span class="font-mono text-zinc-800 font-bold">Lat: ${lat}, Lng: ${lng}</span>`;
                }

                if (btnPreview) {
                    btnPreview.href = `https://www.google.com/maps?q=${lat},${lng}`;
                    btnPreview.classList.remove('hidden');
                }
                updateMapPreviewIframe(lat, lng);
            } else if (url.length > 8) {
                // Bersihkan nilai lama agar tidak tertahan koordinat dari link sebelumnya
                hiddenLat.value = '';
                hiddenLng.value = '';
                if (manualLat) manualLat.value = '';
                if (manualLng) manualLng.value = '';

                if (coordsText) {
                    coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse inline-block shrink-0"></span><span class="font-sans text-amber-800 text-[11px]">Memverifikasi link baru...</span>`;
                }
                if (btnPreview) {
                    btnPreview.classList.add('hidden');
                }

                // Panggil server live untuk menyelesaikan shortlink (maps.app.goo.gl)
                resolveTimeout = setTimeout(() => {
                    const resolveUrl = '{{ route('owner.resolve-maps-coords') }}';
                    fetch(`${resolveUrl}?url=${encodeURIComponent(url)}`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.success && res.lat && res.lng) {
                                hiddenLat.value = res.lat;
                                hiddenLng.value = res.lng;
                                if (manualLat) manualLat.value = res.lat;
                                if (manualLng) manualLng.value = res.lng;

                                if (coordsText) {
                                    coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500 inline-block shrink-0"></span><span class="font-mono text-zinc-800 font-bold">Lat: ${res.lat}, Lng: ${res.lng}</span>`;
                                }
                                if (btnPreview) {
                                    btnPreview.href = `https://www.google.com/maps?q=${res.lat},${res.lng}`;
                                    btnPreview.classList.remove('hidden');
                                }
                                updateMapPreviewIframe(res.lat, res.lng);
                            } else {
                                if (coordsText) {
                                    coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-500 inline-block shrink-0"></span><span class="font-sans text-amber-800 text-[11px]">Link terdeteksi, koordinat pin akan diverifikasi server saat simpan</span>`;
                                }
                            }
                        })
                        .catch(() => {
                            if (coordsText) {
                                coordsText.innerHTML = `<span class="w-2 h-2 rounded-full bg-zinc-300 inline-block shrink-0"></span><span class="font-sans text-zinc-400 text-[11px]">Koordinat pin akan diproses saat Anda simpan</span>`;
                            }
                        });
                }, 250);
            }
        }
    </script>

    <style>
        /* Optimasi Tampilan Mode Tablet Khusus Pengaturan Jam Operasional saat Sidebar Aktif */
        @media (min-width: 768px) and (max-width: 1023.98px) {
            html.tablet-sidebar-open .branch-day-hours-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }
            html.tablet-sidebar-open #bottom-save-bar {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            html.tablet-sidebar-open #bottom-save-bar button {
                width: 100% !important;
            }
        }
    </style>
</x-layouts.app>
