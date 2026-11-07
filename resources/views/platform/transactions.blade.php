<x-layouts.app title="Monitoring Pendapatan Bulanan" subtitle="Monitoring perputaran omzet (GMV) dan volume transaksi barbershop se-platform per bulan" role="superadmin">
    <!-- 1. KARTU METRIK GLOBAL BULAN TERPILIH -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <!-- Total GMV Bulan Terpilih -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs relative overflow-hidden">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Omzet GMV ({{ $selectedMonthLabel }})</span>
                <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                    <x-ui.icon name="coins" class="w-4 h-4 text-amber-600" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                Rp{{ number_format($totalGmv, 0, ',', '.') }}
            </div>
            <div class="flex items-center gap-1.5 mt-2 text-[11px]">
                @if($growthPercentage >= 0)
                    <span class="inline-flex items-center gap-0.5 text-emerald-600 font-bold">
                        <x-ui.icon name="trending-up" class="w-3.5 h-3.5" />
                        <span>+{{ $growthPercentage }}%</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-0.5 text-rose-600 font-bold">
                        <x-ui.icon name="trending-down" class="w-3.5 h-3.5" />
                        <span>{{ $growthPercentage }}%</span>
                    </span>
                @endif
                <span class="text-zinc-400">vs bulan lalu</span>
            </div>
        </div>

        <!-- Total Transaksi Selesai -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Transaksi Selesai</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <x-ui.icon name="check" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                {{ number_format($totalPaidCount, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Pangkas sukses pada periode {{ strtolower($selectedMonthLabel) }}</p>
        </div>

        <!-- Average Order Value (AOV) -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Rata-Rata Transaksi (AOV)</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <x-ui.icon name="receipt" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                Rp{{ number_format($aov, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Nilai rata-rata per transaksi pangkas</p>
        </div>

        <!-- Barbershop Berkinerja -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Barbershop Terdata</span>
                <div class="w-8 h-8 rounded-xl bg-zinc-100 text-zinc-700 flex items-center justify-center">
                    <x-ui.icon name="store" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                {{ $tenants->total() }} Barbershop
            </div>
            <p class="text-[11px] text-zinc-500 mt-2">Mitra aktif pada ekosistem platform</p>
        </div>
    </div>

    <!-- 2. GRAFIK TREN OMZET BULANAN (6 BULAN TERAKHIR) -->
    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 mb-6 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-sm font-black text-zinc-900 tracking-tight flex items-center gap-2">
                    <x-ui.icon name="trending-up" class="w-4 h-4 text-amber-600" />
                    <span>Tren Perputaran Omzet Platform (6 Bulan Terakhir)</span>
                </h2>
                <p class="text-xs text-zinc-500 mt-0.5">Grafik akumulasi volume GMV seluruh transaksi barbershop se-platform</p>
            </div>
            <span class="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200/80 px-2.5 py-1 rounded-xl self-start sm:self-auto">
                Bulan Aktif: {{ $selectedMonthLabel }}
            </span>
        </div>

        <div class="grid grid-cols-6 gap-2 sm:gap-4 pt-4 pb-2 items-end h-44 border-b border-zinc-100">
            @foreach($monthlyTrends as $trend)
                <div class="flex flex-col items-center h-full justify-end group">
                    <!-- Tooltip Hover Nominal -->
                    <div class="opacity-0 group-hover:opacity-100 transition text-[10px] font-bold bg-zinc-900 text-white px-2 py-0.5 rounded-lg mb-1.5 whitespace-nowrap pointer-events-none shadow-sm">
                        Rp{{ number_format($trend['gmv'], 0, ',', '.') }} ({{ $trend['count'] }} order)
                    </div>

                    <!-- Batang Bar Grafik -->
                    <div class="w-full max-w-[48px] bg-zinc-100 rounded-xl relative flex items-end overflow-hidden" style="height: 100%;">
                        <div
                            class="w-full rounded-xl transition-all duration-500 {{ $trend['is_current'] ? 'bg-amber-500 shadow-sm' : 'bg-zinc-800 hover:bg-amber-400' }}"
                            @style(['height: ' . $trend['height_percentage'] . '%'])
                        ></div>
                    </div>

                    <!-- Label Bulan -->
                    <span class="mt-2 text-[10px] sm:text-xs font-bold {{ $trend['is_current'] ? 'text-amber-800 font-black' : 'text-zinc-500' }}">
                        {{ $trend['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @php
        $tenantFilterOptions = $allTenants->map(fn ($t) => [
            'value' => (string) $t->id,
            'label' => $t->name,
            'sublabel' => $t->slug,
        ])->all();
    @endphp

    <!-- 3. TOOLBAR FILTER PERIODE & PENCARIAN -->
    <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs mb-6">
        <form method="GET" action="{{ route('platform.transactions') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <!-- Filter Dropdown Bulan -->
            <div class="sm:col-span-4">
                <x-ui.select
                    label="Pilih Periode Bulan"
                    name="month"
                    :value="$selectedMonth"
                    :options="$monthOptions"
                    icon="calendar"
                    :auto-submit="true"
                    placeholder="Pilih Bulan..."
                />
            </div>

            <!-- Filter Pilih Barbershop Spesifik -->
            <div class="sm:col-span-4">
                <x-ui.select
                    label="Filter Barbershop"
                    name="tenant_id"
                    :value="$tenantId ? (string) $tenantId : ''"
                    :options="$tenantFilterOptions"
                    icon="store"
                    :auto-submit="true"
                    :searchable="true"
                    search-placeholder="Cari barbershop..."
                    placeholder="Semua Barbershop"
                />
            </div>

            <!-- Search Nama & Tombol Aksi -->
            <div class="sm:col-span-4 flex items-end gap-2">
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-semibold text-zinc-700 mb-1.5">Cari Barbershop</label>
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Ketik nama outlet..."
                            class="w-full h-11 pl-9 pr-3 rounded-xl border border-zinc-200 text-xs font-semibold text-zinc-800 bg-white shadow-2xs focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500"
                        />
                        <x-ui.icon name="search" class="w-4 h-4 text-zinc-400 absolute left-3 top-3.5 pointer-events-none" />
                    </div>
                </div>

                <a
                    href="{{ route('platform.transactions.export', request()->query()) }}"
                    class="h-11 px-3.5 rounded-xl border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 active:scale-95 text-zinc-700 font-bold text-xs transition inline-flex items-center gap-1.5 shrink-0 shadow-2xs cursor-pointer"
                    title="Export Rekap Pendapatan Bulan Ini ke Excel (.xlsx)"
                >
                    <x-ui.icon name="download" class="w-3.5 h-3.5 text-emerald-600" />
                    <span class="hidden sm:inline">Export Excel</span>
                </a>
            </div>
        </form>
    </div>

    <!-- 4. TABEL KINERJA OMZET BARBERSHOP BULANAN (BERSIH DARI DATA PRIBADI CUSTOMER) -->
    <div class="rounded-2xl border border-zinc-200/90 bg-white overflow-hidden shadow-2xs mb-6" data-pagination-container="platform-transactions">
        <div class="px-5 py-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
            <div>
                <h3 class="text-sm font-black text-zinc-900 tracking-tight">Kinerja Pendapatan Barbershop ({{ $selectedMonthLabel }})</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Menampilkan perputaran omzet bulanan dan kontribusi volume per mitra barbershop</p>
            </div>
            <span class="text-xs font-bold text-zinc-500">
                Total {{ $tenants->total() }} Barbershop
            </span>
        </div>

        @if($tenants->isEmpty())
            <div class="p-12 text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center mx-auto mb-3">
                    <x-ui.icon name="store" class="w-6 h-6" />
                </div>
                <h4 class="text-sm font-bold text-zinc-900">Tidak ada data barbershop ditemukan</h4>
                <p class="text-xs text-zinc-500 max-w-sm mx-auto">Coba ubah kata kunci pencarian atau pilih periode bulan lainnya.</p>
            </div>
        @else
            <!-- Tampilan Desktop & Tablet (Table) -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-[11px] font-bold uppercase tracking-wider text-zinc-500 border-b border-zinc-100">
                        <tr>
                            <th class="py-3 px-4 w-12 text-center">No</th>
                            <th class="py-3 px-4">Nama Barbershop</th>
                            <th class="py-3 px-4 text-center">Paket SaaS</th>
                            <th class="py-3 px-4 text-center">Cabang</th>
                            <th class="py-3 px-4 text-center">Total Transaksi</th>
                            <th class="py-3 px-4 text-right">Omzet / GMV (Bulan Ini)</th>
                            <th class="py-3 px-4 text-right">Kontribusi</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($tenants as $index => $tenant)
                            @php
                                $sub = $tenant->subscriptions->first();
                                $tenantGmv = (int) ($tenant->month_gmv ?? 0);
                                $tenantOrders = (int) ($tenant->month_orders_count ?? 0);
                                $sharePct = $totalGmv > 0 ? round(($tenantGmv / $totalGmv) * 100, 1) : 0;
                                $rankNumber = ($tenants->currentPage() - 1) * $tenants->perPage() + $index + 1;
                            @endphp
                            <tr class="hover:bg-zinc-50/70 transition">
                                <!-- Ranking Peringkat -->
                                <td class="py-3.5 px-4 text-center">
                                    @if($rankNumber === 1 && $tenantGmv > 0)
                                        <span class="w-6 h-6 rounded-full bg-amber-500 text-zinc-950 font-black text-[11px] inline-flex items-center justify-center shadow-xs">1</span>
                                    @elseif($rankNumber === 2 && $tenantGmv > 0)
                                        <span class="w-6 h-6 rounded-full bg-zinc-300 text-zinc-900 font-bold text-[11px] inline-flex items-center justify-center">2</span>
                                    @elseif($rankNumber === 3 && $tenantGmv > 0)
                                        <span class="w-6 h-6 rounded-full bg-amber-700/20 text-amber-900 font-bold text-[11px] inline-flex items-center justify-center">3</span>
                                    @else
                                        <span class="text-zinc-400 font-semibold num">{{ $rankNumber }}</span>
                                    @endif
                                </td>

                                <!-- Identitas Barbershop -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-zinc-950 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-2xs">
                                            {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('platform.tenants.show', $tenant) }}" class="font-extrabold text-zinc-950 text-xs sm:text-sm hover:text-amber-600 transition truncate block">
                                                {{ $tenant->name }}
                                            </a>
                                            <span class="text-[11px] text-zinc-400 font-mono">/{{ $tenant->slug }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Paket SaaS -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $sub?->plan?->code === 'business' ? 'bg-amber-50 text-amber-800 border-amber-200' : ($sub?->plan?->code === 'pro' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-zinc-100 text-zinc-700 border-zinc-200') }}">
                                        {{ $sub?->plan?->name ?? 'Starter' }}
                                    </span>
                                </td>

                                <!-- Jumlah Cabang -->
                                <td class="py-3.5 px-4 text-center font-bold text-zinc-800 num">
                                    {{ $tenant->branches_count }} Cabang
                                </td>

                                <!-- Volume Transaksi Bulan Ini -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="font-extrabold text-zinc-900 num text-xs">
                                        {{ number_format($tenantOrders, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 block">pangkas</span>
                                </td>

                                <!-- Omzet / GMV Bulan Ini -->
                                <td class="py-3.5 px-4 text-right">
                                    <span class="font-black text-zinc-950 num text-sm block">
                                        Rp{{ number_format($tenantGmv, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400">Total penerimaan</span>
                                </td>

                                <!-- Kontribusi terhadap GMV Platform -->
                                <td class="py-3.5 px-4 text-right">
                                    <span class="font-bold text-zinc-700 num text-xs block">
                                        {{ $sharePct }}%
                                    </span>
                                    <div class="w-16 bg-zinc-100 rounded-full h-1.5 mt-1 ml-auto overflow-hidden">
                                        <div class="bg-amber-500 h-1.5 rounded-full" @style(['width: ' . min(100, $sharePct) . '%'])></div>
                                    </div>
                                </td>

                                <!-- Status Tenant -->
                                <td class="py-3.5 px-4 text-center">
                                    @if($tenant->status->value === 'active')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Aktif</span>
                                        </span>
                                    @elseif($tenant->status->value === 'trial')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>Trial</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                            Suspended
                                        </span>
                                    @endif
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 px-4 text-center">
                                    <a
                                        href="{{ route('platform.tenants.show', $tenant) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold transition"
                                        title="Buka detail profil tenant"
                                    >
                                        <span>Detail</span>
                                        <x-ui.icon name="arrow-right" class="w-3 h-3" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($tenants->hasPages())
                <div class="p-4 border-t border-zinc-100 bg-zinc-50/30">
                    {{ $tenants->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
