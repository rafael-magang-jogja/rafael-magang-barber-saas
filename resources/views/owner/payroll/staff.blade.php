<x-layouts.app title="Gaji per Karyawan & Kenaikan Gaji" subtitle="Atur penyesuaian gaji pokok dan komisi khusus per karyawan" role="owner">
    @php
        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $hasCommissionAuto = $tenant?->hasFeature('commission_auto') ?? false;

        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $roleFilterOptions = [
            ['value' => 'barber', 'label' => 'Staff Barber'],
            ['value' => 'admin', 'label' => 'Admin / Kasir'],
        ];

        $schemeSelectOptions = $salarySchemes->map(fn ($sc) => [
            'value' => (string) $sc->id,
            'label' => $sc->name,
            'sublabel' => $sc->type === 'commission_only' ? 'Bagi Hasil: ' . $sc->effective_commission_percentage . '%' : 'Gaji Standar: Rp' . number_format($sc->base_salary, 0, ',', '.'),
        ])->all();

        $schemesJsonData = [];
        foreach ($salarySchemes as $sc) {
            $schemesJsonData[$sc->id] = [
                'id' => $sc->id,
                'name' => $sc->name,
                'type' => $sc->type,
                'base_salary' => (int) $sc->base_salary,
                'commission_percentage' => (int) $sc->effective_commission_percentage,
            ];
        }
    @endphp

    <!-- Sub Navigasi Modul Gaji & Komisi -->
    <x-payroll.nav active="staff" />

    <!-- Stat Cards Ringkasan Gaji Karyawan (Super Rapi: 1 Kolom di Mobile, 2 Kolom di Tablet, 4 Kolom di Desktop) -->
    <div class="stat-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5 mb-5 transition-all duration-500 ease-in-out">
        <!-- 1. Total Beban Gaji Pokok (Primary Dark Card) -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-950 text-white border border-zinc-800 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-zinc-400 truncate">Total Beban Gaji Pokok</p>
                <span class="w-7 h-7 rounded-xl bg-amber-500/15 text-amber-400 grid place-items-center shrink-0">
                    <x-ui.icon name="banknote" class="w-4 h-4 text-amber-400" />
                </span>
            </div>
            <div class="mt-2.5">
                <p class="text-xl sm:text-2xl font-black text-white num leading-tight truncate">
                    Rp{{ number_format($totalBaseSalary, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-emerald-400 font-medium mt-0.5 truncate flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                    <span>Beban tetap per bulan</span>
                </p>
            </div>
        </div>

        <!-- 2. Kenaikan / Gaji Khusus -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out {{ $customSalaryCount > 0 ? 'ring-2 ring-amber-500/30' : '' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-zinc-600 truncate">Kenaikan / Gaji Khusus</p>
                <span class="w-7 h-7 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0 border border-amber-200/60">
                    <x-ui.icon name="sliders" class="w-4 h-4 text-amber-600" />
                </span>
            </div>
            <div class="mt-2.5">
                <p class="text-xl sm:text-2xl font-black text-zinc-900 num leading-tight truncate">
                    {{ $customSalaryCount }} <span class="text-xs sm:text-sm font-bold text-zinc-500">dari {{ $memberships->count() }} staf</span>
                </p>
                <p class="text-[11px] text-amber-700 font-medium mt-0.5 truncate flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                    <span>Penyesuaian di luar standar</span>
                </p>
            </div>
        </div>

        <!-- 3. Rata-rata Gaji Pokok -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-zinc-600 truncate">Rata-rata Gaji Pokok</p>
                <span class="w-7 h-7 rounded-xl bg-sky-50 text-sky-600 grid place-items-center shrink-0 border border-sky-200/60">
                    <x-ui.icon name="coins" class="w-4 h-4 text-sky-600" />
                </span>
            </div>
            <div class="mt-2.5">
                <p class="text-xl sm:text-2xl font-black text-zinc-900 num leading-tight truncate">
                    Rp{{ number_format($avgBaseSalary, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-zinc-400 font-medium mt-0.5 truncate flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-300 shrink-0"></span>
                    <span>Rata-rata per karyawan</span>
                </p>
            </div>
        </div>

        <!-- 4. Total Staf Aktif -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-zinc-600 truncate">Total Staf Aktif</p>
                <span class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0 border border-emerald-200/60">
                    <x-ui.icon name="users" class="w-4 h-4 text-emerald-600" />
                </span>
            </div>
            <div class="mt-2.5">
                <p class="text-xl sm:text-2xl font-black text-zinc-900 num leading-tight truncate">
                    {{ $memberships->count() }} <span class="text-xs sm:text-sm font-bold text-zinc-500">orang</span>
                </p>
                <p class="text-[11px] text-zinc-400 font-medium mt-0.5 truncate flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    <span>Terdaftar di sistem</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Toolbar Filter & Pencarian -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" action="{{ route('owner.payroll.staff') }}" class="staff-filter-form flex flex-wrap items-center gap-2.5 flex-1 w-full transition-all duration-500 ease-in-out">
            <div class="staff-search-wrapper relative w-full sm:w-64 lg:w-72 xl:w-80 transition-all duration-500 ease-in-out">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama karyawan..."
                    class="w-full text-xs font-semibold pl-8 pr-3 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 shadow-2xs transition-all duration-500 ease-in-out"
                />
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-400">
                    <x-ui.icon name="search" class="w-3.5 h-3.5" />
                </div>
            </div>

            <div class="staff-filter-controls w-full sm:w-auto flex flex-wrap items-center gap-2 transition-all duration-500 ease-in-out">
                <x-ui.select
                    name="branch_id"
                    size="sm"
                    placeholder="Semua Cabang"
                    icon="store"
                    :options="$branchFilterOptions"
                    :value="request('branch_id')"
                    search-placeholder="Cari cabang..."
                    class="w-full sm:w-40 lg:w-44"
                    auto-submit
                />

                <x-ui.select
                    name="role"
                    size="sm"
                    placeholder="Semua Role"
                    icon="users"
                    :options="$roleFilterOptions"
                    :value="request('role')"
                    class="w-full sm:w-36 lg:w-40"
                    auto-submit
                />

                <div class="staff-action-buttons w-full sm:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <x-ui.button type="submit" size="sm" variant="secondary" class="w-full sm:w-auto justify-center">
                        <x-ui.icon name="filter" class="w-3.5 h-3.5" />
                        <span>Filter</span>
                    </x-ui.button>

                    @if(request()->hasAny(['search', 'branch_id', 'role']))
                        <a
                            href="{{ route('owner.payroll.staff') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 sm:bg-transparent sm:hover:bg-transparent border border-rose-200/80 sm:border-transparent py-2 sm:py-1.5 px-3 rounded-xl transition"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                            <span>Reset</span>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Gaji per Karyawan -->
    <x-ui.card class="overflow-hidden">
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <h2 class="font-bold text-sm flex items-center gap-2">
                    <x-ui.icon name="users" class="w-4 h-4 text-amber-600" />
                    <span>Daftar Gaji Pokok &amp; Penyesuaian Karyawan</span>
                </h2>
                <span class="text-xs text-zinc-400 font-medium">{{ $memberships->count() }} Karyawan Aktif</span>
            </div>
        </x-slot:header>

        @if($memberships->isEmpty())
            <x-ui.empty-state title="Tidak ada karyawan yang sesuai filter" />
        @else
            <!-- 1. Tampilan Desktop (Table View) -->
            <div class="hidden lg:block overflow-x-auto rounded-xl border border-zinc-200/80">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500">
                            <th class="py-3 px-4">Karyawan</th>
                            <th class="py-3 px-4">Role &amp; Cabang</th>
                            <th class="py-3 px-4">Skema Jabatan</th>
                            <th class="py-3 px-4 text-right">Gaji Pokok Efektif</th>
                            <th class="py-3 px-4">Komisi Pangkas</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 text-xs">
                        @foreach($memberships as $m)
                            @php
                                $effectiveSalary = $m->effective_base_salary;
                                $isCustom = $m->custom_base_salary !== null;
                                $schemeStandard = (int) ($m->salaryScheme?->base_salary ?? 0);
                                $diff = $effectiveSalary - $schemeStandard;
                                $customComm = $individualCommissions->get($m->id);
                            @endphp
                            <tr class="hover:bg-zinc-50/80 transition-all duration-500 ease-in-out">
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-zinc-900 leading-snug">{{ $m->display_name ?? $m->user?->name }}</p>
                                    <p class="text-[11px] text-zinc-400 mt-0.5">{{ $m->user?->email }} · {{ $m->user?->phone }}</p>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ $m->role->value === 'barber' ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-900' }}">
                                        {{ $m->role->value === 'barber' ? 'Barber' : 'Admin / Kasir' }}
                                    </span>
                                    <p class="text-[11px] text-zinc-500 font-medium mt-1">{{ $m->branch?->name ?? 'Semua Cabang' }}</p>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 text-[11px] font-semibold">
                                        {{ $m->salaryScheme?->name ?? 'Belum ada skema' }}
                                    </span>
                                    <p class="text-[11px] text-zinc-400 mt-1">Standar: Rp{{ number_format($schemeStandard, 0, ',', '.') }}</p>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <p class="font-extrabold text-sm num text-zinc-950">
                                        Rp{{ number_format($effectiveSalary, 0, ',', '.') }}
                                    </p>
                                    @if($isCustom)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-1.5 py-0.2 rounded-md mt-1">
                                            <span>Kenaikan Khusus</span>
                                            @if($diff > 0)
                                                <span>(+Rp{{ number_format($diff, 0, ',', '.') }})</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-[10px] text-zinc-400 font-medium block mt-1">Standar Skema</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($m->role->value === 'barber')
                                        @if(! $hasCommissionAuto)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-500 text-[11px] font-semibold">
                                                Rp0 (Nonaktif di Starter)
                                            </span>
                                        @elseif($m->salaryScheme?->type === 'commission_only')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-[11px] font-bold">
                                                <span>Bagi Hasil {{ $m->salaryScheme->effective_commission_percentage }}%</span>
                                            </span>
                                            <p class="text-[10px] text-zinc-400 mt-1">Skema Jabatan</p>
                                        @else
                                            <span class="text-[11px] text-zinc-600 font-medium">Standar per Layanan</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-300 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <button
                                        type="button"
                                        onclick="openSalaryAdjustmentModal({
                                            id: {{ $m->id }},
                                            name: '{{ addslashes($m->display_name ?? $m->user?->name) }}',
                                            role: '{{ $m->role->value }}',
                                            branch: '{{ addslashes($m->branch?->name ?? 'Semua Cabang') }}',
                                            scheme_id: {{ $m->salary_scheme_id ?? 'null' }},
                                            scheme_name: '{{ addslashes($m->salaryScheme?->name ?? '') }}',
                                            scheme_base: {{ $schemeStandard }},
                                            effective_base: {{ $effectiveSalary }},
                                            custom_base: {{ $m->custom_base_salary ?? 'null' }}
                                        })"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white text-xs font-bold shadow-2xs transition-all duration-500 ease-in-out cursor-pointer shrink-0"
                                    >
                                        <x-ui.icon name="sliders" class="w-3.5 h-3.5 text-amber-400" />
                                        <span>Atur Gaji &amp; Kenaikan</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- 2. Tampilan Responsif Mobile & Tablet (Grid Card View) -->
            <div class="staff-salary-grid lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-3.5 transition-all duration-500 ease-in-out">
                @foreach($memberships as $m)
                    @php
                        $effectiveSalary = $m->effective_base_salary;
                        $isCustom = $m->custom_base_salary !== null;
                        $schemeStandard = (int) ($m->salaryScheme?->base_salary ?? 0);
                        $diff = $effectiveSalary - $schemeStandard;
                        $customComm = $individualCommissions->get($m->id);
                    @endphp
                    <div class="bg-zinc-50/60 rounded-2xl border border-zinc-200/80 p-3.5 sm:p-4 hover:border-zinc-300 hover:bg-white hover:shadow-md hover:-translate-y-0.5 transition-all duration-500 ease-in-out flex flex-col justify-between gap-3 shadow-2xs">
                        <!-- Card Header: Nama, Role, Cabang, Kontak -->
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-extrabold text-sm text-zinc-950 truncate">
                                        {{ $m->display_name ?? $m->user?->name }}
                                    </h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider shrink-0 {{ $m->role->value === 'barber' ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-900' }}">
                                        {{ $m->role->value === 'barber' ? 'Barber' : 'Admin / Kasir' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 font-medium mt-1">
                                    <x-ui.icon name="store" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                    <span class="truncate">{{ $m->branch?->name ?? 'Semua Cabang' }}</span>
                                </div>
                                <div class="text-[11px] text-zinc-400 mt-0.5 truncate">
                                    {{ $m->user?->email }} · {{ $m->user?->phone }}
                                </div>
                            </div>
                        </div>

                        <!-- Box Finansial: Gaji Pokok & Skema Komisi (2 Kolom Bersih) -->
                        <div class="grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-white border border-zinc-200/80 shadow-2xs items-stretch min-w-0">
                            <!-- Gaji Pokok Efektif -->
                            <div class="flex flex-col justify-center px-1.5 py-1 border-r border-zinc-100 min-w-0">
                                <span class="text-[9px] uppercase font-bold tracking-wider text-zinc-400 mb-0.5 truncate">Gaji Pokok Efektif</span>
                                <p class="font-black text-xs sm:text-sm text-zinc-950 num truncate">
                                    Rp{{ number_format($effectiveSalary, 0, ',', '.') }}
                                </p>
                                @if($isCustom)
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-1.5 py-0.5 rounded-md mt-1 self-start truncate">
                                        <span>Kenaikan</span>
                                        @if($diff > 0)
                                            <span>(+Rp{{ number_format($diff, 0, ',', '.') }})</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-[10px] text-zinc-400 font-medium mt-1 truncate">Standar Skema</span>
                                @endif
                            </div>

                            <!-- Skema Jabatan & Komisi -->
                            <div class="flex flex-col justify-center px-1.5 py-1 min-w-0">
                                <span class="text-[9px] uppercase font-bold tracking-wider text-zinc-400 mb-0.5 truncate">Skema &amp; Komisi</span>
                                <p class="font-bold text-xs text-zinc-800 truncate" title="{{ $m->salaryScheme?->name ?? 'Belum ada skema' }}">
                                    {{ $m->salaryScheme?->name ?? 'Belum ada skema' }}
                                </p>
                                @if($m->role->value === 'barber')
                                    @if(! $hasCommissionAuto)
                                        <span class="text-[10px] text-zinc-500 font-semibold mt-1 truncate">
                                            Rp0 (Nonaktif di Starter)
                                        </span>
                                    @elseif($m->salaryScheme?->type === 'commission_only')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-[10px] font-bold mt-1 self-start truncate">
                                            Bagi Hasil {{ $m->salaryScheme->effective_commission_percentage }}%
                                        </span>
                                    @else
                                        <span class="text-[10px] text-zinc-500 font-medium mt-1 truncate">Standar per Layanan</span>
                                    @endif
                                @else
                                    <span class="text-[10px] text-zinc-400 font-medium mt-1 truncate">Gaji Tetap (Flat)</span>
                                @endif
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <button
                            type="button"
                            onclick="openSalaryAdjustmentModal({
                                id: {{ $m->id }},
                                name: '{{ addslashes($m->display_name ?? $m->user?->name) }}',
                                role: '{{ $m->role->value }}',
                                branch: '{{ addslashes($m->branch?->name ?? 'Semua Cabang') }}',
                                scheme_id: {{ $m->salary_scheme_id ?? 'null' }},
                                scheme_name: '{{ addslashes($m->salaryScheme?->name ?? '') }}',
                                scheme_base: {{ $schemeStandard }},
                                effective_base: {{ $effectiveSalary }},
                                custom_base: {{ $m->custom_base_salary ?? 'null' }}
                            })"
                            class="w-full py-2.5 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white text-xs font-bold shadow-2xs transition-all duration-500 ease-in-out inline-flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <x-ui.icon name="sliders" class="w-3.5 h-3.5 text-amber-400" />
                            <span>Atur Gaji &amp; Kenaikan</span>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <!-- Modal Atur Gaji Pokok & Kenaikan Karyawan -->
    <dialog id="modal-salary-adjustment" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto border border-zinc-200">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80 rounded-t-3xl">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0">
                    <x-ui.icon name="banknote" class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-zinc-900">Atur Gaji &amp; Kenaikan Karyawan</h3>
                    <p class="text-xs text-zinc-500" id="modal-staff-subtitle">-</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-salary-adjustment').close()" class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 grid place-items-center cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form id="form-salary-adjustment" method="POST" action="" class="p-5 sm:p-6 space-y-4">
            @csrf
            <!-- Skema Jabatan Karyawan -->
            <div>
                <x-ui.select
                    id="modal-select-scheme-wrapper"
                    label="Skema Jabatan (Level / Golongan) *"
                    name="salary_scheme_id"
                    icon="sliders"
                    :options="$schemeSelectOptions"
                    required
                />
                <p class="text-[11px] text-zinc-400 mt-1">Mengubah skema jabatan dapat digunakan saat karyawan naik level / promosi.</p>
            </div>

            <!-- Mode Penetapan Gaji Pokok -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-zinc-700">Penetapan Gaji Pokok</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40">
                        <input
                            type="radio"
                            name="salary_mode"
                            value="standard"
                            id="mode-standard"
                            onchange="toggleSalaryMode(this.value)"
                            class="text-amber-600 focus:ring-amber-500"
                        />
                        <div class="text-xs">
                            <span class="font-extrabold block text-zinc-900">Standar Skema</span>
                            <span class="text-[11px] text-zinc-500" id="label-standard-amount">Rp0</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40">
                        <input
                            type="radio"
                            name="salary_mode"
                            value="custom"
                            id="mode-custom"
                            onchange="toggleSalaryMode(this.value)"
                            class="text-amber-600 focus:ring-amber-500"
                        />
                        <div class="text-xs">
                            <span class="font-extrabold block text-zinc-900">Kenaikan / Khusus</span>
                            <span class="text-[11px] text-zinc-500">Nominal individual</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Input Nominal Gaji Pokok Khusus (Jika Mode Custom Dipilih) -->
            <div id="container-custom-salary" class="p-3.5 rounded-2xl bg-amber-50/50 border border-amber-200/80 space-y-2.5">
                <label class="block text-xs font-bold text-amber-950">Nominal Gaji Pokok Baru (Kenaikan Gaji) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="custom_base_salary"
                        id="input-custom-base"
                        placeholder="Contoh: 2.800.000"
                        data-currency-input
                        oninput="formatCurrencyInput(this)"
                        class="w-full text-xs font-black pl-9 pr-3 py-2.5 rounded-xl bg-white border border-amber-300 text-zinc-950 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                    />
                </div>
                <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                    <span class="text-[10px] font-bold text-zinc-400">Cepat Tambah:</span>
                    <button type="button" onclick="addIncreaseAmount(100000)" class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-100 cursor-pointer">+100rb</button>
                    <button type="button" onclick="addIncreaseAmount(250000)" class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-100 cursor-pointer">+250rb</button>
                    <button type="button" onclick="addIncreaseAmount(500000)" class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-100 cursor-pointer">+500rb</button>
                    <button type="button" onclick="addIncreasePercent(10)" class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-100 cursor-pointer">+10%</button>
                </div>
            </div>

            <!-- Pengaturan Komisi Pangkas Layanan (Otomatis Sesuai Skema Jabatan) -->
            <div id="container-barber-commission" class="hidden space-y-2 pt-2 border-t border-zinc-100 transition-all duration-500 ease-in-out">
                <label class="block text-xs font-bold text-zinc-700">Skema Komisi Layanan Barber</label>

                @if(! $hasCommissionAuto)
                    <div class="p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-amber-950 flex items-start gap-2.5 text-xs">
                        <x-ui.icon name="lock" class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <span class="text-[11px] leading-snug">Transaksi baru tidak memotong komisi staf (100% omzet toko). Komisi staf sebelum paket Starter tetap tersimpan dan wajib dibayarkan di payroll.</span>
                    </div>
                @endif

                <!-- 1. Tampilan Khusus Skema Bagi Hasil (commission_only): Persentase fix dari skema, tidak bisa diubah di sini -->
                <div id="section-comm-only" class="hidden p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 flex items-center justify-between gap-3 shadow-2xs">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 text-xs font-extrabold text-amber-950">
                            <x-ui.icon name="percent" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Bagi Hasil Omzet Pangkas</span>
                        </div>
                        <p class="text-[11px] text-amber-800 font-medium mt-0.5">
                            Persentase otomatis dari skema jabatan &amp; tidak dapat diubah di sini.
                        </p>
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-amber-500 text-zinc-950 font-black text-sm shrink-0 shadow-2xs">
                        <span id="label-scheme-commission-percent">30</span>%
                    </div>
                </div>

                <!-- 2. Tampilan Skema Pokok + Komisi (base_plus_commission): Standar tarif komisi per layanan -->
                <div id="section-comm-service" class="hidden p-3 rounded-xl bg-zinc-50 border border-zinc-200 text-xs text-zinc-600 flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-zinc-200/80 text-zinc-700 grid place-items-center shrink-0">
                        <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1 text-[11px]">
                        <span class="font-bold text-zinc-900 block">Komisi Standar Tiap Layanan</span>
                        <span class="text-zinc-500">Mengikuti acuan tarif komisi per layanan yang terdaftar di barbershop.</span>
                    </div>
                </div>
            </div>

            <!-- Catatan Kenaikan Gaji -->
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Catatan Kenaikan / Penyesuaian Gaji</label>
                <input
                    type="text"
                    name="notes"
                    placeholder="Contoh: Kenaikan gaji tahunan 2026 atau evaluasi performa"
                    class="w-full text-xs font-semibold px-3 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 shadow-2xs"
                />
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-salary-adjustment').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="sm" variant="primary">
                    <x-ui.icon name="check" class="w-3.5 h-3.5" />
                    <span>Simpan Penyesuaian Gaji</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <script>
        let currentStaffData = null;

        const salarySchemesData = @json($schemesJsonData);

        function formatCurrencyNumber(val) {
            if (val === null || val === undefined || val === '') return '';
            const num = parseInt(String(val).replace(/\D/g, ''), 10);
            if (isNaN(num)) return '';
            return num.toLocaleString('id-ID');
        }

        function formatCurrencyInput(el) {
            const raw = el.value.replace(/\D/g, '');
            if (!raw) {
                el.value = '';
                return 0;
            }
            const num = parseInt(raw, 10);
            el.value = num.toLocaleString('id-ID');
            return num;
        }

        function getSelectedSchemeId() {
            const input = document.querySelector('#modal-select-scheme-wrapper [data-select-input]');
            return input ? input.value : null;
        }

        function getSelectedSchemeBase() {
            const id = getSelectedSchemeId();
            return id && salarySchemesData[id] ? salarySchemesData[id].base_salary : 0;
        }

        function updateSchemeDetails(schemeId) {
            const scheme = salarySchemesData[schemeId];
            if (!scheme) return;

            // 1. Update nominal acuan standar gaji pokok
            const labelStandard = document.getElementById('label-standard-amount');
            if (labelStandard) {
                labelStandard.textContent = 'Rp' + scheme.base_salary.toLocaleString('id-ID');
            }

            // 2. Kontrol Bagian Komisi Pangkas Layanan (Hanya untuk Barber)
            const barberCommContainer = document.getElementById('container-barber-commission');
            const commOnlySection = document.getElementById('section-comm-only');
            const commServiceSection = document.getElementById('section-comm-service');
            const commPercentDisplay = document.getElementById('label-scheme-commission-percent');

            if (currentStaffData && currentStaffData.role === 'barber') {
                if (scheme.type === 'commission_only') {
                    // Skema Bagi Hasil: Munculkan persentase fix (read-only), tidak bisa diedit di sini
                    barberCommContainer.classList.remove('hidden');
                    commOnlySection.classList.remove('hidden');
                    commServiceSection.classList.add('hidden');
                    if (commPercentDisplay) {
                        commPercentDisplay.textContent = scheme.commission_percentage;
                    }
                } else if (scheme.type === 'base_plus_commission') {
                    // Skema Pokok + Komisi: Sembunyikan persentase, tampilkan info tarif komisi per layanan
                    barberCommContainer.classList.remove('hidden');
                    commOnlySection.classList.add('hidden');
                    commServiceSection.classList.remove('hidden');
                } else {
                    // Skema Fixed (misal kasir / gaji tetap)
                    barberCommContainer.classList.add('hidden');
                    commOnlySection.classList.add('hidden');
                    commServiceSection.classList.add('hidden');
                }
            } else {
                barberCommContainer.classList.add('hidden');
            }
        }

        function openSalaryAdjustmentModal(staff) {
            currentStaffData = staff;
            const modal = document.getElementById('modal-salary-adjustment');
            const form = document.getElementById('form-salary-adjustment');
            const subtitle = document.getElementById('modal-staff-subtitle');
            const modeStandard = document.getElementById('mode-standard');
            const modeCustom = document.getElementById('mode-custom');
            const inputCustomBase = document.getElementById('input-custom-base');

            form.action = `/owner/payroll/staff/${staff.id}/salary`;
            subtitle.textContent = `${staff.name} · ${staff.role.toUpperCase()} (${staff.branch})`;

            if (staff.scheme_id) {
                const opt = document.querySelector('#modal-select-scheme-wrapper [data-select-option][data-value="' + staff.scheme_id + '"]');
                if (opt) opt.click();
            }

            const activeSchemeId = staff.scheme_id || getSelectedSchemeId();
            updateSchemeDetails(activeSchemeId);

            if (staff.custom_base !== null) {
                modeCustom.checked = true;
                inputCustomBase.value = formatCurrencyNumber(staff.custom_base);
                toggleSalaryMode('custom');
            } else {
                modeStandard.checked = true;
                inputCustomBase.value = formatCurrencyNumber(staff.scheme_base || 0);
                toggleSalaryMode('standard');
            }

            modal.showModal();
        }

        document.addEventListener('click', (e) => {
            const opt = e.target.closest('#modal-select-scheme-wrapper [data-select-option]');
            if (opt) {
                setTimeout(() => {
                    const schemeId = getSelectedSchemeId();
                    updateSchemeDetails(schemeId);
                    const inputCustomBase = document.getElementById('input-custom-base');
                    const modeCustom = document.getElementById('mode-custom');
                    if (modeCustom && modeCustom.checked && (!inputCustomBase.value || inputCustomBase.value === '0')) {
                        inputCustomBase.value = formatCurrencyNumber(getSelectedSchemeBase());
                    }
                }, 50);
            }
        });

        function toggleSalaryMode(mode) {
            const container = document.getElementById('container-custom-salary');
            const input = document.getElementById('input-custom-base');
            if (mode === 'custom') {
                container.classList.remove('hidden');
                container.classList.add('animate-fade-in');
                input.required = true;
                if (!input.value || input.value === '0') {
                    input.value = formatCurrencyNumber(getSelectedSchemeBase());
                }
            } else {
                container.classList.add('hidden');
                container.classList.remove('animate-fade-in');
                input.required = false;
            }
        }

        function addIncreaseAmount(amount) {
            const input = document.getElementById('input-custom-base');
            const current = parseInt((input.value || '').replace(/\D/g, '') || '0', 10);
            input.value = formatCurrencyNumber(current + amount);
        }

        function addIncreasePercent(percent) {
            const input = document.getElementById('input-custom-base');
            const current = parseInt((input.value || '').replace(/\D/g, '') || '0', 10);
            input.value = formatCurrencyNumber(Math.round(current * (1 + (percent / 100))));
        }

        // Unformat currency inputs on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-currency-input]').forEach(inp => {
                    inp.value = inp.value.replace(/\D/g, '') || '0';
                });
            });
        });

        // Tutup modal saat klik backdrop
        const staffModal = document.getElementById('modal-salary-adjustment');
        if (staffModal) {
            staffModal.addEventListener('click', (e) => {
                const rect = staffModal.getBoundingClientRect();
                const isInDialog = (
                    e.clientX >= rect.left &&
                    e.clientX <= rect.right &&
                    e.clientY >= rect.top &&
                    e.clientY <= rect.bottom
                );
                if (!isInDialog) {
                    staffModal.close();
                }
            });
        }
    </script>

    <style>
        @keyframes modalSectionFadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: modalSectionFadeIn 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
    </style>
</x-layouts.app>
