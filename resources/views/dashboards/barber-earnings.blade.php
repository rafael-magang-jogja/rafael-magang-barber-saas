<x-layouts.barber
    :title="'Penghasilan — ' . ($barberMembership?->display_name ?? 'Barber')"
    :barber-membership="$barberMembership"
>
    <!-- Header Ringkasan Penghasilan & Cabang -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-black text-zinc-900 tracking-tight">Penghasilan &amp; Komisi</h1>
            <p class="text-[11px] text-zinc-400">
                {{ $tenant?->name ?? 'Barbershop' }} &bull; {{ $branch?->name ?? 'Cabang' }}
            </p>
        </div>
        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
            Fitur {{ strtoupper($planCode) }}
        </span>
    </div>

    <!-- FILTER PERIODE WAKTU (MENGGUNAKAN DESIGN DROPDOWN RESMI SYSTEM) -->
    @php
        $periodOptions = [
            [
                'value' => 'this_month',
                'label' => 'Bulan Ini (' . now()->translatedFormat('F Y') . ')',
                'sublabel' => 'Periode berjalan',
            ],
            [
                'value' => 'today',
                'label' => 'Hari Ini (' . now()->translatedFormat('d M Y') . ')',
                'sublabel' => 'Pelanggan hari ini',
            ],
            [
                'value' => 'yesterday',
                'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M Y') . ')',
                'sublabel' => 'Rekap kemarin',
            ],
            [
                'value' => '7days',
                'label' => '7 Hari Terakhir',
                'sublabel' => 'Satu minggu',
            ],
            [
                'value' => 'last_month',
                'label' => 'Bulan Lalu (' . now()->subMonth()->translatedFormat('F Y') . ')',
                'sublabel' => 'Bulan sebelumnya',
            ],
            [
                'value' => 'custom',
                'label' => 'Kustom Rentang Tanggal...',
                'sublabel' => 'Tentukan tanggal bebas',
            ],
        ];
    @endphp

    <div class="p-3.5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-2.5">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-500" />
                <span>Filter Periode</span>
            </span>
            <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/70 truncate max-w-[180px]">
                {{ $periodLabel }}
            </span>
        </div>

        <form method="GET" action="{{ route('barber.earnings') }}" id="filter-earnings-form" class="space-y-2.5">
            <!-- Custom Styled UI Select Component (Desain Dropdown Resmi) -->
            <x-ui.select
                name="period"
                icon="calendar"
                :options="$periodOptions"
                :value="$selectedPeriod"
                size="sm"
                :searchable="false"
                class="w-full"
            />

            <!-- Rentang Tanggal Kustom (Hanya Muncul Jika Kustom Dipilih) -->
            <div id="custom-date-box" class="{{ $selectedPeriod === 'custom' ? '' : 'hidden' }} pt-2.5 space-y-2 border-t border-zinc-100">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="date_from" class="block font-bold text-zinc-600 text-[10px] mb-1">Dari Tanggal</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ $dateFrom }}"
                            class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono"
                        />
                    </div>
                    <div>
                        <label for="date_to" class="block font-bold text-zinc-600 text-[10px] mb-1">Sampai Tanggal</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ $dateTo }}"
                            class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono"
                        />
                    </div>
                </div>
                <button
                    type="submit"
                    class="w-full py-2 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer"
                >
                    <x-ui.icon name="check" class="w-3.5 h-3.5 text-amber-400" />
                    <span>Tampilkan Data Tanggal</span>
                </button>
            </div>
        </form>
    </div>

    @php
        $hasBaseSalary = ($baseSalary ?? 0) > 0;
        $isMonthlyPeriod = in_array($selectedPeriod, ['this_month', 'last_month'], true);
        $periodTip = $periodTip ?? 0;
        $totalDisplayAmount = ($hasBaseSalary && $isMonthlyPeriod) 
            ? ($periodCommission + $periodTip + $baseSalary) 
            : ($periodCommission + $periodTip);
    @endphp

    <!-- 1. KARTU HERO TOTAL PENDAPATAN / KOMISI PERIODE TERPILIH -->
    <div class="p-4 sm:p-5 rounded-3xl bg-zinc-950 text-white border border-zinc-800 shadow-md relative overflow-hidden space-y-3.5">
        <!-- Background accent subtle glow -->
        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-amber-500/10 blur-2xl pointer-events-none"></div>

        <div class="flex items-center justify-between relative z-10">
            <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                <x-ui.icon name="wallet" class="w-3.5 h-3.5 text-amber-400" />
                @if($hasBaseSalary && $isMonthlyPeriod)
                    <span>Total Estimasi Pendapatan ({{ $periodLabel }})</span>
                @elseif($hasBaseSalary)
                    <span>Total Komisi Pangkas ({{ $periodLabel }})</span>
                @else
                    <span>Total Komisi ({{ $periodLabel }})</span>
                @endif
            </span>
            <span class="text-[10px] font-semibold text-zinc-400">
                {{ $periodCompleted }} pangkas
            </span>
        </div>

        <div class="relative z-10">
            <p class="text-3xl sm:text-4xl font-black text-white tracking-tight num">
                Rp{{ number_format($totalDisplayAmount, 0, ',', '.') }}
            </p>
            <p class="text-[11px] text-zinc-400 mt-1">
                @if($hasBaseSalary && $isMonthlyPeriod)
                    Estimasi gabungan gaji pokok bulanan, perolehan komisi, dan tip pelanggan pada periode {{ strtolower($periodLabel) }}.
                @else
                    Total perolehan komisi dan tip pelanggan pada periode {{ strtolower($periodLabel) }}.
                @endif
            </p>
        </div>

        @if($hasBaseSalary)
            <!-- Rincian Komponen Gaji Pokok, Komisi & Tip jika Memiliki Gaji Pokok -->
            <div class="pt-3 border-t border-zinc-800/80 space-y-2 text-xs relative z-10">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-zinc-400">
                        <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                        <span>Gaji Pokok:</span>
                    </span>
                    <span class="font-extrabold text-amber-400 num">Rp{{ number_format($baseSalary, 0, ',', '.') }} <span class="text-[10px] font-normal text-zinc-400">/ bln</span></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-zinc-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <span>Komisi Layanan ({{ $periodLabel }}):</span>
                    </span>
                    <span class="font-bold text-emerald-400 num">Rp{{ number_format($periodCommission, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-zinc-400">
                        <span class="w-2 h-2 rounded-full bg-amber-300 shrink-0"></span>
                        <span>Tip Pelanggan ({{ $periodLabel }}):</span>
                    </span>
                    <span class="font-bold text-amber-300 num">{{ $periodTip > 0 ? '+Rp' . number_format($periodTip, 0, ',', '.') : 'Rp0' }}</span>
                </div>
                @if(! $isMonthlyPeriod)
                    <div class="flex items-center justify-between text-[11px] text-zinc-400 pt-1.5 border-t border-zinc-900">
                        <span>Total Komisi Bulan Ini:</span>
                        <span class="font-bold text-zinc-200 num">Rp{{ number_format($commissionThisMonth, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @else
            <!-- Rincian Komponen Komisi & Tip jika Tanpa Gaji Pokok -->
            <div class="pt-3 border-t border-zinc-800/80 space-y-2 text-xs relative z-10">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-zinc-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <span>Komisi Layanan:</span>
                    </span>
                    <span class="font-bold text-emerald-400 num">Rp{{ number_format($periodCommission, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-zinc-400">
                        <span class="w-2 h-2 rounded-full bg-amber-300 shrink-0"></span>
                        <span>Tip Pelanggan:</span>
                    </span>
                    <span class="font-bold text-amber-300 num">{{ $periodTip > 0 ? '+Rp' . number_format($periodTip, 0, ',', '.') : 'Rp0' }}</span>
                </div>
                <div class="pt-1.5 border-t border-zinc-900 flex items-center justify-between text-[11px] text-zinc-400">
                    <span>Komisi Bulan Ini:</span>
                    <span class="font-bold text-zinc-200 num">Rp{{ number_format($commissionThisMonth, 0, ',', '.') }}</span>
                </div>
            </div>
        @endif
    </div>

    <!-- 2. KARTU RINGKASAN AKTIVITAS PANGKAS (FULL LEBAR) -->
    <div class="p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs flex items-center justify-between gap-3">
        <div class="flex items-center gap-3.5 min-w-0">
            <span class="w-11 h-11 rounded-2xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                <x-ui.icon name="scissors" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-extrabold text-xs sm:text-sm text-zinc-950">Aktivitas Pangkas Rambut</h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200/60 shrink-0">
                        Bulan {{ now()->translatedFormat('F') }}
                    </span>
                </div>
                <p class="text-[11px] sm:text-xs text-zinc-500 mt-1 flex items-center gap-1.5 flex-wrap">
                    <span>Hari ini: <strong class="text-emerald-700 font-bold num">{{ $completedToday }} pelanggan</strong></span>
                    <span class="text-zinc-300">·</span>
                    <span>Periode ini: <strong class="text-zinc-800 font-bold num">{{ $periodCompleted }} pangkas</strong></span>
                </p>
            </div>
        </div>

        <div class="text-right shrink-0 pl-2">
            <p class="text-2xl sm:text-3xl font-black text-zinc-950 num leading-none">
                {{ $completedThisMonth }}
            </p>
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mt-1">
                Pelanggan
            </span>
        </div>
    </div>

    <!-- 3. FITUR GRAFIK PENDAPATAN (HANYA KELUAR PADA PAKET PRO & BISNIS) -->
    @if($canViewAnalytics)
        <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="chart" class="w-3.5 h-3.5" />
                    </span>
                    <div>
                        <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Grafik Pendapatan</h3>
                        <p class="text-[10px] text-zinc-400">Tren pada periode {{ strtolower($periodLabel) }}</p>
                    </div>
                </div>

                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
                    Pro / Business
                </span>
            </div>

            <!-- Batang Grafik (Bar Chart) Adaptif Sesuai Filter Periode -->
            <div class="pt-2">
                <div class="h-40 flex items-end justify-between gap-1.5 px-1 pb-1 border-b border-zinc-100">
                    @forelse($dailyChart as $bar)
                        <div class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end">
                            <!-- Tooltip Nominal Komisi -->
                            <div class="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-zinc-900 text-white text-[10px] font-bold py-0.5 px-1.5 rounded shadow whitespace-nowrap pointer-events-none z-20">
                                {{ $bar['day_name'] }} ({{ $bar['day_date'] }}): Rp{{ number_format($bar['amount'], 0, ',', '.') }}
                            </div>

                            <!-- Angka Singkat di Atas Batang -->
                            <span class="text-[8px] font-black num {{ $bar['is_peak'] ? 'text-amber-700 font-extrabold' : 'text-zinc-400' }}">
                                @if($bar['amount'] >= 1000)
                                    {{ round($bar['amount'] / 1000) }}k
                                @elseif($bar['amount'] > 0)
                                    {{ $bar['amount'] }}
                                @endif
                            </span>

                            <!-- Bar Batang Grafik -->
                            <div
                                class="w-full rounded-t-lg transition-all duration-300 {{ $bar['is_peak'] ? 'bg-amber-500 ring-2 ring-amber-400/60 shadow-xs' : ($bar['amount'] > 0 ? 'bg-amber-400/80 hover:bg-amber-500' : 'bg-zinc-100') }} {{ $bar['is_today'] ? 'border-b-2 border-zinc-900' : '' }}"
                                style="height: {{ $bar['percentage'] }}%;"
                            ></div>

                            <!-- Label Hari & Tanggal di Bawah Batang -->
                            <div class="text-center mt-1 w-full">
                                <span class="text-[9px] font-bold block truncate {{ $bar['is_today'] ? 'text-amber-600 font-black' : 'text-zinc-600' }}">
                                    {{ $bar['day_name'] }}
                                </span>
                                <span class="text-[8px] text-zinc-400 block num truncate">
                                    {{ $bar['day_date'] }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="w-full text-center py-6 text-zinc-400 text-xs">
                            Belum ada riwayat komisi pada periode ini.
                        </div>
                    @endforelse
                </div>

                <!-- Footer Info Puncak Pendapatan -->
                @if($peakAmount > 0)
                    <div class="mt-2.5 flex items-center justify-between text-[11px] text-zinc-500">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Tertinggi: <strong class="text-zinc-800">{{ $peakDay }}</strong></span>
                        </span>
                        <span class="font-extrabold text-amber-700 num">Rp{{ number_format($peakAmount, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 4. DAFTAR RIWAYAT KOMISI SESUAI PERIODE -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-zinc-100 text-zinc-700 flex items-center justify-center shrink-0">
                    <x-ui.icon name="ticket" class="w-3.5 h-3.5" />
                </span>
                <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Riwayat Komisi Layanan</h3>
            </div>
            <span class="text-[10px] font-bold text-zinc-400">{{ $recentCommissions->count() }} Transaksi</span>
        </div>

        <div class="divide-y divide-zinc-100">
            @forelse($recentCommissions as $entry)
                <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-zinc-900 truncate">
                            {{ $entry->orderItem?->service_name_snapshot ?? ($entry->orderItem?->service?->name ?? 'Layanan Pangkas') }}
                        </p>
                        <p class="text-[10px] text-zinc-400 num">
                            {{ $entry->earned_at ? $entry->earned_at->translatedFormat('d M Y, H:i') : '-' }}
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="font-black text-emerald-700 num block">
                            +Rp{{ number_format($entry->amount, 0, ',', '.') }}
                        </span>
                        @if($entry->base_amount && $entry->base_amount > 0)
                            <span class="text-[9px] text-zinc-400 num block">
                                dari Rp{{ number_format($entry->base_amount, 0, ',', '.') }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-zinc-400 text-xs space-y-1">
                    <p class="font-semibold text-zinc-500">Tidak ada komisi pada periode ini</p>
                    <p class="text-[11px]">Coba ubah filter periode waktu di atas untuk melihat tanggal lainnya.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 5. DAFTAR RIWAYAT TIP APRESIASI PELANGGAN (ANONIM - TANPA NAMA PELANGGAN DEMI PRIVASI) -->
    <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <x-ui.icon name="star" class="w-3.5 h-3.5 fill-amber-500 text-amber-500" />
                </span>
                <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Riwayat Tip Pelanggan</h3>
            </div>
            <span class="text-[10px] font-bold text-zinc-400">{{ ($recentTips ?? collect())->count() }} Kali Diterima</span>
        </div>

        <div class="divide-y divide-zinc-100">
            @forelse(($recentTips ?? collect()) as $tip)
                <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-zinc-900 flex items-center gap-1.5">
                            <span>Apresiasi Pelanggan</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-200/80">Tip</span>
                        </p>
                        <p class="text-[10px] text-zinc-400 num mt-0.5">
                            {{ $tip->paid_at ? \Illuminate\Support\Carbon::parse($tip->paid_at)->translatedFormat('d M Y, H:i') : '-' }}
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="font-black text-amber-600 num block">
                            +Rp{{ number_format($tip->tip_amount, 0, ',', '.') }}
                        </span>
                        <span class="text-[9px] font-semibold text-emerald-600 block">
                            100% Hak Barber
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-zinc-400 text-xs space-y-1">
                    <p class="font-semibold text-zinc-500">Belum ada tip pada periode ini</p>
                    <p class="text-[11px]">Tip sukarela dari pelanggan saat pembayaran di kasir otomatis tercatat transparan di sini.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Script Listener untuk Interaksi Dropdown Select & Form Filter -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filter-earnings-form');
            const customBox = document.getElementById('custom-date-box');
            const selectRoot = form ? form.querySelector('[data-ui-select][data-input-name="period"]') : null;

            if (selectRoot && form && customBox) {
                selectRoot.addEventListener('ui-select:change', function (e) {
                    const val = e.detail?.value;
                    if (val === 'custom') {
                        customBox.classList.remove('hidden');
                    } else {
                        customBox.classList.add('hidden');
                        form.submit();
                    }
                });
            }
        });
    </script>
</x-layouts.barber>
