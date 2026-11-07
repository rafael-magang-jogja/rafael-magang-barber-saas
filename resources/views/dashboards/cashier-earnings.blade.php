@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
    $effectiveRole = $currentTenantCtx->role() ?? request()->attributes->get('current_role') ?? 'cashier';

    $periodOptions = [
        [
            'value' => 'this_month',
            'label' => 'Bulan Ini (' . now()->translatedFormat('F Y') . ')',
            'sublabel' => 'Periode berjalan',
        ],
        [
            'value' => 'today',
            'label' => 'Hari Ini (' . now()->translatedFormat('d M Y') . ')',
            'sublabel' => 'Transaksi hari ini',
        ],
        [
            'value' => 'yesterday',
            'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M Y') . ')',
            'sublabel' => 'Rekap kemarin',
        ],
        [
            'value' => '7days',
            'label' => '7 Hari Terakhir',
            'sublabel' => 'Satu minggu terakhir',
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

    $isMonthlyPeriod = in_array($selectedPeriod, ['this_month', 'last_month'], true);
@endphp

<x-layouts.app
    :title="'Pendapatan Kasir — ' . ($cashierMembership?->display_name ?? $user?->name ?? 'Kasir')"
    subtitle="Pantau omzet transaksi POS dan performa kasir"
    :role="$effectiveRole"
>
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header Ringkasan Penghasilan & Cabang -->
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-zinc-900 tracking-tight">Pendapatan &amp; Transaksi Kasir</h1>
            <p class="text-xs text-zinc-500 mt-0.5">
                {{ $tenant?->name ?? 'Barbershop' }} &bull; {{ $branch?->name ?? 'Semua Cabang' }}
            </p>
        </div>

        <!-- FILTER PERIODE WAKTU -->
        <div class="p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-black text-zinc-800 uppercase tracking-wider flex items-center gap-2">
                    <x-ui.icon name="calendar" class="w-4 h-4 text-amber-500" />
                    <span>Filter Periode Performa</span>
                </span>
                <span class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200/70 truncate max-w-[220px]">
                    {{ $periodLabel }}
                </span>
            </div>

            <form method="GET" action="{{ route('cashier.earnings') }}" id="filter-earnings-form" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <x-ui.select
                            name="period"
                            icon="calendar"
                            :options="$periodOptions"
                            :value="$selectedPeriod"
                            size="sm"
                            :searchable="false"
                            class="w-full"
                        />
                    </div>
                    <div class="flex items-center">
                        <button
                            type="submit"
                            class="w-full py-2.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs"
                        >
                            <x-ui.icon name="filter" class="w-3.5 h-3.5 text-amber-400" />
                            <span>Terapkan Filter</span>
                        </button>
                    </div>
                </div>

                <!-- Rentang Tanggal Kustom -->
                <div id="custom-date-box" class="{{ $selectedPeriod === 'custom' ? '' : 'hidden' }} pt-3 space-y-3 border-t border-zinc-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="date_from" class="block font-bold text-zinc-600 text-[11px] mb-1">Dari Tanggal</label>
                            <input
                                type="date"
                                id="date_from"
                                name="date_from"
                                value="{{ $dateFrom }}"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono"
                            />
                        </div>
                        <div>
                            <label for="date_to" class="block font-bold text-zinc-600 text-[11px] mb-1">Sampai Tanggal</label>
                            <input
                                type="date"
                                id="date_to"
                                name="date_to"
                                value="{{ $dateTo }}"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono"
                            />
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- 1. KARTU HERO TOTAL OMZET & PERFORMA PERIODE TERPILIH -->
        <div class="p-6 rounded-3xl bg-zinc-950 text-white border border-zinc-800 shadow-md relative overflow-hidden space-y-4">
            <!-- Subtle Glow Effect -->
            <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 relative z-10">
                <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-2">
                    <x-ui.icon name="wallet" class="w-4 h-4 text-amber-400" />
                    <span>Total Omzet Transaksi POS ({{ $periodLabel }})</span>
                </span>
                <span class="text-xs font-semibold text-zinc-400">
                    <strong class="text-white font-mono">{{ $periodOrdersCount }}</strong> order diselesaikan
                </span>
            </div>

            <div class="relative z-10">
                <p class="text-3xl sm:text-4xl font-black text-white tracking-tight num">
                    Rp{{ number_format($periodOmzet, 0, ',', '.') }}
                </p>
                <p class="text-xs text-zinc-400 mt-1">
                    Total nilai pembayaran lunas yang berhasil Anda input dan selesaikan di kasir pada periode ini.
                </p>
            </div>
        </div>

        <!-- 2. QUICK METRIC CARDS (HARI INI & PERBANDINGAN BULANAN) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Kasir Hari Ini</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <p class="text-lg font-black text-zinc-950 num">
                    Rp{{ number_format($omzetToday, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-zinc-500 font-medium">
                    {{ $ordersToday }} transaksi selesai
                </p>
            </div>

            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Kasir Bulan Ini</span>
                    <x-ui.icon name="trending-up" class="w-3.5 h-3.5 text-blue-500" />
                </div>
                <p class="text-lg font-black text-zinc-950 num">
                    Rp{{ number_format($omzetThisMonth, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-zinc-500 font-medium">
                    {{ $ordersThisMonth }} total transaksi
                </p>
            </div>

            <div class="p-4 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Kasir Bulan Lalu</span>
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400" />
                </div>
                <p class="text-lg font-black text-zinc-700 num">
                    Rp{{ number_format($omzetLastMonth, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-zinc-400">
                    Bulan {{ now()->subMonth()->translatedFormat('F Y') }}
                </p>
            </div>
        </div>

        <!-- 3. GRAFIK TREN OMZET KASIR (FITUR PRO / BUSINESS) -->
        @if($canViewAnalytics && !empty($dailyChart))
            <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="chart" class="w-4 h-4" />
                        </span>
                        <div>
                            <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Grafik Penjualan Kasir</h3>
                            <p class="text-[11px] text-zinc-400">Tren transaksi dan nominal omzet selama {{ strtolower($periodLabel) }}</p>
                        </div>
                    </div>
                    @if($peakDay)
                        <div class="text-[11px] text-amber-700 font-semibold bg-amber-50 px-2.5 py-1 rounded-xl border border-amber-200/70">
                            Puncak: <strong>{{ $peakDay }}</strong> (Rp{{ number_format($peakAmount, 0, ',', '.') }})
                        </div>
                    @endif
                </div>

                <!-- Bar Chart Visual -->
                <div class="pt-4 pb-2">
                    <div class="h-40 flex items-end gap-1.5 sm:gap-2 justify-between">
                        @foreach($dailyChart as $chartItem)
                            <div class="flex-1 flex flex-col items-center gap-1 group relative min-w-0">
                                <!-- Tooltip -->
                                <div class="absolute -top-10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none bg-zinc-900 text-white text-[10px] font-mono py-1 px-2 rounded-lg shadow-lg whitespace-nowrap z-20">
                                    {{ $chartItem['day_name'] }}: Rp{{ number_format($chartItem['amount'], 0, ',', '.') }} ({{ $chartItem['count'] }} trs)
                                </div>

                                <!-- Bar -->
                                <div class="w-full bg-zinc-100 rounded-t-lg relative flex items-end justify-center overflow-hidden h-32">
                                    <div
                                        class="w-full rounded-t-lg transition-all duration-300 {{ $chartItem['is_peak'] ? 'bg-amber-500' : ($chartItem['is_today'] ? 'bg-amber-400' : 'bg-zinc-800 group-hover:bg-amber-400') }}"
                                        @style(['height: ' . $chartItem['percentage'] . '%'])
                                    ></div>
                                </div>

                                <!-- Label Hari -->
                                <span class="text-[10px] font-bold text-zinc-500 truncate w-full text-center {{ $chartItem['is_today'] ? 'text-amber-600 font-extrabold' : '' }}">
                                    {{ $chartItem['day_name'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- 4. RIWAYAT TRANSAKSI POS TERAKHIR YANG DIPROSES OLEH KASIR -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="receipt" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 class="font-black text-xs uppercase tracking-wider text-zinc-900">Riwayat Transaksi Terakhir</h3>
                        <p class="text-[11px] text-zinc-400">Daftar transaksi kasir yang selesai diproses</p>
                    </div>
                </div>
                <span class="text-[11px] font-bold text-zinc-500 font-mono">
                    {{ count($recentOrders) }} Transaksi
                </span>
            </div>

            @if(count($recentOrders) > 0)
                <div class="overflow-x-auto -mx-5 sm:mx-0">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-100 text-[10px] uppercase tracking-wider font-extrabold text-zinc-400">
                                <th class="pb-2.5 px-3 w-12 text-center">No</th>
                                <th class="pb-2.5 px-3">Tanggal &amp; Waktu</th>
                                <th class="pb-2.5 px-3">Pelanggan</th>
                                <th class="pb-2.5 px-3">Metode Bayar</th>
                                <th class="pb-2.5 px-3 text-right">Total Bayar</th>
                                <th class="pb-2.5 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach($recentOrders as $order)
                                <tr class="hover:bg-zinc-50/80 transition-colors">
                                    <td class="py-3 px-3 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-zinc-100 text-zinc-700 font-extrabold font-mono text-xs num">
                                            {{ $loop->iteration }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <p class="font-bold text-zinc-900">{{ $order->paid_at ? $order->paid_at->translatedFormat('d M Y') : '-' }}</p>
                                        <p class="text-[10px] text-zinc-400 font-mono">{{ $order->paid_at ? $order->paid_at->format('H:i') . ' WIB' : '-' }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-zinc-900">{{ $order->customerProfile?->name ?? ($order->customer_name ?? 'Pelanggan Umum') }}</p>
                                        <p class="text-[10px] text-zinc-400 font-mono">{{ $order->customerProfile?->phone ?? ($order->customer_phone ?? '-') }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ ($order->payment_method ?? 'cash') === 'cash' ? 'bg-zinc-100 text-zinc-700' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                            {{ strtoupper($order->payment_method ?? 'CASH') }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-zinc-900 num">
                                        Rp{{ number_format($order->paid_total, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                            Lunas
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-zinc-400 text-xs">
                    <x-ui.icon name="file-text" class="w-8 h-8 mx-auto text-zinc-300 mb-2" />
                    <p class="font-semibold">Belum ada transaksi POS pada periode ini.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Script Filter Interaktif -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var periodSelect = document.querySelector('select[name="period"]');
            var customBox = document.getElementById('custom-date-box');

            if (periodSelect && customBox) {
                periodSelect.addEventListener('change', function () {
                    if (this.value === 'custom') {
                        customBox.classList.remove('hidden');
                    } else {
                        customBox.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</x-layouts.app>
