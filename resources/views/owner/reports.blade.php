<x-layouts.app title="Laporan & Evaluasi Bisnis" subtitle="Rekap transaksi, ulasan & evaluasi kepuasan pelanggan cabang, analitik operasional, dan export Excel (.xlsx)" role="owner">
    @php
        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $barberFilterOptions = $barbers->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->display_name ?? $b->user->name,
            'sublabel' => $b->branch?->name ?? 'Semua Cabang',
        ])->all();

        $statusFilterOptions = [
            ['value' => 'paid', 'label' => 'Lunas (Paid)'],
            ['value' => 'refunded', 'label' => 'Direfund (Refunded)'],
        ];

        $periodFilterOptions = [
            ['value' => 'today', 'label' => 'Hari Ini'],
            ['value' => '7days', 'label' => '7 Hari Terakhir'],
            ['value' => 'this_month', 'label' => 'Bulan Ini'],
            ['value' => 'last_month', 'label' => 'Bulan Lalu'],
            ['value' => 'custom', 'label' => 'Kustom Tanggal...'],
        ];

        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $hasAnalyticsPro = $tenant?->hasFeature('analytics_pro') ?? true;
        $plan = $tenant?->plan();
        $planName = $plan?->name ?? 'Starter';
    @endphp

    @if(session('status'))
        <div class="mb-5 p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Filter & Export Excel: Bersih & Rapi di Mobile, Tablet (Sidebar Aktif), dan Desktop -->
    <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-zinc-200/80 shadow-2xs mb-5">
        <form id="reports-filter-form" method="GET" action="{{ route('owner.reports') }}" class="space-y-3">
            <!-- Pilihan Filter: Responsif 1 kolom (Mobile), 2 kolom (Tablet), 4 kolom (Desktop) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                <!-- Filter Periode -->
                <div>
                    <x-ui.select
                        name="period"
                        placeholder="Semua Periode"
                        icon="calendar"
                        :options="$periodFilterOptions"
                        :value="request('period')"
                        class="w-full"
                    />
                </div>

                <!-- Filter Cabang -->
                <div>
                    <x-ui.select
                        name="branch_id"
                        placeholder="Semua Cabang"
                        icon="store"
                        :options="$branchFilterOptions"
                        :value="request('branch_id')"
                        search-placeholder="Cari cabang..."
                        class="w-full"
                        auto-submit
                    />
                </div>

                <!-- Filter Barber -->
                <div>
                    <x-ui.select
                        name="barber_id"
                        placeholder="Semua Barber"
                        icon="scissors"
                        :options="$barberFilterOptions"
                        :value="request('barber_id')"
                        search-placeholder="Cari nama barber..."
                        class="w-full"
                        auto-submit
                    />
                </div>

                <!-- Filter Status -->
                <div>
                    <x-ui.select
                        name="payment_status"
                        placeholder="Semua Status"
                        icon="filter"
                        :options="$statusFilterOptions"
                        :value="request('payment_status')"
                        class="w-full"
                        auto-submit
                    />
                </div>
            </div>

            <!-- Rentang Tanggal Kustom (Muncul jika Kustom Tanggal Dipilih) -->
            @php
                $isCustomPeriod = request('period') === 'custom' || (request()->filled('date_from') && !in_array(request('period'), ['today', '7days', 'this_month', 'last_month'], true));
            @endphp
            <div id="report-custom-date-range" class="{{ $isCustomPeriod ? 'flex' : 'hidden' }} flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-2.5 border-t border-zinc-100">
                <span class="text-xs font-bold text-zinc-500 shrink-0 flex items-center gap-1.5">
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400" />
                    <span>Rentang Tanggal:</span>
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 flex-1">
                    <div>
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1 sm:hidden">Mulai Tanggal:</span>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ request('date_from', $dateFrom) }}"
                            class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-2xs"
                        />
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1 sm:hidden">Sampai Tanggal:</span>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ request('date_to', $dateTo) }}"
                            class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-2xs"
                        />
                    </div>
                </div>
            </div>

            <!-- Baris Tombol Aksi: Sejajar & Rapi di Semua Resolusi Layar -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 pt-3 border-t border-zinc-100">
                <div class="flex items-center gap-2 flex-1 sm:flex-initial">
                    <x-ui.button type="submit" size="sm" variant="secondary" class="flex-1 sm:flex-initial cursor-pointer">
                        <x-ui.icon name="filter" class="w-3.5 h-3.5" />
                        <span>Filter</span>
                    </x-ui.button>

                    @if(request()->hasAny(['period', 'date_from', 'date_to', 'branch_id', 'barber_id', 'payment_status']))
                        <a
                            href="{{ route('owner.reports') }}"
                            class="inline-flex items-center justify-center gap-1 text-xs font-extrabold text-rose-600 hover:text-rose-700 px-2.5 py-1.5 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                            <span>Reset</span>
                        </a>
                    @endif
                </div>

                <a
                    href="{{ route('owner.reports.export', request()->query()) }}"
                    class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 shadow-2xs transition shrink-0 cursor-pointer"
                    title="Export Data Transaksi Sesuai Filter ke Excel (.xlsx)"
                >
                    <x-ui.icon name="download" class="w-4 h-4 text-emerald-400" />
                    <span>Export Excel (.xlsx)</span>
                </a>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- FITUR PRO: ANALITIK & GRAFIK OPERASIONAL BARBERSHOP                     -->
    <!-- (Non-keuangan: efektivitas harian/bulanan, kinerja staff, cara pesan)    -->
    <!-- ========================================================================= -->
    @if($hasAnalyticsPro)
        <div class="relative mb-6">
            <div class="space-y-4">
                <!-- Header Ringkas Fitur Pro: Outline / Border Sesuai Warna Tema Amber -->
            <div class="p-4 rounded-2xl bg-zinc-900 text-white border border-amber-500/40 shadow-sm shadow-amber-500/5 flex flex-col xl:flex-row xl:items-center justify-between gap-3.5">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase bg-amber-500/20 text-amber-400 border border-amber-500/40">
                        <x-ui.icon name="sparkles" class="w-3 h-3 text-amber-400" />
                        <span>PRO</span>
                    </span>
                    <h2 class="text-sm sm:text-base font-extrabold text-white">Efektivitas &amp; Kinerja Barbershop</h2>
                </div>
                <p class="text-xs text-zinc-400">
                    Ringkasan jam ramai, produktivitas barber, dan cara pesan pelanggan.
                </p>
            </div>

            <!-- Ringkasan Cepat: Outline kotak metrik diselaraskan dengan warna tema -->
            <div class="grid grid-cols-2 gap-2.5 w-full xl:w-auto shrink-0">
                <div class="p-2.5 sm:px-4 sm:py-2 rounded-xl bg-zinc-850/90 border border-amber-500/30 text-left sm:text-center xl:text-right">
                    <span class="text-zinc-400 text-[10px] block font-medium">Paling Ramai</span>
                    <span class="font-extrabold text-amber-400 text-xs sm:text-sm num block truncate">{{ $analytics['hourly']['peak_hour'] }}</span>
                </div>
                <div class="p-2.5 sm:px-4 sm:py-2 rounded-xl bg-zinc-850/90 border border-amber-500/30 text-left sm:text-center xl:text-right">
                    <span class="text-zinc-400 text-[10px] block font-medium">Layanan Sukses</span>
                    <span class="font-extrabold text-emerald-400 text-xs sm:text-sm num block">{{ $analytics['monthly']['overall_rate'] }}%</span>
                </div>
            </div>
        </div>

        <!-- Baris 1: Jam Ramai Harian & Tren Bulanan -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- 1. Jam Ramai Kunjungan (Harian) -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2 w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-700">
                                <x-ui.icon name="clock" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Jam Ramai Kunjungan</h3>
                                <p class="text-[11px] text-zinc-400">Kepadatan pelanggan tiap jam operasional</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800">
                            Puncak: {{ $analytics['hourly']['peak_hour'] }}
                        </span>
                    </div>
                </x-slot:header>

                <div class="space-y-3 pt-1">
                    <!-- Grafik Batang Jam Sibuk -->
                    <div class="h-36 flex items-end justify-between gap-1 sm:gap-2 px-1 pt-4 border-b border-zinc-100">
                        @foreach($analytics['hourly']['list'] as $item)
                            @php
                                $heightPct = max(8, $item['percentage']);
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end">
                                <!-- Tooltip Sederhana -->
                                <div class="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-zinc-900 text-white text-[10px] font-bold py-0.5 px-1.5 rounded shadow whitespace-nowrap pointer-events-none z-10">
                                    {{ $item['label'] }}: {{ $item['count'] }} orang
                                </div>

                                <!-- Angka di atas batang -->
                                <span class="text-[9px] font-bold {{ $item['is_peak'] ? 'text-amber-700 font-black' : 'text-zinc-400' }} num">
                                    {{ $item['count'] > 0 ? $item['count'] : '' }}
                                </span>

                                <!-- Batang Grafik -->
                                <div
                                    class="w-full rounded-t-sm transition-all duration-300 {{ $item['is_peak'] ? 'bg-amber-500 ring-2 ring-amber-400/50' : ($item['count'] > 0 ? 'bg-amber-400/70 hover:bg-amber-500' : 'bg-zinc-100 hover:bg-zinc-200') }}"
                                    @style(['height: ' . $heightPct . '%'])
                                ></div>

                                <!-- Label Jam Singkat -->
                                <span class="text-[9px] font-semibold text-zinc-400 num truncate w-full text-center mt-1">
                                    {{ substr($item['label'], 0, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <!-- Keterangan Singkat -->
                    <div class="flex items-center justify-between text-xs text-zinc-500">
                        <span>Jam Buka: 10:00 - 21:00</span>
                        <span class="text-zinc-700 font-semibold">Paling ramai di jam {{ $analytics['hourly']['peak_hour'] }}</span>
                    </div>
                </div>
            </x-ui.card>

            <!-- 2. Tren Pangkas Bulanan -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2 w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-700">
                                <x-ui.icon name="calendar" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Tren Pangkas Bulanan</h3>
                                <p class="text-[11px] text-zinc-400">Total selesai vs batal (6 bulan terakhir)</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                            {{ $analytics['monthly']['overall_rate'] }}% Selesai
                        </span>
                    </div>
                </x-slot:header>

                <div class="space-y-3 pt-1">
                    <!-- Progress Bar Tiap Bulan -->
                    <div class="space-y-2">
                        @foreach($analytics['monthly']['trend'] as $m)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-zinc-700">{{ $m['full_month'] }}</span>
                                    <div class="flex items-center gap-2 num text-[11px]">
                                        <span class="font-bold text-zinc-900">{{ $m['completed'] }} selesai</span>
                                        @if($m['cancelled'] > 0)
                                            <span class="text-rose-500 font-semibold">{{ $m['cancelled'] }} batal</span>
                                        @endif
                                        <span class="text-zinc-400">({{ $m['rate'] }}%)</span>
                                    </div>
                                </div>
                                <div class="w-full h-1.5 rounded-full bg-zinc-100 overflow-hidden flex">
                                    <div class="h-full bg-amber-500 transition-all duration-300" @style(['width: ' . $m['rate'] . '%'])></div>
                                    @if(100 - $m['rate'] > 0)
                                        <div class="h-full bg-rose-400" @style(['width: ' . (100 - $m['rate']) . '%'])></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Keterangan Singkat -->
                    <div class="flex items-center justify-between text-xs text-zinc-500 pt-1 border-t border-zinc-100">
                        <span>Total 6 Bulan: <b class="text-zinc-900 num">{{ $analytics['monthly']['total_completed'] }} selesai</b></span>
                        <span class="text-emerald-700 font-semibold">Tingkat batal sangat rendah</span>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Baris 2: Cara Masuk Pelanggan & Layanan Terlaris (Kanan Kiri 2 Kolom di Atas Performa Kerja) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- 2a. Cara Masuk Pelanggan -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2 w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-700">
                                <x-ui.icon name="ticket" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Cara Masuk Pelanggan</h3>
                                <p class="text-[11px] text-zinc-400">Antrean langsung vs reservasi online</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700">
                            Antrean
                        </span>
                    </div>
                </x-slot:header>

                <div class="space-y-3 pt-1">
                    <!-- Bar Terbagi -->
                    <div class="w-full h-2.5 rounded-full bg-zinc-100 overflow-hidden flex shadow-inner">
                        <div class="h-full bg-amber-500 transition-all duration-300" @style(['width: ' . $analytics['arrival']['walk_in_pct'] . '%'])></div>
                        <div class="h-full bg-indigo-500 transition-all duration-300" @style(['width: ' . $analytics['arrival']['booking_pct'] . '%'])></div>
                    </div>

                    <!-- 2 Kolom Keterangan Kanan Kiri -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/50">
                            <div class="flex items-center gap-1.5 mb-1">
                                <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                <span class="text-[11px] font-bold text-zinc-700">Langsung Datang</span>
                            </div>
                            <span class="font-black text-amber-800 text-base num">{{ $analytics['arrival']['walk_in_pct'] }}%</span>
                            <span class="text-[11px] text-zinc-500 block num mt-0.5">({{ $analytics['arrival']['walk_in_count'] }} orang)</span>
                        </div>

                        <div class="p-2.5 rounded-xl bg-indigo-50/70 border border-indigo-200/50">
                            <div class="flex items-center gap-1.5 mb-1">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                <span class="text-[11px] font-bold text-zinc-700">Booking Online</span>
                            </div>
                            <span class="font-black text-indigo-800 text-base num">{{ $analytics['arrival']['booking_pct'] }}%</span>
                            <span class="text-[11px] text-zinc-500 block num mt-0.5">({{ $analytics['arrival']['booking_count'] }} orang)</span>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- 2b. Layanan Terlaris -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2 w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-zinc-100 text-zinc-700">
                                <x-ui.icon name="scissors" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Layanan Terlaris</h3>
                                <p class="text-[11px] text-zinc-400">Paling banyak dipesan pelanggan</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700">
                            Paling Diminati
                        </span>
                    </div>
                </x-slot:header>

                <div class="space-y-2.5 pt-1">
                    @foreach($analytics['top_services'] as $svc)
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-zinc-800 truncate">{{ $svc['name'] }}</span>
                                <span class="font-black text-zinc-900 num shrink-0">{{ $svc['percentage'] }}%</span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-zinc-100 overflow-hidden">
                                <div class="h-full bg-zinc-900 rounded-full" @style(['width: ' . $svc['percentage'] . '%'])></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <!-- Baris 3: Performa & Kinerja Barber (Full Width di Bawah) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-blue-500/10 text-blue-700">
                            <x-ui.icon name="users" class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-xs sm:text-sm text-zinc-900">Performa &amp; Kinerja Barber</h3>
                            <p class="text-[11px] text-zinc-400">Jumlah pangkas selesai, kecepatan durasi, dan request pelanggan</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700">
                        {{ count($analytics['staff']['list']) }} Barber Aktif
                    </span>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                @forelse($analytics['staff']['list'] as $index => $barber)
                    <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/70 hover:bg-zinc-100/60 transition flex flex-col justify-between">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-6 h-6 rounded-md bg-zinc-900 text-amber-400 font-extrabold text-[11px] grid place-items-center shrink-0">
                                    {{ $index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-xs text-zinc-900 truncate">{{ $barber['name'] }}</h4>
                                    <p class="text-[10px] text-zinc-400 truncate">{{ $barber['branch'] }}</p>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-xs font-black text-zinc-900 num">{{ $barber['completed_count'] }}</span>
                                <span class="text-[10px] text-zinc-400 block">Pangkas</span>
                            </div>
                        </div>

                        <!-- Info Metrik 3 Kolom: Rating, Durasi, dan Permintaan Khusus -->
                        <div class="grid grid-cols-3 gap-1 text-center py-1.5 mb-2 rounded-lg bg-white border border-zinc-100 text-[11px]">
                            <div>
                                <span class="text-zinc-400 text-[10px] block font-medium">Rating</span>
                                <div class="inline-flex items-center justify-center gap-0.5 font-black text-amber-600 num">
                                    <x-ui.icon name="star" class="w-3 h-3 fill-amber-400 text-amber-500" />
                                    <span>{{ $barber['rating'] }}</span>
                                </div>
                            </div>
                            <div class="border-l border-zinc-100">
                                <span class="text-zinc-400 text-[10px] block font-medium">Durasi</span>
                                <span class="font-bold text-zinc-700 num">~{{ $barber['avg_duration'] }} mnt</span>
                            </div>
                            <div class="border-l border-zinc-100">
                                <span class="text-zinc-400 text-[10px] block font-medium">Diminta</span>
                                <span class="font-bold text-amber-700 num">{{ $barber['request_rate'] }}%</span>
                            </div>
                        </div>

                        <!-- Bar Kontribusi -->
                        <div class="space-y-1">
                            <div class="w-full h-1.5 rounded-full bg-zinc-200 overflow-hidden">
                                <div class="h-full bg-amber-500 rounded-full transition-all duration-300" @style(['width: ' . $barber['bar_width'] . '%'])></div>
                            </div>
                            <div class="flex justify-between items-center text-[10px] text-zinc-400">
                                <span>Porsi {{ $barber['share_rate'] }}% outlet</span>
                                <span class="num">{{ $barber['completed_count'] }} kepala</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full text-xs text-zinc-400 italic text-center py-4">Belum ada data kinerja barber.</p>
                @endforelse
            </div>
        </x-ui.card>
            </div>
        </div>
    @else
        <!-- Banner Informasi Ringkas: Fitur Pro Analitik & Kinerja Barbershop -->
        <div class="mb-5 p-3 sm:p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-3 shadow-2xs">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0">
                    <x-ui.icon name="lock" class="w-4 h-4 text-amber-700" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-extrabold text-zinc-900">Analitik &amp; Kinerja Barbershop</span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300">
                            Fitur Pro
                        </span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-0.5 leading-snug">
                        Pantau jam ramai operasional, tren bulanan, dan performa staf dengan upgrade ke paket Pro.
                    </p>
                </div>
            </div>
            <a
                href="{{ route('owner.subscription') }}"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold shrink-0 transition shadow-2xs self-start sm:self-center"
            >
                <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                <span>Upgrade ke Paket Pro</span>
            </a>
        </div>
    @endif
    <!-- ========================================================================= -->
    <!-- END FITUR PRO ANALITIK                                                    -->
    <!-- ========================================================================= -->

    <!-- ========================================================================= -->
    <!-- ULASAN & EVALUASI PELANGGAN (BAHAN EVALUASI CABANG & BISNIS)             -->
    <!-- Membantu Owner memantau mutu layanan, keramahan barber & kritik cabang   -->
    <!-- ========================================================================= -->
    <div id="customer-reviews" class="scroll-mt-20 mb-6">
        <x-ui.card data-pagination-container="customer-reviews">
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 w-full report-review-header">
                    <!-- Judul & Badge Ulasan (Mobile Friendly) -->
                    <div class="flex items-start sm:items-center gap-2.5 sm:gap-3 min-w-0">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-500/15 text-amber-600 flex items-center justify-center shrink-0 mt-0.5 sm:mt-0 shadow-inner">
                            <x-ui.icon name="star" class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h2 class="font-extrabold text-sm sm:text-base text-zinc-900 tracking-tight leading-snug">
                                    Ulasan &amp; Evaluasi Pelanggan
                                </h2>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 shrink-0">
                                    {{ number_format($reviewMetrics?->total_reviews ?? 0) }} Ulasan
                                </span>
                            </div>
                            <p class="text-[11px] sm:text-xs text-zinc-500 mt-0.5 leading-relaxed">
                                Feedback nyata pelanggan untuk mengevaluasi kualitas layanan cabang &amp; barber
                            </p>
                        </div>
                    </div>

                    <!-- Indikator Rata-rata Skor Bintang: Full Width di Mobile, Auto di Tablet/Desktop -->
                    @php
                        $avgRating = round((float) ($reviewMetrics?->avg_rating ?? 0), 1);
                        $totalRev = (int) ($reviewMetrics?->total_reviews ?? 0);
                        $posCount = (int) ($reviewMetrics?->positive_count ?? 0);
                        $evalCount = (int) ($reviewMetrics?->evaluation_count ?? 0);
                        $satisfactionPct = $totalRev > 0 ? round(($posCount / $totalRev) * 100) : 0;
                    @endphp
                    <div class="w-full sm:w-auto flex items-center justify-between sm:justify-end gap-3 bg-amber-50/80 border border-amber-200/90 px-3.5 py-2 rounded-xl shadow-2xs report-review-badge shrink-0">
                        <div class="text-left sm:text-right">
                            <span class="block text-[10px] font-bold text-amber-900 uppercase tracking-wider">Skor Kepuasan</span>
                            <span class="text-xs font-black text-amber-950">{{ $satisfactionPct }}% Puas</span>
                        </div>
                        <div class="h-7 w-px bg-amber-300/80"></div>
                        <div class="flex items-center gap-1.5">
                            <x-ui.icon name="star" class="w-5 h-5 text-amber-500 fill-amber-500" />
                            <span class="text-base sm:text-lg font-black text-zinc-950 num">{{ $avgRating > 0 ? number_format($avgRating, 1) : '-' }}</span>
                            <span class="text-[10px] text-zinc-400 font-bold">/ 5.0</span>
                        </div>
                    </div>
                </div>
            </x-slot:header>

            <!-- Ringkasan Metrik Kepuasan (4 Card Grid Responsif) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5 pb-5 border-b border-zinc-100 report-review-metrics">
                <!-- 1. Total Ulasan -->
                <div class="p-3 sm:p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/70">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">Total Ulasan Masuk</span>
                    <span class="text-lg sm:text-xl font-black text-zinc-900 num">{{ number_format($totalRev) }}</span>
                    <span class="text-[11px] text-zinc-500 block mt-0.5">Semua riwayat rating</span>
                </div>

                <!-- 2. Rata-rata Bintang -->
                <div class="p-3 sm:p-3.5 rounded-xl bg-amber-50/50 border border-amber-200/60">
                    <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider block mb-1">Rating Rata-rata</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg sm:text-xl font-black text-amber-950 num">{{ $avgRating > 0 ? number_format($avgRating, 1) : '-' }}</span>
                        <span class="text-xs font-bold text-amber-600">/ 5.0</span>
                    </div>
                    <span class="text-[11px] text-amber-800/80 block mt-0.5">Indeks mutu layanan</span>
                </div>

                <!-- 3. Kepuasan Positif -->
                <div class="p-3 sm:p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-200/60">
                    <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block mb-1">Puas (Bintang 4-5)</span>
                    <span class="text-lg sm:text-xl font-black text-emerald-700 num">{{ number_format($posCount) }}</span>
                    <span class="text-[11px] text-emerald-700/80 block mt-0.5">{{ $satisfactionPct }}% pelanggan senang</span>
                </div>

                <!-- 4. Bahan Evaluasi (Bintang 1-3) -->
                <div class="p-3 sm:p-3.5 rounded-xl bg-rose-50/50 border border-rose-200/60">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wider block mb-1">Bahan Evaluasi (1-3)</span>
                        @if($evalCount > 0)
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        @endif
                    </div>
                    <span class="text-lg sm:text-xl font-black text-rose-700 num">{{ number_format($evalCount) }}</span>
                    <span class="text-[11px] text-rose-700/80 block mt-0.5">Ulasan perlu perbaikan</span>
                </div>
            </div>

            <!-- Filter Cepat Rating (Pills Wrap Alami: Tanpa Scroll di Mobile & Tablet) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 pb-3 border-b border-zinc-100">
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-xs">
                    <span class="text-[11px] font-bold text-zinc-400 mr-0.5">Filter:</span>
                    <a
                        href="{{ request()->fullUrlWithQuery(['review_rating' => null, 'reviews_page' => null]) }}#customer-reviews"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition {{ !$selectedReviewRating ? 'bg-zinc-900 text-white shadow-2xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}"
                    >
                        Semua Rating
                    </a>
                    <a
                        href="{{ request()->fullUrlWithQuery(['review_rating' => 5, 'reviews_page' => null]) }}#customer-reviews"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition {{ $selectedReviewRating === 5 ? 'bg-amber-500 text-zinc-950 font-black shadow-2xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}"
                    >
                        <x-ui.icon name="star" class="w-3 h-3 text-amber-500 {{ $selectedReviewRating === 5 ? 'text-zinc-950' : '' }}" />
                        <span>5 Bintang ({{ $reviewMetrics?->count_5 ?? 0 }})</span>
                    </a>
                    <a
                        href="{{ request()->fullUrlWithQuery(['review_rating' => 4, 'reviews_page' => null]) }}#customer-reviews"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition {{ $selectedReviewRating === 4 ? 'bg-amber-500 text-zinc-950 font-black shadow-2xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}"
                    >
                        <x-ui.icon name="star" class="w-3 h-3 text-amber-500 {{ $selectedReviewRating === 4 ? 'text-zinc-950' : '' }}" />
                        <span>4 Bintang ({{ $reviewMetrics?->count_4 ?? 0 }})</span>
                    </a>
                    <a
                        href="{{ request()->fullUrlWithQuery(['review_rating' => 3, 'reviews_page' => null]) }}#customer-reviews"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition {{ $selectedReviewRating === 3 ? 'bg-amber-500 text-zinc-950 font-black shadow-2xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}"
                    >
                        <x-ui.icon name="star" class="w-3 h-3 text-amber-500 {{ $selectedReviewRating === 3 ? 'text-zinc-950' : '' }}" />
                        <span>3 Bintang ({{ $reviewMetrics?->count_3 ?? 0 }})</span>
                    </a>
                    <a
                        href="{{ request()->fullUrlWithQuery(['review_rating' => 1, 'reviews_page' => null]) }}#customer-reviews"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition {{ in_array($selectedReviewRating, [1, 2], true) ? 'bg-rose-600 text-white shadow-2xs' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                        title="Evaluasi Rating 1-2 Bintang"
                    >
                        <x-ui.icon name="alert-circle" class="w-3 h-3 text-rose-500 {{ in_array($selectedReviewRating, [1, 2], true) ? 'text-white' : '' }}" />
                        <span>Kritik/Evaluasi ({{ ($reviewMetrics?->count_1 ?? 0) + ($reviewMetrics?->count_2 ?? 0) }})</span>
                    </a>
                </div>

                <div class="text-xs text-zinc-500 font-semibold self-start sm:self-center shrink-0">
                    Menampilkan <span class="font-black text-zinc-900 num">{{ $reviews->count() }}</span> ulasan
                </div>
            </div>

            <!-- Daftar Kartu Ulasan Pelanggan -->
            <div class="space-y-3.5 pt-2">
                @forelse($reviews as $rev)
                    @php
                        $isLowRating = $rev->rating <= 3;
                        $customerName = $rev->customerProfile?->name ?? 'Pelanggan Walk-in';
                        $customerPhone = $rev->customerProfile?->phone;
                        $barberName = $rev->barber?->display_name ?? ($rev->barber?->user?->name ?? 'Barber Tidak Terdaftar');
                        $branchName = $rev->branch?->name ?? 'Semua Cabang';
                    @endphp
                    <div class="rounded-2xl border {{ $isLowRating ? 'border-amber-300/80 bg-amber-50/20' : 'border-zinc-200/80 bg-white' }} p-4 sm:p-5 shadow-2xs transition space-y-3">
                        <!-- Baris Atas: Profil Pelanggan, Cabang, Barber, dan Bintang -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 report-review-item-header">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl {{ $isLowRating ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700' }} font-black text-sm flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr($customerName, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <h4 class="font-bold text-xs sm:text-sm text-zinc-900">{{ $customerName }}</h4>
                                        @if($customerPhone)
                                            <span class="text-[11px] text-zinc-400 font-mono">({{ $customerPhone }})</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 mt-0.5">
                                        <span class="inline-flex items-center gap-1 text-zinc-600 font-semibold">
                                            <x-ui.icon name="store" class="w-3 h-3 text-zinc-400" />
                                            <span>{{ $branchName }}</span>
                                        </span>
                                        <span class="text-zinc-300">&bull;</span>
                                        <span class="inline-flex items-center gap-1 text-zinc-600 font-semibold">
                                            <x-ui.icon name="scissors" class="w-3 h-3 text-zinc-400" />
                                            <span>{{ $barberName }}</span>
                                        </span>
                                        @if($rev->order && $rev->order->number)
                                            <span class="text-zinc-300">&bull;</span>
                                            <a href="{{ route('invoices.show', $rev->order->number) }}" class="font-mono text-[10px] text-amber-700 hover:underline font-bold inline-flex items-center gap-0.5" title="Buka Faktur Transaksi">
                                                <span>#{{ $rev->order->number }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Rating Stars & Waktu -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 self-stretch sm:self-auto pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-100 report-review-rating-row">
                                <div class="flex items-center gap-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <x-ui.icon
                                            name="star"
                                            class="w-4 h-4 {{ $i <= $rev->rating ? 'text-amber-500 fill-amber-500' : 'text-zinc-200' }}"
                                        />
                                    @endfor
                                    <span class="ml-1 text-xs font-black text-zinc-900 num">{{ $rev->rating }}.0</span>
                                </div>
                                <span class="text-[11px] text-zinc-400 shrink-0">
                                    {{ $rev->created_at?->diffForHumans() ?? '-' }}
                                </span>
                            </div>
                        </div>

                        <!-- Isi Ulasan / Komentar Customer -->
                        <div class="p-3 sm:p-3.5 rounded-xl {{ $isLowRating ? 'bg-amber-100/50 border border-amber-200/70' : 'bg-zinc-50/80 border border-zinc-100' }} text-xs sm:text-sm text-zinc-800 leading-relaxed font-medium">
                            @if(!empty($rev->comment))
                                <p class="italic">&ldquo;{{ $rev->comment }}&rdquo;</p>
                            @else
                                <p class="text-zinc-400 italic">Pelanggan tidak memberikan catatan tertulis (hanya penilaian bintang).</p>
                            @endif

                            @if($isLowRating)
                                <div class="mt-2 pt-2 border-t border-amber-200/60 flex items-center gap-1.5 text-[11px] font-bold text-amber-900">
                                    <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                    <span>Catatan Evaluasi: Perhatikan kualitas pengerjaan barber atau pelayanan cabang terkait ulasan ini.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Balasan Apresiasi / Tanggapan Owner (Jika Ada) -->
                        @if($rev->replied_text)
                            <div class="pl-3.5 border-l-2 border-amber-500 py-1 space-y-1 bg-zinc-50/60 rounded-r-xl p-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-black text-zinc-900">Balasan Pemilik Barbershop</span>
                                    <span class="text-[10px] text-zinc-400">({{ $rev->replied_at?->format('d M Y H:i') }})</span>
                                </div>
                                <p class="text-xs text-zinc-600 leading-relaxed">
                                    {{ $rev->replied_text }}
                                </p>
                            </div>
                        @endif

                        <!-- Footer Aksi: Balas Ulasan & Toggle Visibilitas Publik -->
                        <div class="space-y-2.5 pt-2 border-t border-zinc-100">
                            <div class="flex flex-wrap items-center justify-between gap-2.5 text-xs">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- Status Visibilitas Publik -->
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold {{ $rev->is_published ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                        <x-ui.icon name="{{ $rev->is_published ? 'check' : 'eye-off' }}" class="w-3 h-3" />
                                        <span>{{ $rev->is_published ? 'Tampil di Publik' : 'Disembunyikan' }}</span>
                                    </span>

                                    <form method="POST" action="{{ route('owner.reports.reviews.toggle-visibility', $rev->id) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-zinc-600 hover:text-zinc-900 bg-zinc-100 hover:bg-zinc-200 border border-zinc-200/80 transition cursor-pointer"
                                            title="{{ $rev->is_published ? 'Klik untuk sembunyikan ulasan ini dari booking publik' : 'Klik untuk tampilkan ulasan ini di booking publik' }}"
                                        >
                                            {{ $rev->is_published ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                    </form>
                                </div>

                                <!-- Tombol Buka Form Balas Ulasan -->
                                <button
                                    type="button"
                                    onclick="document.getElementById('reply-box-{{ $rev->id }}').classList.toggle('hidden')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 text-xs font-bold transition cursor-pointer"
                                >
                                    <x-ui.icon name="message-square" class="w-3.5 h-3.5" />
                                    <span>{{ $rev->replied_text ? 'Ubah Balasan' : 'Beri Balasan' }}</span>
                                </button>
                            </div>

                            <!-- Form Balas Ulasan (Full Width Dropdown/Collapsible) -->
                            <div id="reply-box-{{ $rev->id }}" class="hidden p-3.5 sm:p-4 rounded-xl bg-zinc-50 border border-zinc-200 w-full space-y-2">
                                <form method="POST" action="{{ route('owner.reports.reviews.reply', $rev->id) }}">
                                    @csrf
                                    <label class="block text-[11px] font-bold text-zinc-700 mb-1">Tulis tanggapan untuk pelanggan:</label>
                                    <textarea
                                        name="reply"
                                        rows="2"
                                        required
                                        placeholder="Tulis balasan apresiasi atau tanggapan ramah kepada pelanggan..."
                                        class="w-full text-xs font-medium p-2.5 rounded-lg bg-white border border-zinc-200 text-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                    >{{ old('reply', $rev->replied_text) }}</textarea>
                                    <div class="flex items-center justify-end gap-2 mt-2">
                                        <button
                                            type="button"
                                            onclick="document.getElementById('reply-box-{{ $rev->id }}').classList.add('hidden')"
                                            class="px-3 py-1.5 rounded-lg text-zinc-500 hover:text-zinc-800 text-xs font-bold transition cursor-pointer"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            type="submit"
                                            class="px-3.5 py-1.5 rounded-lg bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 transition cursor-pointer"
                                        >
                                            Simpan Balasan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center space-y-2 border border-dashed border-zinc-200 rounded-2xl">
                        <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-400 grid place-items-center mx-auto">
                            <x-ui.icon name="star" class="w-5 h-5 text-zinc-400" />
                        </div>
                        <p class="text-xs font-bold text-zinc-600">Belum ada ulasan pelanggan untuk filter ini.</p>
                        <p class="text-[11px] text-zinc-400 max-w-sm mx-auto">
                            Ulasan pelanggan akan muncul di sini setelah pelanggan menyelesaikan booking dan memberikan penilaian bintang atau komentar.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination Reviews -->
            @if($reviews->hasPages())
                <div class="pt-4 border-t border-zinc-100">
                    {{ $reviews->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    <!-- Riwayat Transaksi & Refund -->
    <x-ui.card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-2 w-full">
                <h2 class="font-bold text-sm sm:text-base">Riwayat Transaksi &amp; Refund</h2>
                <span class="text-xs text-zinc-400 font-semibold">{{ $orders->count() }} Transaksi</span>
            </div>
        </x-slot:header>

        <!-- Mode Desktop: Tampilan Tabel Bersih (Layar Lebar xl ke Atas) -->
        <div class="hidden xl:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-zinc-400 text-left border-b border-zinc-100 whitespace-nowrap">
                    <tr>
                        <th class="py-2.5 px-3">Invoice</th>
                        <th class="py-2.5 px-3">Cabang</th>
                        <th class="py-2.5 px-3">Customer</th>
                        <th class="py-2.5 px-3">Barber</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Total</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 whitespace-nowrap">
                    @forelse($orders as $order)
                        <tr>
                            <td class="py-3 px-3 font-bold num">
                                <a href="{{ route('invoices.show', $order->number) }}" class="text-amber-700 hover:underline inline-flex items-center gap-1">
                                    <span>{{ $order->number }}</span>
                                    <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                </a>
                            </td>
                            <td class="py-3 px-3">{{ $order->branch?->name }}</td>
                            <td class="py-3 px-3 font-medium text-zinc-900">{{ $order->customerProfile?->name ?? 'Walk-in' }}</td>
                            <td class="py-3 px-3 text-zinc-700">{{ $order->barber?->display_name ?? '-' }}</td>
                            <td class="py-3 px-3"><x-ui.badge :status="$order->payment_status" /></td>
                            <td class="py-3 px-3 text-right font-extrabold num">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-3 text-right">
                                @if($order->payment_status === \App\Enums\PaymentStatus::Paid)
                                    <button
                                        type="button"
                                        data-refund-url="{{ route('owner.orders.refund', $order) }}"
                                        data-order-number="{{ $order->number }}"
                                        data-order-total="Rp{{ number_format($order->grand_total, 0, ',', '.') }}"
                                        onclick="openRefundModal(this.dataset.refundUrl, this.dataset.orderNumber, this.dataset.orderTotal)"
                                        class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold border border-rose-200 transition cursor-pointer"
                                    >
                                        Refund
                                    </button>
                                @else
                                    <span class="text-xs text-zinc-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-xs text-zinc-400 italic">
                                Belum ada riwayat transaksi yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mode Mobile & Tablet (Termasuk Saat Sidebar Aktif): Kartu 1 Kolom (Mobile & Tablet Sidebar Aktif) & 2 Kolom (Tablet Lebar) -->
        <div class="xl:hidden grid grid-cols-1 sm:grid-cols-2 gap-3 report-orders-grid">
            @forelse($orders as $order)
                <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80 space-y-2.5 flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('invoices.show', $order->number) }}" class="font-bold text-xs num text-amber-700 hover:underline inline-flex items-center gap-1">
                                <span>{{ $order->number }}</span>
                                <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                            </a>
                            <x-ui.badge :status="$order->payment_status" />
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs text-zinc-600 pt-1">
                            <div>
                                <span class="text-[10px] text-zinc-400 block font-semibold">Customer</span>
                                <span class="font-bold text-zinc-800 truncate block">{{ $order->customerProfile?->name ?? 'Walk-in' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-400 block font-semibold">Barber</span>
                                <span class="font-bold text-zinc-800 truncate block">{{ $order->barber?->display_name ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-400 block font-semibold">Cabang</span>
                                <span class="text-zinc-700 truncate block">{{ $order->branch?->name }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-400 block font-semibold">Total</span>
                                <span class="font-black text-zinc-900 num">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    @if($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        <div class="pt-2 border-t border-zinc-200/70 flex justify-end">
                            <button
                                type="button"
                                data-refund-url="{{ route('owner.orders.refund', $order) }}"
                                data-order-number="{{ $order->number }}"
                                data-order-total="Rp{{ number_format($order->grand_total, 0, ',', '.') }}"
                                onclick="openRefundModal(this.dataset.refundUrl, this.dataset.orderNumber, this.dataset.orderTotal)"
                                class="w-full inline-flex items-center justify-center px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold border border-rose-200 transition cursor-pointer"
                            >
                                <span>Refund Transaksi</span>
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="col-span-full py-6 text-center text-xs text-zinc-400 italic">Belum ada riwayat transaksi.</p>
            @endforelse
        </div>
    </x-ui.card>

    <!-- Popup Kecil Konfirmasi Refund -->
    <dialog id="modal-refund-confirm" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-sm border border-zinc-200">
        <div class="p-5 text-center space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 grid place-items-center mx-auto border border-rose-200/60">
                <x-ui.icon name="alert-triangle" class="w-6 h-6" />
            </div>

            <div class="space-y-1">
                <h3 class="font-extrabold text-base text-zinc-900">Konfirmasi Refund</h3>
                <p class="text-xs text-zinc-500 leading-relaxed">
                    Yakin ingin melakukan refund untuk transaksi ini?
                </p>
                <div class="mt-2.5 p-3 rounded-xl bg-zinc-50 border border-zinc-200/70 text-xs text-left space-y-1.5">
                    <div class="flex justify-between items-center">
                        <span class="text-zinc-400">Invoice:</span>
                        <span id="refund-modal-invoice" class="font-bold text-zinc-900 num">-</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-zinc-400">Total:</span>
                        <span id="refund-modal-total" class="font-black text-rose-600 num">-</span>
                    </div>
                    <p class="text-[10px] text-zinc-400 pt-1.5 border-t border-zinc-200/60 leading-normal">
                        * Komisi barber terkait akan otomatis dibatalkan/dibalikkan.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
                <x-ui.button
                    type="button"
                    variant="ghost"
                    size="md"
                    onclick="document.getElementById('modal-refund-confirm').close()"
                >
                    Batal
                </x-ui.button>

                <form id="refund-modal-form" method="POST" action="">
                    @csrf
                    <input type="hidden" name="reason" value="Refund diajukan owner di menu Laporan" />
                    <x-ui.button type="submit" variant="danger" size="md" class="w-full">
                        Ya, Refund
                    </x-ui.button>
                </form>
            </div>
        </div>
    </dialog>

    <script>
        function openRefundModal(actionUrl, invoiceNumber, totalFormatted) {
            const modal = document.getElementById('modal-refund-confirm');
            const form = document.getElementById('refund-modal-form');
            const invoiceElem = document.getElementById('refund-modal-invoice');
            const totalElem = document.getElementById('refund-modal-total');

            if (!modal || !form) return;

            form.action = actionUrl;
            if (invoiceElem) invoiceElem.textContent = invoiceNumber;
            if (totalElem) totalElem.textContent = totalFormatted;

            modal.showModal();
        }

        document.getElementById('modal-refund-confirm')?.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) {
                this.close();
            }
        });

        // Interaksi Filter Periode & Kustom Rentang Tanggal
        document.addEventListener('ui-select:change', function(e) {
            if (e.target && e.target.matches('[data-input-name="period"]')) {
                const val = e.detail ? e.detail.value : '';
                const rangeDiv = document.getElementById('report-custom-date-range');
                const form = document.getElementById('reports-filter-form');
                if (val === 'custom') {
                    if (rangeDiv) {
                        rangeDiv.classList.remove('hidden');
                        rangeDiv.classList.add('flex');
                    }
                } else {
                    if (rangeDiv) {
                        rangeDiv.classList.add('hidden');
                        rangeDiv.classList.remove('flex');
                    }
                    if (form) {
                        form.submit();
                    }
                }
            }
        });
    </script>
</x-layouts.app>
