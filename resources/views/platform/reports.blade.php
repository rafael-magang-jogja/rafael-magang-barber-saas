<x-layouts.app title="Laporan & Pertumbuhan Platform" subtitle="Metrik SaaS, MRR, adopsi paket dan tren pertumbuhan tenant" role="superadmin">
    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-base sm:text-lg font-black text-zinc-900 tracking-tight flex items-center gap-2">
                <x-ui.icon name="trending-up" class="w-5 h-5 text-amber-600" />
                <span>Analitik & Laporan Pertumbuhan Bisnis SaaS</span>
            </h1>
            <p class="text-xs text-zinc-500 mt-0.5">Pantau kesehatan finansial platform, MRR sewa sistem, dan laju pertumbuhan barbershop baru</p>
        </div>

        <a
            href="{{ route('platform.reports.export') }}"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs shadow-xs transition cursor-pointer self-start sm:self-auto shrink-0"
            title="Download Laporan Ekosistem Tenant dalam format Microsoft Excel (.xlsx)"
        >
            <x-ui.icon name="download" class="w-4 h-4 text-white" />
            <span>Export Excel (.xlsx)</span>
        </a>
    </div>

    <!-- Metrik Utama SaaS Platform -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <!-- MRR -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Estimasi MRR</span>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center">
                    <x-ui.icon name="coins" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-lg sm:text-2xl font-black text-zinc-950 num">
                Rp{{ number_format($mrr, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-zinc-500 mt-1">Pendapatan langganan berulang / bln</p>
        </div>

        <!-- Total Tenant -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Barbershop</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <x-ui.icon name="users" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-lg sm:text-2xl font-black text-zinc-950 num">
                {{ number_format($totalTenants) }}
            </div>
            <div class="flex items-center gap-2 text-[11px] mt-1 font-semibold">
                <span class="text-emerald-600">{{ $activeTenants }} aktif</span>
                <span class="text-zinc-300">·</span>
                <span class="text-amber-600">{{ $trialTenants }} trial</span>
            </div>
        </div>

        <!-- Total Cabang Outlet -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Cabang / Outlet</span>
                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <x-ui.icon name="store" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-lg sm:text-2xl font-black text-zinc-950 num">
                {{ number_format($totalBranches) }}
            </div>
            <p class="text-[11px] text-zinc-500 mt-1">Titik outlet fisik terkelola</p>
        </div>

        <!-- Total GMV Transaksi -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between text-zinc-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Volume GMV Platform</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <x-ui.icon name="chart" class="w-4 h-4" />
                </div>
            </div>
            <div class="text-lg sm:text-2xl font-black text-zinc-950 num">
                Rp{{ number_format($totalGmv, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-zinc-500 mt-1">Total omzet seluruh outlet</p>
        </div>
    </div>

    <!-- Grid 2 Kolom: Status Siklus Tenant & Adopsi Paket -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Kartu 1: Status Siklus Tenant -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-black text-zinc-900 tracking-tight flex items-center gap-2">
                    <x-ui.icon name="ticket" class="w-4 h-4 text-amber-600" />
                    <span>Status & Kesehatan Tenant</span>
                </h3>
                <p class="text-xs text-zinc-500 mt-0.5">Komposisi status keaktifan akun barbershop yang terdaftar</p>

                <!-- Progress Bar Visual Segment -->
                @php
                    $activePct = $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100) : 0;
                    $trialPct = $totalTenants > 0 ? round(($trialTenants / $totalTenants) * 100) : 0;
                    $suspendedPct = $totalTenants > 0 ? (100 - $activePct - $trialPct) : 0;
                @endphp
                <div class="mt-5 h-3 w-full rounded-full bg-zinc-100 overflow-hidden flex">
                    <div style="width: {{ $activePct }}%" class="bg-emerald-500" title="Aktif: {{ $activePct }}%"></div>
                    <div style="width: {{ $trialPct }}%" class="bg-amber-400" title="Trial: {{ $trialPct }}%"></div>
                    <div style="width: {{ $suspendedPct }}%" class="bg-rose-400" title="Suspended: {{ $suspendedPct }}%"></div>
                </div>

                <!-- Legend & Detail Count -->
                <div class="grid grid-cols-3 gap-3 mt-4 text-center">
                    <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-100">
                        <div class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-emerald-700">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Aktif Lunas</span>
                        </div>
                        <div class="text-base font-black text-emerald-900 mt-1 num">{{ $activeTenants }}</div>
                        <span class="text-[10px] text-emerald-600 font-semibold">{{ $activePct }}%</span>
                    </div>

                    <div class="p-3 rounded-xl bg-amber-50/60 border border-amber-100">
                        <div class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-amber-700">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Masa Trial</span>
                        </div>
                        <div class="text-base font-black text-amber-900 mt-1 num">{{ $trialTenants }}</div>
                        <span class="text-[10px] text-amber-600 font-semibold">{{ $trialPct }}%</span>
                    </div>

                    <div class="p-3 rounded-xl bg-rose-50/60 border border-rose-100">
                        <div class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-rose-700">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>Ditangguhkan</span>
                        </div>
                        <div class="text-base font-black text-rose-900 mt-1 num">{{ $suspendedTenants }}</div>
                        <span class="text-[10px] text-rose-600 font-semibold">{{ $suspendedPct }}%</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-zinc-100 text-[11px] text-zinc-500 flex items-center justify-between">
                <span>Rasio konversi trial ke aktif diperbarui real-time.</span>
                <a href="{{ route('platform.dashboard') }}" class="font-bold text-amber-600 hover:underline">Kelola Tenant →</a>
            </div>
        </div>

        <!-- Kartu 2: Distribusi Penggunaan Paket SaaS -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-black text-zinc-900 tracking-tight flex items-center gap-2">
                            <x-ui.icon name="coins" class="w-4 h-4 text-amber-600" />
                            <span>Distribusi Paket Berlangganan</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Adopsi tier paket dan perkiraan omzet bulanan tiap paket</p>
                    </div>
                    <a href="{{ route('platform.plans') }}" class="text-xs font-bold text-amber-600 hover:underline">Katalog Paket →</a>
                </div>

                <div class="space-y-2.5">
                    @forelse($plansWithCount as $plan)
                        @php
                            $subCount = $plan->subscriptions_count;
                            $estRev = $subCount * $plan->price_monthly;
                        @endphp
                        <div class="p-3 rounded-xl border border-zinc-100 bg-zinc-50/60 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-xs text-zinc-900">{{ $plan->name }}</h4>
                                    <span class="font-mono text-[10px] text-zinc-400 uppercase font-semibold">({{ $plan->code }})</span>
                                </div>
                                <div class="text-[11px] text-zinc-500 mt-0.5">
                                    Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}/bln · {{ $plan->max_branches ? $plan->max_branches . ' Cabang' : 'Unlimited Cabang' }}
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-xs font-black text-zinc-900 num">
                                    {{ $subCount }} Tenant
                                </div>
                                <div class="text-[11px] text-emerald-600 font-bold num">
                                    Rp{{ number_format($estRev, 0, ',', '.') }}/bln
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-zinc-400">Belum ada paket terdaftar.</div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-100 text-[11px] text-zinc-500 flex items-center justify-between">
                <span>Total estimasi pendapatan paket SaaS:</span>
                <span class="font-black text-zinc-950 num">Rp{{ number_format($mrr, 0, ',', '.') }}/bulan</span>
            </div>
        </div>
    </div>

    <!-- Tren Akuisisi Barbershop Baru (6 Bulan Terakhir) -->
    <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 shadow-2xs mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-black text-zinc-900 tracking-tight flex items-center gap-2">
                    <x-ui.icon name="sparkles" class="w-4 h-4 text-amber-600" />
                    <span>Tren Akuisisi Barbershop Baru</span>
                </h3>
                <p class="text-xs text-zinc-500 mt-0.5">Pertumbuhan jumlah pendaftaran tenant baru per bulan dalam 6 bulan terakhir</p>
            </div>
        </div>

        @if($monthlyGrowth->isEmpty())
            <div class="py-8 text-center text-xs text-zinc-400">Belum ada riwayat data bulanan.</div>
        @else
            @php
                $maxCount = max($monthlyGrowth->pluck('count')->max() ?? 1, 1);
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($monthlyGrowth as $item)
                    @php
                        $percentage = round(($item->count / $maxCount) * 100);
                        $dateObj = \Carbon\Carbon::createFromFormat('Y-m', $item->month);
                    @endphp
                    <div class="p-3.5 rounded-xl border border-zinc-100 bg-zinc-50/50 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                                {{ $dateObj ? $dateObj->translatedFormat('M Y') : $item->month }}
                            </span>
                            <div class="text-xl font-black text-zinc-950 mt-1 num">
                                +{{ $item->count }}
                            </div>
                            <span class="text-[10px] text-zinc-500">Tenant Baru</span>
                        </div>

                        <!-- Bar visual -->
                        <div class="mt-4">
                            <div class="h-2 w-full rounded-full bg-zinc-200/70 overflow-hidden">
                                <div style="width: {{ max($percentage, 8) }}%" class="h-full bg-amber-500 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
