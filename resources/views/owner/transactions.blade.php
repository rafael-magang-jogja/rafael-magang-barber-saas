<x-layouts.app title="Transaksi & Riwayat Pembayaran" subtitle="Semua transaksi pembayaran QRIS, Tunai, dan status invoice barbershop" role="owner">
    @php
        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $barberFilterOptions = $barbers->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->display_name ?? $b->user?->name,
            'sublabel' => $b->branch?->name ?? 'Semua Cabang',
        ])->all();

        $paymentStatusOptions = [
            ['value' => 'paid', 'label' => 'Lunas (Paid)'],
            ['value' => 'refunded', 'label' => 'Direfund (Refunded)'],
            ['value' => 'pending', 'label' => 'Menunggu (Pending)'],
        ];

        $periodOptions = [
            ['value' => 'all', 'label' => 'Semua Periode', 'sublabel' => 'Seluruh waktu'],
            ['value' => 'today', 'label' => 'Hari Ini (' . now()->translatedFormat('d M') . ')', 'sublabel' => 'Transaksi hari ini'],
            ['value' => 'yesterday', 'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M') . ')', 'sublabel' => 'Transaksi kemarin'],
            ['value' => '7days', 'label' => '7 Hari Terakhir', 'sublabel' => '1 minggu terakhir'],
            ['value' => 'this_month', 'label' => 'Bulan Ini (' . now()->translatedFormat('F Y') . ')', 'sublabel' => 'Bulan berjalan'],
            ['value' => 'last_month', 'label' => 'Bulan Lalu (' . now()->subMonth()->translatedFormat('F Y') . ')', 'sublabel' => 'Bulan sebelumnya'],
            ['value' => 'custom', 'label' => 'Kustom Rentang Tanggal...', 'sublabel' => 'Pilih tanggal bebas'],
        ];

        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $hasAnalyticsPro = $tenant?->hasFeature('analytics_pro') ?? true;
        $plan = $tenant?->plan();
        $planName = $plan?->name ?? 'Starter';
    @endphp

    <!-- Stat Cards Ringkasan Transaksi -->
    <div class="stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 transition-all duration-500 ease-in-out">
        <x-ui.stat
            label="Total Omzet Lunas"
            :value="'Rp' . number_format($totalRevenue, 0, ',', '.')"
            trend="Pembayaran Terverifikasi"
            highlight
        />
        <x-ui.stat
            label="Transaksi Lunas"
            :value="$paidCount . ' invoice'"
        />
        <x-ui.stat
            label="Total Refund"
            :value="$refundedCount . ' transaksi'"
        />
        <x-ui.stat
            label="Total Invoice"
            :value="$orders->total() . ' total'"
        />
    </div>

    <!-- 2 Grafik Fitur Pro Owner: Tren Omzet & Komposisi Metode Pembayaran -->
    @if($hasAnalyticsPro && isset($analytics))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
            <!-- Grafik 1: Tren Omzet 7 Hari Terakhir -->
            <x-ui.card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="trending-up" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900 flex items-center gap-1.5">
                                    <span>Tren Omzet (7 Hari Terakhir)</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 tracking-wide uppercase">Pro</span>
                                </h3>
                                <p class="text-[11px] text-zinc-400">Total omzet invoice lunas 7 hari terakhir</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-medium text-zinc-400 block">Total 7 Hari</span>
                            <span class="text-xs sm:text-sm font-extrabold text-amber-600">Rp{{ number_format($analytics['trend_7days']['total_revenue'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Visual Bar Vertikal 7 Hari -->
                    <div class="pt-4 pb-2">
                        <div class="h-32 flex items-end justify-between gap-1.5 sm:gap-2 px-1">
                            @foreach($analytics['trend_7days']['days'] as $day)
                                <div class="flex-1 flex flex-col items-center h-full justify-end group relative">
                                    <!-- Tooltip hover -->
                                    <div class="opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-200 absolute -top-10 z-20 whitespace-nowrap bg-zinc-900 text-white text-[10px] font-semibold py-1 px-2 rounded shadow-md">
                                        Rp{{ number_format($day['revenue'], 0, ',', '.') }} ({{ $day['count'] }} trx)
                                    </div>

                                    <!-- Bar Column -->
                                    <div class="w-full max-w-[28px] sm:max-w-[36px] flex flex-col items-center justify-end h-full">
                                        <div
                                            class="w-full rounded-t-md transition-all duration-300 {{ $day['is_peak'] ? 'bg-amber-500 shadow-xs shadow-amber-500/30' : ($day['is_today'] ? 'bg-zinc-800' : 'bg-zinc-200 group-hover:bg-amber-400/80') }}"
                                            style="height: {{ max(6, $day['percentage']) }}%;"
                                        ></div>
                                    </div>

                                    <!-- Label Tanggal & Hari -->
                                    <div class="mt-2 text-center">
                                        <span class="block text-[10px] font-bold {{ $day['is_today'] ? 'text-zinc-900' : 'text-zinc-500' }}">{{ $day['day_name'] }}</span>
                                        <span class="block text-[9px] text-zinc-400">{{ substr($day['label'], 0, 2) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Footer Summary Info -->
                <div class="mt-3 pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Puncak: <strong class="text-zinc-700 font-semibold">{{ $analytics['trend_7days']['peak_day'] }}</strong></span>
                    </span>
                    <span>Rata-rata: <strong class="text-zinc-700 font-semibold">Rp{{ number_format($analytics['trend_7days']['avg_revenue'], 0, ',', '.') }}/hari</strong></span>
                </div>
            </x-ui.card>

            <!-- Grafik 2: Komposisi Metode Pembayaran (QRIS vs Tunai) -->
            <x-ui.card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="smartphone" class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-bold text-xs sm:text-sm text-zinc-900 flex items-center gap-1.5">
                                    <span>Metode Pembayaran (QRIS vs Tunai)</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 tracking-wide uppercase">Pro</span>
                                </h3>
                                <p class="text-[11px] text-zinc-400">Rasio transaksi non-tunai vs uang tunai kasir</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-medium text-zinc-400 block">Rasio Non-Tunai</span>
                            <span class="text-xs sm:text-sm font-extrabold text-emerald-600">{{ $analytics['payment_methods']['qris_pct'] }}%</span>
                        </div>
                    </div>

                    <!-- Split Progress Bar Visual -->
                    <div class="pt-2 pb-3">
                        <div class="h-3 w-full bg-zinc-100 rounded-full overflow-hidden flex shadow-inner">
                            <div
                                class="bg-emerald-500 h-full transition-all duration-500"
                                style="width: {{ $analytics['payment_methods']['qris_pct'] }}%;"
                                title="QRIS / Non-Tunai: {{ $analytics['payment_methods']['qris_pct'] }}%"
                            ></div>
                            <div
                                class="bg-zinc-700 h-full transition-all duration-500"
                                style="width: {{ $analytics['payment_methods']['cash_pct'] }}%;"
                                title="Tunai: {{ $analytics['payment_methods']['cash_pct'] }}%"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between text-[10px] font-bold mt-1.5">
                            <span class="text-emerald-700">QRIS / Non-Tunai ({{ $analytics['payment_methods']['qris_pct'] }}%)</span>
                            <span class="text-zinc-600">Tunai Cash ({{ $analytics['payment_methods']['cash_pct'] }}%)</span>
                        </div>
                    </div>

                    <!-- Detail Box 2 Kolom -->
                    <div class="grid grid-cols-2 gap-2.5 pt-1">
                        <div class="p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-100">
                            <div class="flex items-center gap-1.5 mb-1 text-emerald-800">
                                <x-ui.icon name="smartphone" class="w-3.5 h-3.5 shrink-0" />
                                <span class="text-[11px] font-bold">QRIS / Non-Tunai</span>
                            </div>
                            <div class="text-xs sm:text-sm font-extrabold text-zinc-900">
                                Rp{{ number_format($analytics['payment_methods']['qris_total'], 0, ',', '.') }}
                            </div>
                            <span class="text-[10px] text-zinc-500 font-medium block mt-0.5">
                                {{ $analytics['payment_methods']['qris_count'] }} transaksi lunas
                            </span>
                        </div>

                        <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-200/70">
                            <div class="flex items-center gap-1.5 mb-1 text-zinc-700">
                                <x-ui.icon name="banknote" class="w-3.5 h-3.5 shrink-0" />
                                <span class="text-[11px] font-bold">Uang Tunai (Cash)</span>
                            </div>
                            <div class="text-xs sm:text-sm font-extrabold text-zinc-900">
                                Rp{{ number_format($analytics['payment_methods']['cash_total'], 0, ',', '.') }}
                            </div>
                            <span class="text-[10px] text-zinc-500 font-medium block mt-0.5">
                                {{ $analytics['payment_methods']['cash_count'] }} transaksi di kasir
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer Summary Info -->
                <div class="mt-3 pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Total Kas Diterima: <strong class="text-zinc-700 font-semibold">Rp{{ number_format($analytics['payment_methods']['total_revenue'], 0, ',', '.') }}</strong></span>
                    </span>
                    <span>Total: <strong class="text-zinc-700 font-semibold">{{ $analytics['payment_methods']['total_count'] }} invoice</strong></span>
                </div>
            </x-ui.card>
        </div>
    @elseif(! $hasAnalyticsPro)
        <!-- Banner Informasi: Fitur Eksklusif Pro & Business (Tidak Tersedia di Paket Starter) -->
        <div class="mb-5 p-3 sm:p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-3 shadow-2xs">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0">
                    <x-ui.icon name="lock" class="w-4 h-4 text-amber-700" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-extrabold text-zinc-900">Grafik Omzet &amp; QRIS</span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300">
                            Fitur Pro
                        </span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-0.5 leading-snug">
                        Visual tren omzet 7 hari dan rasio pembayaran aktif mulai paket Pro.
                    </p>
                </div>
            </div>
            <a
                href="{{ route('owner.subscription') }}"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold shrink-0 transition shadow-2xs self-start sm:self-center"
            >
                <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                <span>Upgrade Paket</span>
            </a>
        </div>
    @endif

    <!-- Filter & Pencarian Transaksi (Responsif Penuh: Mobile, Tablet & Desktop) -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs mb-5">
        <form method="GET" action="{{ route('owner.transactions') }}" class="space-y-3">
            <!-- Baris 1: Pencarian & Tombol Export Excel -->
            <div class="flex items-center gap-2 sm:gap-3">
                <div class="relative flex-1 min-w-0">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari no invoice, customer, no HP..."
                        class="w-full text-xs font-semibold pl-9 pr-3 py-2.5 rounded-xl bg-zinc-50/80 border border-zinc-200 text-zinc-900 placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 shadow-2xs transition"
                    />
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                        <x-ui.icon name="search" class="w-3.5 h-3.5" />
                    </div>
                </div>

                <a
                    href="{{ route('owner.reports.export') }}"
                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold shadow-2xs transition shrink-0 whitespace-nowrap cursor-pointer"
                    title="Export Riwayat Transaksi ke File Microsoft Excel (.xlsx)"
                >
                    <x-ui.icon name="download" class="w-3.5 h-3.5 text-white" />
                    <span class="hidden sm:inline">Export Excel (.xlsx)</span>
                    <span class="sm:hidden font-extrabold">Excel</span>
                </a>
            </div>

            <!-- Baris 2: Dropdown Filter & Aksi (Responsif: 1 Kolom Mobile, 2-3 Kolom Tablet, 5 Kolom Desktop) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-2.5 pt-2.5 border-t border-zinc-100">
                <!-- Dropdown Cabang -->
                <x-ui.select
                    name="branch_id"
                    size="sm"
                    placeholder="Semua Cabang"
                    icon="store"
                    :options="$branchFilterOptions"
                    :value="request('branch_id')"
                    search-placeholder="Cari cabang..."
                    auto-submit
                />

                <!-- Dropdown Barber -->
                <x-ui.select
                    name="barber_id"
                    size="sm"
                    placeholder="Semua Barber"
                    icon="scissors"
                    :options="$barberFilterOptions"
                    :value="request('barber_id')"
                    search-placeholder="Cari barber..."
                    auto-submit
                />

                <!-- Dropdown Periode Tanggal -->
                <x-ui.select
                    name="period"
                    size="sm"
                    placeholder="Semua Periode"
                    icon="calendar"
                    :options="$periodOptions"
                    :value="request('period', 'all')"
                    auto-submit
                />

                <!-- Dropdown Status Pembayaran -->
                <x-ui.select
                    name="payment_status"
                    size="sm"
                    placeholder="Semua Status"
                    icon="filter"
                    :options="$paymentStatusOptions"
                    :value="request('payment_status')"
                    auto-submit
                />

                <!-- Tombol Terapkan & Reset Filter -->
                <div class="flex items-center gap-2 sm:col-span-2 md:col-span-1">
                    <x-ui.button type="submit" size="sm" variant="secondary" class="flex-1 justify-center py-2 text-xs font-bold shadow-2xs cursor-pointer">
                        <x-ui.icon name="filter" class="w-3.5 h-3.5 text-zinc-500" />
                        <span>Filter</span>
                    </x-ui.button>

                    @if(request()->hasAny(['search', 'branch_id', 'barber_id', 'period', 'date_from', 'date_to', 'payment_status']))
                        <a
                            href="{{ route('owner.transactions') }}"
                            class="inline-flex items-center justify-center gap-1 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-2 rounded-xl transition border border-rose-200/60 shadow-2xs shrink-0 cursor-pointer"
                            title="Reset Filter Pencarian"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                            <span>Reset</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Rentang Tanggal Kustom (Muncul jika Kustom Tanggal Dipilih) -->
            @php
                $isCustomPeriod = request('period') === 'custom' || (request()->filled('date_from') && !in_array(request('period'), ['today', 'yesterday', '7days', 'this_month', 'last_month'], true));
            @endphp
            <div id="tx-custom-date-range" class="{{ $isCustomPeriod ? 'flex' : 'hidden' }} flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-2.5 border-t border-zinc-100">
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
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs transition shadow-2xs flex items-center justify-center gap-1.5 cursor-pointer shrink-0"
                >
                    <x-ui.icon name="check" class="w-3.5 h-3.5 text-amber-400" />
                    <span>Terapkan Tanggal</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel & Daftar Transaksi (Responsif: Card di Mobile/Tablet, Tabel di Desktop) -->
    <x-ui.card class="overflow-hidden">
        <x-slot:header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-2 w-full">
                <h2 class="font-bold text-sm sm:text-base flex items-center gap-2 text-zinc-900">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                        <x-ui.icon name="receipt" class="w-4 h-4 text-amber-600" />
                    </span>
                    <span>Daftar Transaksi Pembayaran</span>
                </h2>
                <span class="text-xs text-zinc-400 font-medium">Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} invoice</span>
            </div>
        </x-slot:header>

        @if($orders->isEmpty())
            <x-ui.empty-state title="Belum ada data transaksi yang sesuai filter" />
        @else
            <!-- Desktop Table View (Layar Lebar >= 1024px) -->
            <div class="hidden lg:block overflow-x-auto rounded-xl border border-zinc-200/80">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500">
                            <th class="py-2.5 px-4">Invoice &amp; Waktu</th>
                            <th class="py-2.5 px-4">Customer</th>
                            <th class="py-2.5 px-4">Layanan &amp; Barber</th>
                            <th class="py-2.5 px-4">Pembayaran</th>
                            <th class="py-2.5 px-4 text-right">Grand Total</th>
                            <th class="py-2.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 text-xs">
                        @foreach($orders as $order)
                            @php
                                $paymentMethod = $order->payments->first()?->method?->value ?? ($order->payment_method ?? 'qris');
                                $isCash = str_contains(strtolower((string)$paymentMethod), 'cash') || strtolower((string)$paymentMethod) === 'tunai';
                                $itemNames = $order->items->pluck('name')->filter()->values();
                                if ($itemNames->isEmpty()) {
                                    $itemNames = $order->items->map(fn($i) => $i->service?->name)->filter()->values();
                                }
                                $servicesSummary = $itemNames->join(', ') ?: 'Layanan Cukur';
                            @endphp
                            <tr class="hover:bg-zinc-50/70 transition">
                                <td class="py-3 px-4">
                                    <a href="{{ route('invoices.show', $order->number) }}" class="font-mono font-bold text-amber-700 hover:text-amber-800 hover:underline inline-flex items-center gap-1.5" title="Buka E-Invoice">
                                        <x-ui.icon name="receipt" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                        <span>{{ $order->number }}</span>
                                    </a>
                                    <p class="text-[11px] text-zinc-400 font-medium mt-0.5">{{ $order->created_at->translatedFormat('d M Y · H:i') }} WIB</p>
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-bold text-zinc-900">{{ $order->customerProfile?->name ?? 'Pelanggan Walk-in' }}</p>
                                    @if($order->customerProfile?->phone)
                                        <p class="text-[11px] text-zinc-400 font-mono mt-0.5">{{ $order->customerProfile->phone }}</p>
                                    @else
                                        <span class="text-[10px] text-zinc-400 font-medium">Walk-in</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-bold text-zinc-900 truncate max-w-[220px]" title="{{ $servicesSummary }}">
                                        {{ $servicesSummary }}
                                    </p>
                                    <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 mt-0.5">
                                        <x-ui.icon name="scissors" class="w-3 h-3 text-amber-600 shrink-0" />
                                        <span class="truncate max-w-[120px] font-semibold text-zinc-700">{{ $order->barber?->display_name ?? $order->barber?->user?->name ?? '-' }}</span>
                                        <span class="text-zinc-300">·</span>
                                        <span class="text-zinc-400 truncate max-w-[120px]">{{ $order->branch?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div>
                                        @if($order->payment_status->value === 'paid')
                                            <p class="font-extrabold text-xs text-emerald-600 flex items-center gap-1.5">
                                                <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                                                <span>Lunas</span>
                                            </p>
                                        @elseif($order->payment_status->value === 'refunded')
                                            <p class="font-extrabold text-xs text-rose-600 flex items-center gap-1">
                                                <x-ui.icon name="rotate-ccw" class="w-3 h-3 text-rose-500 shrink-0" />
                                                <span>Direfund</span>
                                            </p>
                                        @else
                                            <p class="font-extrabold text-xs text-amber-600 flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                <span>Menunggu</span>
                                            </p>
                                        @endif
                                        <p class="text-[11px] font-semibold text-zinc-500 flex items-center gap-1 mt-0.5">
                                            @if($isCash)
                                                <x-ui.icon name="banknote" class="w-3 h-3 text-zinc-400" />
                                                <span>Tunai</span>
                                            @else
                                                <x-ui.icon name="smartphone" class="w-3 h-3 text-emerald-600" />
                                                <span>QRIS</span>
                                            @endif
                                        </p>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-black num text-sm text-zinc-900">
                                        Rp{{ number_format($order->grand_total, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if($order->payment_status->value === 'paid')
                                        <button
                                            type="button"
                                            onclick="openRefundModal('{{ route('owner.orders.refund', $order) }}', '{{ $order->number }}', 'Rp{{ number_format($order->grand_total, 0, ',', '.') }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white text-xs font-bold shadow-xs transition cursor-pointer"
                                            title="Refund Invoice"
                                        >
                                            <x-ui.icon name="rotate-ccw" class="w-3 h-3 text-white" />
                                            <span>Refund</span>
                                        </button>
                                    @elseif($order->payment_status->value === 'refunded')
                                        <span class="text-xs font-bold text-rose-600">Direfund</span>
                                    @else
                                        <span class="text-zinc-300 text-xs font-medium">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card List View (Layar Tablet & HP < 1024px) -->
            <div class="lg:hidden divide-y divide-zinc-100 rounded-xl border border-zinc-200/80 bg-white overflow-hidden">
                @foreach($orders as $order)
                    @php
                        $paymentMethod = $order->payments->first()?->method?->value ?? ($order->payment_method ?? 'qris');
                        $isCash = str_contains(strtolower((string)$paymentMethod), 'cash') || strtolower((string)$paymentMethod) === 'tunai';
                        $itemNames = $order->items->pluck('name')->filter()->values();
                        if ($itemNames->isEmpty()) {
                            $itemNames = $order->items->map(fn($i) => $i->service?->name)->filter()->values();
                        }
                        $servicesSummary = $itemNames->join(', ') ?: 'Layanan Cukur';
                    @endphp
                    <div class="p-3.5 sm:p-4 hover:bg-zinc-50/60 transition">
                        <!-- Baris 1: Invoice Number, Badge Status & Grand Total -->
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a
                                        href="{{ route('invoices.show', $order->number) }}"
                                        class="font-mono font-bold text-amber-700 hover:text-amber-800 hover:underline text-xs sm:text-sm inline-flex items-center gap-1"
                                        title="Buka E-Invoice"
                                    >
                                        <x-ui.icon name="receipt" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                        <span>{{ $order->number }}</span>
                                    </a>

                                    {{-- Status Badge --}}
                                    @if($order->payment_status->value === 'paid')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-extrabold border border-emerald-200/60">
                                            <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                            <span>Lunas</span>
                                        </span>
                                    @elseif($order->payment_status->value === 'refunded')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[10px] font-extrabold border border-rose-200/60">
                                            <x-ui.icon name="rotate-ccw" class="w-3 h-3 text-rose-600" />
                                            <span>Direfund</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-extrabold border border-amber-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>Menunggu</span>
                                        </span>
                                    @endif
                                </div>

                                <p class="text-[11px] text-zinc-400 font-medium mt-1">
                                    {{ $order->created_at->translatedFormat('d M Y · H:i') }} WIB
                                </p>
                            </div>

                            {{-- Grand Total & Metode Pembayaran --}}
                            <div class="text-right shrink-0">
                                <span class="font-black num text-sm sm:text-base text-zinc-950 block leading-tight">
                                    Rp{{ number_format($order->grand_total, 0, ',', '.') }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-zinc-500 mt-0.5">
                                    @if($isCash)
                                        <x-ui.icon name="banknote" class="w-3 h-3 text-zinc-400" />
                                        <span>Tunai</span>
                                    @else
                                        <x-ui.icon name="smartphone" class="w-3 h-3 text-emerald-600" />
                                        <span>QRIS</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Baris 2: Customer & Layanan / Barber -->
                        <div class="mt-2.5 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs">
                            <!-- Customer Info -->
                            <div class="flex items-start gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-zinc-100 text-zinc-500 grid place-items-center shrink-0 mt-0.5">
                                    <x-ui.icon name="user" class="w-3.5 h-3.5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-zinc-900 truncate text-xs">{{ $order->customerProfile?->name ?? 'Walk-in' }}</p>
                                    <p class="text-[10px] text-zinc-400 font-mono truncate">{{ $order->customerProfile?->phone ?? 'Pelanggan Walk-in' }}</p>
                                </div>
                            </div>

                            <!-- Layanan & Barber Info -->
                            <div class="flex items-start gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-700 grid place-items-center shrink-0 mt-0.5">
                                    <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-zinc-900 truncate text-xs" title="{{ $servicesSummary }}">{{ $servicesSummary }}</p>
                                    <p class="text-[10px] text-zinc-500 truncate">
                                        <span class="font-semibold text-zinc-700">{{ $order->barber?->display_name ?? $order->barber?->user?->name ?? '-' }}</span>
                                        <span class="text-zinc-300 mx-0.5">·</span>
                                        <span class="text-zinc-400">{{ $order->branch?->name ?? '-' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Baris 3: Tombol Aksi Refund (Jika Pembayaran Lunas) -->
                        @if($order->payment_status->value === 'paid')
                            <div class="mt-2.5 pt-2 border-t border-zinc-100/70 flex items-center justify-end">
                                <button
                                    type="button"
                                    onclick="openRefundModal('{{ route('owner.orders.refund', $order) }}', '{{ $order->number }}', 'Rp{{ number_format($order->grand_total, 0, ',', '.') }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 active:scale-95 text-rose-700 text-xs font-bold border border-rose-200/70 shadow-2xs transition cursor-pointer"
                                    title="Ajukan Refund untuk transaksi ini"
                                >
                                    <x-ui.icon name="rotate-ccw" class="w-3.5 h-3.5 text-rose-600" />
                                    <span>Refund</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-100">
                {{ $orders->links() }}
            </div>
        @endif
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
                    <input type="hidden" name="reason" value="Refund diajukan owner di menu Transaksi" />
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
    </script>
</x-layouts.app>
