<x-layouts.app title="Standar Gaji Jabatan & Komisi Layanan" subtitle="Atur standar gaji pokok jabatan, bagi hasil mitra, dan komisi layanan" role="owner">
    @php
        $schemeTypeOptions = [
            [
                'value' => 'base_plus_commission',
                'label' => 'Gaji Pokok + Komisi Pangkas (Standar Barber Tetap)',
                'sublabel' => 'Menerima gaji pokok bulanan, komisi per layanan, dan opsi bonus target pangkas',
            ],
            [
                'value' => 'fixed',
                'label' => 'Gaji Pokok Tetap Tanpa Komisi (Standar Kasir / Petugas)',
                'sublabel' => 'Penghasilan tetap flat per bulan tanpa hitungan komisi layanan',
            ],
            [
                'value' => 'commission_only',
                'label' => 'Murni Bagi Hasil Komisi (Mitra Lepas / Freelance)',
                'sublabel' => 'Tanpa gaji pokok, penghasilan murni persentase bagi hasil dari transaksi layanan',
            ],
        ];

        $schemesJson = $schemes->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'base_salary' => (int) $s->base_salary,
                'alpha_penalty_nominal' => (int) ($s->alpha_penalty_nominal ?? 0),
                'commission_percentage' => (int) $s->effective_commission_percentage,
                'has_percentage_commission' => (bool) $s->has_percentage_commission,
                'bonus_tiers' => $s->bonus_tiers,
                'memberships_count' => (int) $s->memberships_count,
            ];
        })->values();

        $defaultSimSchemeId = (string) ($schemes->first()?->id ?? '');

        $simSchemeOptions = $schemes->map(fn ($sc) => [
            'value' => (string) $sc->id,
            'label' => $sc->name,
            'sublabel' => $sc->type === 'commission_only'
                ? ($sc->has_percentage_commission ? 'Bagi Hasil: ' . $sc->effective_commission_percentage . '%' : 'Komisi Layanan (per Kepala)')
                : ($sc->type === 'fixed'
                    ? 'Gaji Tetap: Rp' . number_format($sc->base_salary, 0, ',', '.')
                    : 'Gaji Pokok: Rp' . number_format($sc->base_salary, 0, ',', '.') . ' + Komisi'),
        ])->all();

        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $hasCommissionAuto = $tenant?->hasFeature('commission_auto') ?? false;
        $plan = $tenant?->plan();
        $planName = $plan?->name ?? 'Starter';
    @endphp

    <!-- Sub Navigasi Modul Gaji & Komisi -->
    <x-payroll.nav active="rules" />

    <div class="space-y-8">
        <!-- Ringkasan Alur Penggajian Full Kanan Kiri & Super Responsive -->
        <div class="p-3 sm:p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 space-y-2.5 w-full">
            <div class="flex items-center gap-2 px-0.5">
                <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 shadow-2xs">
                    <x-ui.icon name="coins" class="w-3.5 h-3.5" />
                </div>
                <h3 class="font-extrabold text-xs sm:text-sm text-zinc-900 leading-tight">Alur Penggajian Barbershop</h3>
            </div>

            <!-- 3 Langkah Full Kanan Kiri -->
            <div class="grid grid-cols-3 gap-1.5 sm:gap-2.5 w-full items-stretch">
                <!-- 1. Standar Jabatan (Halaman Aktif) -->
                <div class="px-2 sm:px-3.5 py-2 sm:py-2.5 rounded-xl bg-white border border-amber-300 shadow-2xs flex items-center justify-between min-w-0 transition">
                    <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0 truncate">
                        <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-amber-500 text-zinc-950 text-[10px] sm:text-xs font-black grid place-items-center shrink-0 shadow-2xs">1</span>
                        <div class="truncate text-[11px] sm:text-xs text-zinc-800">
                            <!-- Desktop: Full Text -->
                            <span class="hidden xl:inline"><strong>Standar Jabatan:</strong> Acuan Pokok &amp; Bagi Hasil</span>
                            <!-- Tablet: Minimalist Text -->
                            <span class="hidden sm:inline xl:hidden font-bold">Standar Jabatan</span>
                            <!-- Mobile: Super Minimalist -->
                            <span class="sm:hidden font-bold">Jabatan</span>
                        </div>
                    </div>
                    <span class="text-zinc-300 font-bold text-xs shrink-0 hidden md:inline ml-1">&rarr;</span>
                </div>

                <!-- 2. Komisi Layanan (Scroll ke Bagian Komisi) -->
                <a
                    href="#section-service-commissions"
                    class="px-2 sm:px-3.5 py-2 sm:py-2.5 rounded-xl bg-white hover:bg-amber-50/50 border border-amber-200/80 hover:border-amber-300 shadow-2xs flex items-center justify-between min-w-0 transition cursor-pointer group"
                >
                    <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0 truncate">
                        <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-amber-500 text-zinc-950 text-[10px] sm:text-xs font-black grid place-items-center shrink-0 shadow-2xs">2</span>
                        <div class="truncate text-[11px] sm:text-xs text-zinc-800 group-hover:text-zinc-950">
                            <!-- Desktop: Full Text -->
                            <span class="hidden xl:inline"><strong>Komisi Layanan:</strong> Tarif per Pangkas</span>
                            <!-- Tablet: Minimalist Text -->
                            <span class="hidden sm:inline xl:hidden font-bold">Komisi Layanan</span>
                            <!-- Mobile: Super Minimalist -->
                            <span class="sm:hidden font-bold">Komisi</span>
                        </div>
                    </div>
                    <span class="text-zinc-300 group-hover:text-amber-500 font-bold text-xs shrink-0 hidden md:inline ml-1 transition">&rarr;</span>
                </a>

                <!-- 3. Gaji per Karyawan (Link ke Staff Payroll) -->
                <a
                    href="{{ route('owner.payroll.staff') }}"
                    class="px-2 sm:px-3.5 py-2 sm:py-2.5 rounded-xl bg-white hover:bg-amber-50/60 border border-amber-200/80 hover:border-amber-400 shadow-2xs flex items-center justify-between min-w-0 transition group cursor-pointer"
                >
                    <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0 truncate">
                        <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-amber-500 text-zinc-950 text-[10px] sm:text-xs font-black grid place-items-center shrink-0 shadow-2xs">3</span>
                        <div class="truncate text-[11px] sm:text-xs text-zinc-800 group-hover:text-zinc-950">
                            <!-- Desktop: Full Text -->
                            <span class="hidden xl:inline"><strong>Gaji Karyawan:</strong> Penyesuaian Khusus</span>
                            <!-- Tablet: Minimalist Text -->
                            <span class="hidden sm:inline xl:hidden font-bold">Gaji Karyawan</span>
                            <!-- Mobile: Super Minimalist -->
                            <span class="sm:hidden font-bold">Karyawan</span>
                        </div>
                    </div>
                    <span class="text-zinc-400 group-hover:text-amber-600 font-bold text-xs shrink-0 transition ml-1">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- BAGIAN 1: Skema Jabatan & Acuan Gaji Pokok -->
        <section class="space-y-4">
            <div class="schemes-header-container flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all duration-300">
                <div>
                    <h2 class="font-extrabold text-base text-zinc-950 flex items-center gap-2">
                        <x-ui.icon name="briefcase" class="w-5 h-5 text-amber-600" />
                        <span>Skema Jabatan &amp; Standar Penghasilan</span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Atur acuan gaji bulanan dan persentase bagi hasil per jabatan.
                    </p>
                </div>
                <button
                    type="button"
                    onclick="openAddSchemeModal()"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-extrabold shadow-sm transition cursor-pointer shrink-0"
                >
                    <x-ui.icon name="plus" class="w-4 h-4" />
                    <span>Tambah Jabatan / Skema Baru</span>
                </button>
            </div>

            <div class="schemes-cards-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-3.5 sm:gap-4 transition-all duration-500 ease-in-out">
                @foreach($schemes as $scheme)
                    @php
                        $isCommissionOnly = $scheme->type === 'commission_only';
                        $isFixed = $scheme->type === 'fixed';
                        $isBasePlusComm = $scheme->type === 'base_plus_commission';
                        $hasPercentage = $scheme->has_percentage_commission;

                        $typeLabel = match($scheme->type) {
                            'fixed' => 'Gaji Pokok',
                            'commission_only' => $hasPercentage ? 'Bagi Hasil' : 'Komisi Layanan',
                            default => 'Pokok + Komisi',
                        };
                        $typeBadgeColor = match($scheme->type) {
                            'fixed' => 'bg-sky-50 text-sky-700 border-sky-200/80',
                            'commission_only' => $hasPercentage ? 'bg-purple-50 text-purple-700 border-purple-200/80' : 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
                            default => 'bg-amber-50 text-amber-700 border-amber-200/80',
                        };

                        $bonusTiers = $scheme->bonus_tiers;
                    @endphp
                    <div class="bg-white rounded-2xl border border-zinc-200/80 p-4 sm:p-5 shadow-2xs hover:shadow-md hover:border-zinc-300 hover:-translate-y-0.5 transition-all duration-500 ease-in-out flex flex-col justify-between space-y-4">
                        <div class="space-y-3">
                            <!-- Card Header: Title & Badge -->
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-extrabold text-sm text-zinc-900 leading-snug truncate" title="{{ $scheme->name }}">
                                    {{ $scheme->name }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-lg border text-[10px] font-bold shrink-0 {{ $typeBadgeColor }}">
                                    {{ $typeLabel }}
                                </span>
                            </div>

                            <!-- Nilai Utama Sesuai Tipe Skema -->
                            @if($isCommissionOnly)
                                @if($hasPercentage)
                                    <div>
                                        <p class="text-[11px] font-medium text-zinc-400">Bagi Hasil Komisi</p>
                                        <div class="flex items-baseline gap-1 mt-0.5">
                                            <p class="text-2xl font-black text-zinc-950 num">
                                                {{ $scheme->effective_commission_percentage }}%
                                            </p>
                                            <span class="text-xs font-semibold text-zinc-400">omzet pangkas</span>
                                        </div>
                                        <p class="text-[11px] text-zinc-500 mt-2 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500 shrink-0"></span>
                                            <span>Murni bagi hasil omzet (Gaji pokok Rp0)</span>
                                        </p>
                                    </div>
                                @else
                                    <div>
                                        <p class="text-[11px] font-medium text-zinc-400">Komisi per Layanan</p>
                                        <div class="flex items-baseline gap-1.5 mt-0.5">
                                            <p class="text-xl sm:text-2xl font-black text-emerald-700">
                                                Tarif Layanan
                                            </p>
                                            <span class="text-xs font-semibold text-zinc-400">per kepala pangkas</span>
                                        </div>
                                        <p class="text-[11px] text-zinc-500 mt-2 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                            <span>Murni komisi layanan (Gaji pokok Rp0)</span>
                                        </p>
                                    </div>
                                @endif

                                <!-- Bonus Target Layanan (jika ada pada commission only) -->
                                @if(!empty($bonusTiers))
                                    @php
                                        $maxBonus = collect($bonusTiers)->max('bonus') ?? 0;
                                        $tierTooltip = collect($bonusTiers)->map(fn($t) => '≥' . ($t['min_services'] ?? 0) . 'x: +Rp' . number_format($t['bonus'] ?? 0, 0, ',', '.'))->join(', ');
                                    @endphp
                                    <div class="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-xl bg-amber-50/70 border border-amber-200/60 mt-2" title="{{ $tierTooltip }}">
                                        <span class="text-[11px] font-bold text-amber-800 flex items-center gap-1.5">
                                            <x-ui.icon name="trending-up" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                            <span>Bonus Target ({{ count($bonusTiers) }} Tier)</span>
                                        </span>
                                        <span class="text-xs font-extrabold text-emerald-700 num">
                                            s/d +Rp{{ number_format($maxBonus, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                            @elseif($isFixed)
                                <div>
                                    <p class="text-[11px] font-medium text-zinc-400">Gaji Pokok Flat</p>
                                    <p class="text-2xl font-black text-zinc-950 num mt-0.5">
                                        Rp{{ number_format($scheme->base_salary, 0, ',', '.') }}
                                        <span class="text-xs font-normal text-zinc-400">/ bulan</span>
                                    </p>
                                    <p class="text-[11px] text-zinc-500 mt-2 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 shrink-0"></span>
                                        <span>Gaji flat tanpa komisi pangkas</span>
                                    </p>
                                </div>
                            @else
                                <div>
                                    <p class="text-[11px] font-medium text-zinc-400">Gaji Pokok</p>
                                    <p class="text-2xl font-black text-zinc-950 num mt-0.5">
                                        Rp{{ number_format($scheme->base_salary, 0, ',', '.') }}
                                        <span class="text-xs font-normal text-zinc-400">/ bulan</span>
                                    </p>
                                </div>

                                <!-- Bonus Target Layanan (jika ada) -->
                                @if(!empty($bonusTiers))
                                    @php
                                        $maxBonus = collect($bonusTiers)->max('bonus') ?? 0;
                                        $tierTooltip = collect($bonusTiers)->map(fn($t) => '≥' . ($t['min_services'] ?? 0) . 'x: +Rp' . number_format($t['bonus'] ?? 0, 0, ',', '.'))->join(', ');
                                    @endphp
                                    <div class="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-xl bg-amber-50/70 border border-amber-200/60" title="{{ $tierTooltip }}">
                                        <span class="text-[11px] font-bold text-amber-800 flex items-center gap-1.5">
                                            <x-ui.icon name="trending-up" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                            <span>Bonus Target ({{ count($bonusTiers) }} Tier)</span>
                                        </span>
                                        <span class="text-xs font-extrabold text-emerald-700 num">
                                            s/d +Rp{{ number_format($maxBonus, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @else
                                    <p class="text-[11px] text-zinc-500 mt-2 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                        <span>Komisi pangkas (tanpa target bonus)</span>
                                    </p>
                                @endif
                            @endif

                        </div>

                        <!-- Card Footer: Anggota Aktif & Tombol Aksi -->
                        <div class="pt-3 border-t border-zinc-100 flex items-center justify-between gap-2">
                            <a
                                href="{{ route('owner.payroll.staff') }}"
                                class="text-xs text-zinc-500 hover:text-amber-600 font-semibold flex items-center gap-1.5 transition group"
                                title="Lihat staf dengan skema ini"
                            >
                                <x-ui.icon name="users" class="w-3.5 h-3.5 text-zinc-400 group-hover:text-amber-500 transition" />
                                <span class="group-hover:underline">{{ $scheme->memberships_count }} Karyawan</span>
                            </a>

                            <div class="flex items-center gap-1.5">
                                @if($scheme->memberships_count === 0)
                                    <form method="POST" action="{{ route('owner.payroll.destroy-scheme', $scheme->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus skema jabatan {{ addslashes($scheme->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-xl text-zinc-400 hover:text-white hover:bg-rose-600 transition cursor-pointer shadow-2xs"
                                            title="Hapus Jabatan"
                                        >
                                            <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                @endif

                                <button
                                    type="button"
                                    data-scheme="{{ json_encode([
                                        'id' => $scheme->id,
                                        'name' => $scheme->name,
                                        'type' => $scheme->type,
                                        'base_salary' => $scheme->base_salary,
                                        'alpha_penalty_nominal' => (int) ($scheme->alpha_penalty_nominal ?? 0),
                                        'commission_percentage' => $scheme->effective_commission_percentage,
                                        'has_percentage_commission' => $scheme->has_percentage_commission,
                                        'bonus_tiers' => $bonusTiers,
                                    ]) }}"
                                    onclick="openEditSchemeModal(JSON.parse(this.dataset.scheme))"
                                    class="px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition cursor-pointer flex items-center gap-1 shadow-2xs"
                                >
                                    <x-ui.icon name="edit" class="w-3 h-3 text-amber-600" />
                                    <span>Atur Skema</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($hasCommissionAuto)
            <!-- FITUR INTERAKTIF: Kalkulator & Simulasi Hasil Penggajian (Khusus Pro & Business) -->
            <section class="bg-white rounded-3xl p-4 sm:p-6 lg:p-7 shadow-xs space-y-5 sm:space-y-6 border border-zinc-200/80">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-100 pb-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/10 border border-amber-300/40 text-amber-800 text-[11px] font-black uppercase tracking-wider mb-1">
                        <x-ui.icon name="sliders" class="w-3.5 h-3.5 text-amber-600" />
                        <span>Kalkulator &amp; Live Preview</span>
                    </div>
                    <h2 class="font-black text-base sm:text-lg text-zinc-950">Simulasi Hasil Penggajian &amp; Bagi Hasil</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Simulasi perkiraan pendapatan staf dan bagi hasil barbershop.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 sm:gap-6 items-start">
                <!-- Sisi Kiri: Kontrol Pengaturan Simulasi -->
                <div class="xl:col-span-5 space-y-4">
                    <!-- Dropdown Menggunakan Komponen Select yang Seragam -->
                    <div id="sim-scheme-select-wrapper">
                        <x-ui.select
                            name="sim_scheme_id"
                            label="Pilih Jabatan / Skema untuk Diuji:"
                            icon="briefcase"
                            :options="$simSchemeOptions"
                            :value="$defaultSimSchemeId"
                            class="w-full"
                        ></x-ui.select>
                    </div>

                    <!-- Slider Perkiraan Jumlah Pangkas -->
                    <div class="p-4 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-zinc-700">Estimasi Jumlah Pangkas:</label>
                            <span class="text-base font-black text-amber-700 num" id="sim-cuts-label">150 Layanan</span>
                        </div>
                        <input
                            type="range"
                            id="sim-cuts-slider"
                            min="0"
                            max="400"
                            step="5"
                            value="150"
                            oninput="updateSimCuts(this.value)"
                            class="w-full accent-amber-500 cursor-pointer"
                        />
                        <div class="flex justify-between text-[10px] text-zinc-400 font-bold">
                            <span>0x (Sepi)</span>
                            <span>150x (Normal)</span>
                            <span>250x (Target)</span>
                            <span>400x (Ramai)</span>
                        </div>
                    </div>

                    <!-- Rata-rata Tarif Layanan (Tanpa Tombol Naik Turun) -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1">Rata-rata Harga Jual Layanan (Rp):</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                id="sim-avg-price"
                                value="50.000"
                                oninput="formatCurrencyInput(this); runPayrollSimulation();"
                                class="w-full text-xs font-black pl-9 pr-3 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-950 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 num"
                            />
                        </div>
                    </div>
                </div>

                <!-- Sisi Kanan: Hasil Live Rincian Pembagian Uang -->
                <div class="xl:col-span-7 sim-results-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-3.5 sm:gap-4 transition-all duration-500 ease-in-out">
                    <!-- Box 1: Take-Home Pay Karyawan (Light Amber) -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-3.5 flex flex-col justify-between shadow-2xs min-w-0">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-800 block">Diterima Karyawan (Staf)</span>
                            <p class="text-2xl sm:text-3xl font-black text-zinc-950 num mt-1 truncate" id="sim-result-staff-total">
                                Rp0
                            </p>
                            <p class="text-xs text-zinc-500 mt-0.5" id="sim-result-staff-type">
                                Estimasi Take-Home Pay
                            </p>
                        </div>

                        <div class="pt-3 border-t border-amber-200/60 space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-2 text-zinc-600">
                                <span class="font-medium text-xs text-zinc-700">Gaji Pokok:</span>
                                <span class="font-bold text-zinc-900 num text-xs shrink-0" id="sim-result-base">Rp0</span>
                            </div>
                            <div class="flex items-start justify-between gap-2 text-zinc-600">
                                <div class="min-w-0">
                                    <span id="sim-result-comm-label" class="font-medium text-xs text-zinc-700 block leading-tight">Komisi Layanan:</span>
                                    <span id="sim-result-comm-sublabel" class="text-[10px] text-zinc-400 block font-medium mt-0.5 truncate">Flat ~Rp5.000 x 150</span>
                                </div>
                                <span class="font-bold text-emerald-700 num text-xs shrink-0 mt-0.5" id="sim-result-comm">Rp0</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-zinc-600">
                                <span class="font-medium text-xs text-zinc-700">Bonus Target:</span>
                                <span class="font-bold text-amber-700 num text-xs shrink-0" id="sim-result-bonus">Rp0</span>
                            </div>
                        </div>

                        <div class="p-2.5 sm:p-3 rounded-xl bg-white border border-amber-200/70 text-xs shadow-2xs leading-relaxed" id="sim-target-status">
                            Status Target: -
                        </div>
                    </div>

                    <!-- Box 2: Bagian Barbershop (Light Emerald) -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50/50 border border-emerald-200/80 space-y-3.5 flex flex-col justify-between shadow-2xs min-w-0">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 block">Sisa Masuk ke Barbershop</span>
                            <p class="text-2xl sm:text-3xl font-black text-emerald-950 num mt-1 truncate" id="sim-result-shop-total">
                                Rp0
                            </p>
                            <p class="text-xs text-emerald-700/80 mt-0.5">
                                Pendapatan Kotor setelah Beban Staf
                            </p>
                        </div>

                        <div class="pt-3 border-t border-emerald-200/60 space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-2 text-zinc-600">
                                <span class="font-medium text-xs text-zinc-700">Total Omzet Cukur:</span>
                                <span class="font-bold text-zinc-900 num text-xs shrink-0" id="sim-result-gross-omzet">Rp0</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-zinc-600">
                                <span class="font-medium text-xs text-zinc-700">Porsi Barbershop:</span>
                                <span class="font-bold text-emerald-800 num text-xs shrink-0" id="sim-result-shop-pct">0%</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-zinc-600">
                                <span class="font-medium text-xs text-zinc-700">Porsi Staf:</span>
                                <span class="font-bold text-amber-800 num text-xs shrink-0" id="sim-result-staff-pct">0%</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-white border border-emerald-200/60 text-[11px] text-zinc-500 flex items-center gap-2 shadow-2xs">
                            <x-ui.icon name="info" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                            <span class="leading-tight">Catatan: Omzet belum termasuk produk &amp; operasional.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif

        <!-- BAGIAN 2: Komisi Default untuk Setiap Layanan -->
        <section id="section-service-commissions" class="space-y-4 pt-4 border-t border-zinc-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-extrabold text-base text-zinc-950 flex items-center gap-2">
                            <x-ui.icon name="scissors" class="w-5 h-5 text-amber-600" />
                            <span>Komisi Default untuk Setiap Layanan</span>
                        </h2>
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Tarif komisi flat atau persentase komisi barber per layanan (per kepala pangkas). Terbuka untuk semua paket.
                    </p>
                </div>
                <span class="text-xs text-zinc-400 font-medium sm:text-right shrink-0">{{ $services->count() }} Layanan Terdaftar</span>
            </div>

            <x-ui.card class="overflow-hidden">
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900">Daftar Komisi Layanan Barbershop</h3>
                            <p class="text-[11px] text-zinc-400 font-medium sm:hidden mt-0.5">{{ $services->count() }} Layanan Terdaftar</p>
                        </div>
                        <span class="text-xs text-zinc-400 font-medium hidden sm:inline">{{ $services->count() }} Layanan Terdaftar</span>
                    </div>
                </x-slot:header>

                <!-- 1. Tampilan Desktop & Tablet (Table View Bersih & Rapi Anti-Berdempetan) -->
                <div class="hidden md:block overflow-x-auto rounded-xl border border-zinc-200/80">
                    <table class="w-full min-w-[580px] text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500">
                                <th class="py-3 px-3.5 sm:px-4">Layanan &amp; Kategori</th>
                                <th class="py-3 px-3 sm:px-4 text-right">Harga Jual</th>
                                <th class="py-3 px-3 sm:px-4 text-center">Komisi Barber</th>
                                <th class="py-3 px-3 sm:px-4 text-right">Bagian Toko</th>
                                <th class="py-3 px-3.5 sm:px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 text-xs">
                            @forelse($services as $srv)
                                @php
                                    $rule = $serviceRules->get($srv->id);
                                    $commType = $rule ? $rule->type : 'flat';
                                    $commVal = $rule ? (int) $rule->value : 5000;

                                    if ($commType === 'percentage') {
                                        $barberShare = (int) round(($srv->price * $commVal) / 100);
                                        $shopShare = max(0, $srv->price - $barberShare);
                                        $barberPct = $commVal;
                                        $shopPct = max(0, 100 - $commVal);
                                        $displayComm = $commVal . '% (~Rp' . number_format($barberShare, 0, ',', '.') . ')';
                                    } else {
                                        $barberShare = $commVal;
                                        $shopShare = max(0, $srv->price - $barberShare);
                                        $barberPct = $srv->price > 0 ? (int) round(($barberShare / $srv->price) * 100) : 0;
                                        $shopPct = max(0, 100 - $barberPct);
                                        $displayComm = 'Rp' . number_format($commVal, 0, ',', '.') . ' / pangkas';
                                    }
                                @endphp
                                <tr class="hover:bg-zinc-50/80 transition-all duration-300">
                                    <td class="py-3 sm:py-3.5 px-3.5 sm:px-4">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-extrabold text-xs sm:text-sm text-zinc-900 leading-snug">{{ $srv->name }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-600 text-[10px] font-bold shrink-0">
                                                {{ $srv->category?->name ?? 'Umum' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 mt-0.5">
                                            <x-ui.icon name="clock" class="w-3 h-3 text-zinc-400 shrink-0" />
                                            <span>~{{ $srv->duration_minutes }} menit</span>
                                        </div>
                                    </td>
                                    <td class="py-3 sm:py-3.5 px-3 sm:px-4 text-right whitespace-nowrap">
                                        <span class="font-extrabold text-xs sm:text-sm num text-zinc-900">
                                            Rp{{ number_format($srv->price, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="py-3 sm:py-3.5 px-3 sm:px-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl font-extrabold text-xs {{ $commType === 'percentage' ? 'bg-purple-50 text-purple-800 border border-purple-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $commType === 'percentage' ? 'bg-purple-500' : 'bg-emerald-500' }}"></span>
                                            <span class="num">{{ $displayComm }}</span>
                                        </span>
                                    </td>
                                    <td class="py-3 sm:py-3.5 px-3 sm:px-4 text-right whitespace-nowrap">
                                        <span class="font-extrabold text-xs num text-zinc-800 block">
                                            Rp{{ number_format($shopShare, 0, ',', '.') }}
                                        </span>
                                        <span class="text-[11px] text-zinc-400 font-semibold num block">
                                            {{ $shopPct }}% omzet
                                        </span>
                                    </td>
                                    <td class="py-3 sm:py-3.5 px-3.5 sm:px-4 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            data-commission="{{ json_encode([
                                                'id' => $srv->id,
                                                'name' => $srv->name,
                                                'price' => $srv->price,
                                                'comm_type' => $commType,
                                                'comm_val' => $commVal,
                                            ]) }}"
                                            onclick="openEditCommissionModal(JSON.parse(this.dataset.commission))"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white text-xs font-bold shadow-2xs transition cursor-pointer shrink-0"
                                        >
                                            <x-ui.icon name="sliders" class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                                            <span>Ubah Komisi</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-zinc-400">
                                        Belum ada layanan terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- 2. Tampilan Responsif Mobile (Card View Bersih 1 Kolom) -->
                <div class="service-commissions-grid md:hidden grid grid-cols-1 gap-3.5 transition-all duration-500 ease-in-out">
                    @forelse($services as $srv)
                        @php
                            $rule = $serviceRules->get($srv->id);
                            $commType = $rule ? $rule->type : 'flat';
                            $commVal = $rule ? (int) $rule->value : 5000;

                            if ($commType === 'percentage') {
                                $barberShare = (int) round(($srv->price * $commVal) / 100);
                                $shopShare = max(0, $srv->price - $barberShare);
                                $barberPct = $commVal;
                                $shopPct = max(0, 100 - $commVal);
                            } else {
                                $barberShare = $commVal;
                                $shopShare = max(0, $srv->price - $barberShare);
                                $barberPct = $srv->price > 0 ? (int) round(($barberShare / $srv->price) * 100) : 0;
                                $shopPct = max(0, 100 - $barberPct);
                            }
                        @endphp
                        <div class="bg-zinc-50/60 rounded-2xl border border-zinc-200/80 p-3.5 sm:p-4 hover:border-zinc-300 hover:bg-white hover:shadow-md hover:-translate-y-0.5 transition-all duration-500 ease-in-out flex flex-col justify-between gap-3 shadow-2xs">
                            <!-- Card Header: Nama & Kategori -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-extrabold text-sm text-zinc-950 truncate">
                                            {{ $srv->name }}
                                        </h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-200/70 text-zinc-700 text-[10px] font-bold shrink-0">
                                            {{ $srv->category?->name ?? 'Umum' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-zinc-400 mt-1">
                                        <x-ui.icon name="clock" class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>~{{ $srv->duration_minutes }} menit</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Metrik Keuangan 3 Kolom (Anti-Berdepetan dengan min-w-0 & truncate) -->
                            <div class="grid grid-cols-3 gap-1.5 p-2 rounded-xl bg-white border border-zinc-200/80 text-center items-stretch shadow-2xs min-w-0">
                                <!-- Harga Jual -->
                                <div class="flex flex-col justify-center px-1 py-1 border-r border-zinc-100 min-w-0">
                                    <span class="text-[9px] uppercase font-bold tracking-wider text-zinc-400 mb-0.5 truncate">Harga Jual</span>
                                    <span class="text-xs font-black text-zinc-900 num truncate">Rp{{ number_format($srv->price, 0, ',', '.') }}</span>
                                </div>

                                <!-- Komisi Staf -->
                                <div class="flex flex-col justify-center px-1 py-1 rounded-lg {{ $commType === 'percentage' ? 'bg-purple-50 text-purple-900' : 'bg-emerald-50 text-emerald-900' }} min-w-0">
                                    <span class="text-[9px] uppercase font-black tracking-wider {{ $commType === 'percentage' ? 'text-purple-700' : 'text-emerald-700' }} mb-0.5 truncate">Komisi Staf</span>
                                    @if($commType === 'percentage')
                                        <span class="text-xs font-black num leading-tight truncate">{{ $commVal }}%</span>
                                        <span class="text-[10px] font-bold text-purple-700 num leading-tight truncate">~Rp{{ number_format($barberShare, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-xs font-black num leading-tight truncate">Rp{{ number_format($commVal, 0, ',', '.') }}</span>
                                        <span class="text-[9px] font-bold text-emerald-700 leading-tight truncate">/ pangkas</span>
                                    @endif
                                </div>

                                <!-- Bagian Toko -->
                                <div class="flex flex-col justify-center px-1 py-1 border-l border-zinc-100 min-w-0">
                                    <span class="text-[9px] uppercase font-bold tracking-wider text-zinc-400 mb-0.5 truncate">Bagian Toko</span>
                                    <span class="text-xs font-bold text-zinc-800 num leading-tight truncate">Rp{{ number_format($shopShare, 0, ',', '.') }}</span>
                                    <span class="text-[10px] font-semibold text-zinc-400 num truncate">({{ $shopPct }}%)</span>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <button
                                type="button"
                                data-commission="{{ json_encode([
                                    'id' => $srv->id,
                                    'name' => $srv->name,
                                    'price' => $srv->price,
                                    'comm_type' => $commType,
                                    'comm_val' => $commVal,
                                ]) }}"
                                onclick="openEditCommissionModal(JSON.parse(this.dataset.commission))"
                                class="w-full py-2 px-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white text-xs font-bold shadow-2xs transition-all duration-500 ease-in-out inline-flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <x-ui.icon name="sliders" class="w-3.5 h-3.5 text-amber-400" />
                                <span>Ubah Komisi</span>
                            </button>
                        </div>
                    @empty
                        <div class="col-span-full py-8 text-center text-xs text-zinc-400">
                            Belum ada layanan terdaftar.
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        </section>
    </div>

    <!-- MODAL 1: Tambah Jabatan / Skema Baru (Fleksibel & Dinamis) -->
    <dialog id="modal-add-scheme" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto border border-zinc-200">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80 rounded-t-3xl">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900">Tambah Jabatan / Skema Baru</h3>
                <p class="text-xs text-zinc-400">Atur skema gaji tetap, pokok + komisi, atau bagi hasil bebas</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-scheme').close()" class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 grid place-items-center cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('owner.payroll.store-scheme') }}" class="p-5 sm:p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Nama Jabatan / Posisi <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <input
                    type="text"
                    name="name"
                    required
                    placeholder="Contoh: Barber Senior, Mitra Bagi Hasil 40%, atau Kasir Utama"
                    class="w-full text-xs font-semibold px-3.5 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tipe Penggajian <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="grid grid-cols-1 gap-2">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" value="commission_only" checked onchange="toggleSchemeFormType('add', 'commission_only')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Murni Komisi Layanan (Standar Staff Barber)</span>
                            <span class="text-[11px] text-zinc-500 block">Tanpa gaji pokok (Rp0), penghasilan barber dihitung murni dari tarif komisi layanan per kepala (misal Rp5.000/pangkas) atau opsi bagi hasil.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" value="fixed" onchange="toggleSchemeFormType('add', 'fixed')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Gaji Pokok Tetap (Kasir / Petugas Toko / Admin)</span>
                            <span class="text-[11px] text-zinc-500 block">Gaji flat per bulan tanpa hitungan komisi layanan pangkas.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" value="base_plus_commission" onchange="toggleSchemeFormType('add', 'base_plus_commission')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Gaji Pokok + Komisi Tambahan (Opsional Staf Khusus)</span>
                            <span class="text-[11px] text-zinc-500 block">Menerima gaji pokok bulanan, komisi layanan, dan opsi target bonus.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Model Komisi untuk Commission Only -->
            <div id="add-field-commission-model" class="space-y-2 p-3 rounded-2xl bg-zinc-50 border border-zinc-200">
                <label class="block text-xs font-bold text-zinc-800">Model Komisi Barber <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/40">
                        <input type="radio" name="commission_model" id="add-comm-model-per-service" value="per_service" checked onchange="toggleCommissionModel('add')" class="text-emerald-600 focus:ring-emerald-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-bold text-zinc-900 block">Tarif per Layanan</span>
                            <span class="text-[10px] text-zinc-500 block leading-tight">Komisi per kepala (misal Rp5.000/pangkas) dari katalog</span>
                        </div>
                    </label>
                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50/40">
                        <input type="radio" name="commission_model" id="add-comm-model-percentage" value="percentage" onchange="toggleCommissionModel('add')" class="text-purple-600 focus:ring-purple-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-bold text-zinc-900 block">Bagi Hasil Persen (%)</span>
                            <span class="text-[10px] text-zinc-500 block leading-tight">Persentase dari total omzet cukur (misal 30% atau 40%)</span>
                        </div>
                    </label>
                </div>

                <!-- Notice untuk per service -->
                <div id="add-notice-per-service" class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-800 flex items-start gap-2">
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <span>Barber menerima komisi dari tarif masing-masing layanan (misal Rp5.000 untuk pangkas biasa). Anda dapat mengatur tarif setiap layanan pada tabel komisi di bawah.</span>
                </div>

                <!-- Bagian B: Input Persentase Bagi Hasil (Jika model percentage dipilih) -->
                <div id="add-field-commission-pct" class="space-y-2 hidden pt-1">
                    <label class="block text-xs font-bold text-purple-900">Persentase Bagi Hasil Mitra (%) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <div class="relative">
                        <input
                            type="number"
                            name="commission_percentage"
                            id="add-input-commission-pct"
                            value="30"
                            min="1"
                            max="100"
                            oninput="updateSimNotice('add')"
                            class="w-full text-xs font-black pl-3 pr-8 py-2.5 rounded-xl bg-white border border-purple-300 text-purple-950 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-black text-purple-600">%</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-purple-50 border border-purple-200 text-[11px] text-purple-800 flex items-start gap-2" id="add-notice-pct">
                        <x-ui.icon name="info" class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" />
                        <span id="add-notice-text">Dari layanan seharga Rp50.000, Barber menerima <strong>Rp15.000 (30%)</strong> dan Barbershop menerima <strong>Rp35.000 (70%)</strong>.</span>
                    </div>
                </div>
            </div>

            <!-- Bagian A: Input Gaji Pokok (Untuk Fixed & Base+Commission) -->
            <div id="add-field-base-salary" class="space-y-1 hidden">
                <label class="block text-xs font-bold text-zinc-700">Gaji Pokok Bulanan (Rp) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="base_salary"
                        id="add-input-base-salary"
                        value="2.000.000"
                        data-currency-input
                        oninput="formatCurrencyInput(this)"
                        class="w-full text-xs font-black pl-9 pr-3 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-950 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- Bagian C: Bonus Target Layanan Berjenjang -->
            <div id="add-field-bonus-tiers" class="space-y-2 pt-2 border-t border-zinc-100">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-zinc-900">Target Bonus Pangkas Bulanan (Opsional)</label>
                        <p class="text-[11px] text-zinc-400">Bonus tambahan jika staf mencapai batas jumlah pangkas dalam 1 bulan.</p>
                    </div>
                    <button
                        type="button"
                        onclick="addBonusTierRow('add')"
                        class="text-[11px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg transition"
                    >
                        + Tambah Target
                    </button>
                </div>

                <div id="add-bonus-tier-container" class="space-y-2">
                    <!-- Default tier rows will be inserted here by JS -->
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-add-scheme').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-800 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="sm" variant="primary">
                    <span>Simpan Jabatan Baru</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <!-- MODAL 2: Edit Jabatan / Skema (Fleksibel & Dinamis) -->
    <dialog id="modal-edit-scheme" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-lg max-h-[90vh] overflow-y-auto border border-zinc-200">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80 rounded-t-3xl">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900">Atur Skema &amp; Standar Jabatan</h3>
                <p class="text-xs text-zinc-400" id="edit-modal-subtitle">-</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-edit-scheme').close()" class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 grid place-items-center cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form id="form-edit-scheme" method="POST" action="" class="p-5 sm:p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Nama Jabatan / Posisi <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <input
                    type="text"
                    name="name"
                    id="edit-scheme-name"
                    required
                    class="w-full text-xs font-semibold px-3.5 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tipe Penggajian <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="grid grid-cols-1 gap-2">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" id="edit-type-comm-only" value="commission_only" onchange="toggleSchemeFormType('edit', 'commission_only')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Murni Komisi Layanan (Standar Staff Barber)</span>
                            <span class="text-[11px] text-zinc-500 block">Tanpa gaji pokok (Rp0), penghasilan barber dihitung murni dari tarif komisi layanan per kepala (misal Rp5.000/pangkas) atau opsi bagi hasil.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" id="edit-type-fixed" value="fixed" onchange="toggleSchemeFormType('edit', 'fixed')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Gaji Pokok Tetap (Kasir / Petugas Toko / Admin)</span>
                            <span class="text-[11px] text-zinc-500 block">Gaji flat per bulan tanpa hitungan komisi layanan pangkas.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/30">
                        <input type="radio" name="type" id="edit-type-base-comm" value="base_plus_commission" onchange="toggleSchemeFormType('edit', 'base_plus_commission')" class="text-amber-600 focus:ring-amber-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-extrabold text-zinc-900 block">Gaji Pokok + Komisi Tambahan (Opsional Staf Khusus)</span>
                            <span class="text-[11px] text-zinc-500 block">Menerima gaji pokok bulanan, komisi layanan, dan target bonus.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Model Komisi untuk Commission Only -->
            <div id="edit-field-commission-model" class="space-y-2 p-3 rounded-2xl bg-zinc-50 border border-zinc-200">
                <label class="block text-xs font-bold text-zinc-800">Model Komisi Barber <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/40">
                        <input type="radio" name="commission_model" id="edit-comm-model-per-service" value="per_service" checked onchange="toggleCommissionModel('edit')" class="text-emerald-600 focus:ring-emerald-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-bold text-zinc-900 block">Tarif per Layanan</span>
                            <span class="text-[10px] text-zinc-500 block leading-tight">Komisi per kepala (misal Rp5.000/pangkas) dari katalog</span>
                        </div>
                    </label>
                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50/40">
                        <input type="radio" name="commission_model" id="edit-comm-model-percentage" value="percentage" onchange="toggleCommissionModel('edit')" class="text-purple-600 focus:ring-purple-500 mt-0.5" />
                        <div>
                            <span class="text-xs font-bold text-zinc-900 block">Bagi Hasil Persen (%)</span>
                            <span class="text-[10px] text-zinc-500 block leading-tight">Persentase dari total omzet cukur (misal 30% atau 40%)</span>
                        </div>
                    </label>
                </div>

                <!-- Notice untuk per service -->
                <div id="edit-notice-per-service" class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-800 flex items-start gap-2">
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <span>Barber menerima komisi dari tarif masing-masing layanan (misal Rp5.000 untuk pangkas biasa). Anda dapat mengatur tarif setiap layanan pada tabel komisi di bawah.</span>
                </div>

                <!-- Bagian B: Input Persentase Bagi Hasil -->
                <div id="edit-field-commission-pct" class="space-y-2 hidden pt-1">
                    <label class="block text-xs font-bold text-purple-900">Persentase Bagi Hasil Mitra (%) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <div class="relative">
                        <input
                            type="number"
                            name="commission_percentage"
                            id="edit-input-commission-pct"
                            min="1"
                            max="100"
                            oninput="updateSimNotice('edit')"
                            class="w-full text-xs font-black pl-3 pr-8 py-2.5 rounded-xl bg-white border border-purple-300 text-purple-950 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-black text-purple-600">%</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-purple-50 border border-purple-200 text-[11px] text-purple-800 flex items-start gap-2" id="edit-notice-pct">
                        <x-ui.icon name="info" class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" />
                        <span id="edit-notice-text">Dari layanan seharga Rp50.000, Barber menerima <strong>Rp20.000 (40%)</strong> dan Barbershop menerima <strong>Rp30.000 (60%)</strong>.</span>
                    </div>
                </div>
            </div>

            <!-- Bagian A: Input Gaji Pokok -->
            <div id="edit-field-base-salary" class="space-y-1">
                <label class="block text-xs font-bold text-zinc-700">Gaji Pokok Bulanan (Rp) <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="base_salary"
                        id="edit-input-base-salary"
                        data-currency-input
                        oninput="formatCurrencyInput(this)"
                        class="w-full text-xs font-black pl-9 pr-3 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-950 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- Bagian C: Bonus Target Layanan Berjenjang -->
            <div id="edit-field-bonus-tiers" class="space-y-2 pt-2 border-t border-zinc-100">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-zinc-900">Target Bonus Pangkas Bulanan (Opsional)</label>
                        <p class="text-[11px] text-zinc-400">Bonus tambahan jika staf mencapai batas jumlah pangkas dalam 1 bulan.</p>
                    </div>
                    <button
                        type="button"
                        onclick="addBonusTierRow('edit')"
                        class="text-[11px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg transition"
                    >
                        + Tambah Target
                    </button>
                </div>

                <div id="edit-bonus-tier-container" class="space-y-2">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-edit-scheme').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-800 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="sm" variant="primary">
                    <span>Simpan Perubahan</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <!-- MODAL 3: Ubah Komisi Layanan -->
    <dialog id="modal-edit-commission" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-md max-h-[90vh] overflow-y-auto border border-zinc-200">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80 rounded-t-3xl">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900">Ubah Komisi Default Layanan</h3>
                <p class="text-xs text-zinc-500" id="modal-comm-service-title">-</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-edit-commission').close()" class="w-8 h-8 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 grid place-items-center cursor-pointer">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('owner.payroll.store-commission-rule') }}" class="p-5 sm:p-6 space-y-4">
            @csrf
            <input type="hidden" name="service_id" id="input-comm-service-id" value="" />

            <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex items-center justify-between text-xs">
                <span class="text-zinc-500 font-medium">Harga Jual Layanan:</span>
                <span class="font-extrabold text-zinc-900 num" id="modal-comm-price-label">Rp0</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tipe Perhitungan Komisi <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition text-xs has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40">
                        <input type="radio" name="type" value="flat" id="rule-type-flat" onchange="toggleCommInputType('flat')" class="text-amber-600 focus:ring-amber-500" />
                        <div>
                            <span class="font-bold block text-zinc-900">Nominal Flat (Rp)</span>
                            <span class="text-[10px] text-zinc-400">Tetap per pangkas</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-zinc-200 bg-white hover:bg-zinc-50 cursor-pointer transition text-xs has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40">
                        <input type="radio" name="type" value="percentage" id="rule-type-percentage" onchange="toggleCommInputType('percentage')" class="text-amber-600 focus:ring-amber-500" />
                        <div>
                            <span class="font-bold block text-zinc-900">Persentase (%)</span>
                            <span class="text-[10px] text-zinc-400">% dari harga jual</span>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1" id="label-comm-value">Besaran Komisi <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400" id="prefix-comm-value">Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="value"
                        id="input-comm-value"
                        required
                        data-comm-input
                        oninput="handleCommInput(this)"
                        class="w-full text-xs font-black pl-9 pr-8 py-2.5 rounded-xl bg-white border border-zinc-200 text-zinc-950 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 hidden" id="suffix-comm-value">%</span>
                </div>
                <p class="text-[11px] text-zinc-400 mt-1" id="hint-comm-calc">-</p>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-edit-commission').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="sm" variant="primary">
                    <span>Simpan Komisi</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <script type="application/json" id="schemes-data-json">@json($schemesJson)</script>
    <script>
        const schemesData = JSON.parse(document.getElementById('schemes-data-json')?.textContent || '{}');
        let currentCommissionServicePrice = 0;

        // Helper memformat angka ke format ribuan rupiah Indonesia (misal: 2000000 -> "2.000.000")
        function formatCurrencyNumber(val) {
            if (val === null || val === undefined || val === '') return '';
            const num = parseInt(String(val).replace(/\D/g, ''), 10);
            if (isNaN(num)) return '';
            return num.toLocaleString('id-ID');
        }

        // Live input formatter saat user mengetik nominal
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

        // ==========================================
        // 1. LOGIKA SIMULASI & KALKULATOR GAJI
        // ==========================================
        function updateSimCuts(val) {
            const label = document.getElementById('sim-cuts-label');
            if (label) {
                label.textContent = `${val} Layanan`;
            }
            runPayrollSimulation();
        }

        function runPayrollSimulation() {
            const cutsSlider = document.getElementById('sim-cuts-slider');
            if (!cutsSlider) return;

            const hiddenSchemeInput = document.querySelector('#sim-scheme-select-wrapper input[name="sim_scheme_id"]') 
                || document.querySelector('#sim-scheme-select-wrapper [data-select-input]');
            const schemeId = parseInt(hiddenSchemeInput ? hiddenSchemeInput.value : (schemesData[0]?.id || '0'), 10);
            const cuts = parseInt(cutsSlider.value, 10);
            const avgPriceInput = document.getElementById('sim-avg-price');
            const avgPrice = parseInt((avgPriceInput ? avgPriceInput.value : '').replace(/\D/g, '') || '50000', 10);

            const scheme = schemesData.find(s => s.id === schemeId) || schemesData[0];
            if (!scheme) return;

            const grossOmzet = cuts * avgPrice;
            let baseSalary = 0;
            let commTotal = 0;
            let bonusTotal = 0;
            let commLabel = 'Komisi Layanan:';
            let commSublabel = '';
            let targetStatusHtml = '';

            if (scheme.type === 'commission_only') {
                baseSalary = 0;
                if (scheme.has_percentage_commission) {
                    const pct = scheme.commission_percentage || 30;
                    commTotal = Math.round((grossOmzet * pct) / 100);
                    commLabel = 'Bagi Hasil Komisi:';
                    commSublabel = `${pct}% dari omzet cukur`;
                    targetStatusHtml = `
                        <div class="flex items-center gap-2 text-purple-900 font-semibold text-xs">
                            <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            <span>Murni bagi hasil ${pct}% dari total omzet transaksi</span>
                        </div>
                    `;
                } else {
                    commTotal = cuts * 5000;
                    commLabel = 'Komisi Layanan:';
                    commSublabel = `Tarif ~Rp5.000 x ${cuts} pangkas`;
                    targetStatusHtml = `
                        <div class="flex items-center gap-2 text-emerald-900 font-semibold text-xs">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <span>Murni komisi per layanan (tanpa gaji pokok)</span>
                        </div>
                    `;
                }

                // Evaluasi bonus target jika skema komisi memiliki target
                if (scheme.bonus_tiers && scheme.bonus_tiers.length > 0) {
                    let highestTier = null;
                    let nextTier = null;

                    for (const tier of scheme.bonus_tiers) {
                        const min = parseInt(tier.min_services, 10);
                        const bonus = parseInt(tier.bonus, 10);
                        if (cuts >= min) {
                            if (bonus > bonusTotal) {
                                bonusTotal = bonus;
                                highestTier = tier;
                            }
                        } else if (!nextTier) {
                            nextTier = tier;
                        }
                    }

                    if (highestTier) {
                        targetStatusHtml = `
                            <div class="flex items-start gap-2 text-emerald-900 text-xs">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <div class="leading-snug">
                                    <span class="font-bold">Target ≥${highestTier.min_services}x tercapai! Bonus +Rp${highestTier.bonus.toLocaleString('id-ID')}</span>
                                    ${nextTier ? `<span class="block text-[11px] text-zinc-500 mt-0.5 font-medium">Kurang ${nextTier.min_services - cuts} pangkas lagi ke bonus +Rp${nextTier.bonus.toLocaleString('id-ID')}</span>` : ''}
                                </div>
                            </div>
                        `;
                    } else if (nextTier) {
                        targetStatusHtml = `
                            <div class="flex items-start gap-2 text-amber-900 text-xs">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><polyline points="12 6 12 12 16 14" stroke-width="2"></polyline></svg>
                                <div class="leading-snug font-medium">
                                    <span>Kurang <strong>${nextTier.min_services - cuts}x</strong> pangkas lagi untuk bonus +Rp${nextTier.bonus.toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        `;
                    }
                }
            } else if (scheme.type === 'fixed') {
                baseSalary = scheme.base_salary;
                commTotal = 0;
                bonusTotal = 0;
                commLabel = 'Komisi Layanan:';
                commSublabel = 'Gaji flat (tanpa komisi)';
                targetStatusHtml = `
                    <div class="flex items-center gap-2 text-sky-900 font-semibold text-xs">
                        <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="7" rx="2" stroke-width="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" stroke-width="2"></path></svg>
                        <span>Penghasilan flat bulanan tanpa komisi pangkas</span>
                    </div>
                `;
            } else {
                // base_plus_commission
                baseSalary = scheme.base_salary;
                // Estimasi rata-rata komisi per potong Rp5.000
                commTotal = cuts * 5000;
                commLabel = 'Komisi Layanan:';
                commSublabel = `Flat ~Rp5.000 x ${cuts} pangkas`;

                // Evaluasi bonus target
                if (scheme.bonus_tiers && scheme.bonus_tiers.length > 0) {
                    let highestTier = null;
                    let nextTier = null;

                    for (const tier of scheme.bonus_tiers) {
                        const min = parseInt(tier.min_services, 10);
                        const bonus = parseInt(tier.bonus, 10);
                        if (cuts >= min) {
                            if (bonus > bonusTotal) {
                                bonusTotal = bonus;
                                highestTier = tier;
                            }
                        } else if (!nextTier) {
                            nextTier = tier;
                        }
                    }

                    if (highestTier) {
                        targetStatusHtml = `
                            <div class="flex items-start gap-2 text-emerald-900 text-xs">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <div class="leading-snug">
                                    <span class="font-bold">Target ≥${highestTier.min_services}x tercapai! Bonus +Rp${highestTier.bonus.toLocaleString('id-ID')}</span>
                                    ${nextTier ? `<span class="block text-[11px] text-zinc-500 mt-0.5 font-medium">Kurang ${nextTier.min_services - cuts} pangkas lagi ke bonus +Rp${nextTier.bonus.toLocaleString('id-ID')}</span>` : ''}
                                </div>
                            </div>
                        `;
                    } else if (nextTier) {
                        targetStatusHtml = `
                            <div class="flex items-start gap-2 text-amber-900 text-xs">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><polyline points="12 6 12 12 16 14" stroke-width="2"></polyline></svg>
                                <div class="leading-snug font-medium">
                                    <span>Kurang <strong>${nextTier.min_services - cuts}x</strong> pangkas lagi untuk bonus +Rp${nextTier.bonus.toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    targetStatusHtml = `
                        <div class="flex items-center gap-2 text-zinc-500 text-xs">
                            <svg class="w-4 h-4 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><line x1="12" y1="16" x2="12" y2="12" stroke-width="2"></line><line x1="12" y1="8" x2="12.01" y2="8" stroke-width="2"></line></svg>
                            <span>Belum ada target bonus untuk jabatan ini</span>
                        </div>
                    `;
                }
            }

            const staffTakeHome = baseSalary + commTotal + bonusTotal;
            const shopShare = Math.max(0, grossOmzet - staffTakeHome);

            const staffPct = grossOmzet > 0 ? Math.round((staffTakeHome / grossOmzet) * 100) : 0;
            const shopPct = grossOmzet > 0 ? Math.max(0, 100 - staffPct) : 100;

            // Update UI Output
            document.getElementById('sim-result-staff-total').textContent = 'Rp' + staffTakeHome.toLocaleString('id-ID');
            document.getElementById('sim-result-base').textContent = 'Rp' + baseSalary.toLocaleString('id-ID');
            document.getElementById('sim-result-comm-label').textContent = commLabel;
            const sublabelEl = document.getElementById('sim-result-comm-sublabel');
            if (sublabelEl) {
                sublabelEl.textContent = commSublabel;
            }
            document.getElementById('sim-result-comm').textContent = 'Rp' + commTotal.toLocaleString('id-ID');
            document.getElementById('sim-result-bonus').textContent = 'Rp' + bonusTotal.toLocaleString('id-ID');
            document.getElementById('sim-target-status').innerHTML = targetStatusHtml;

            document.getElementById('sim-result-shop-total').textContent = 'Rp' + shopShare.toLocaleString('id-ID');
            document.getElementById('sim-result-gross-omzet').textContent = 'Rp' + grossOmzet.toLocaleString('id-ID');
            document.getElementById('sim-result-shop-pct').textContent = shopPct + '%';
            document.getElementById('sim-result-staff-pct').textContent = staffPct + '%';
        }

        // Listener klik opsi dropdown komponen select simulasi
        document.getElementById('sim-scheme-select-wrapper')?.addEventListener('click', (e) => {
            const opt = e.target.closest('[data-select-option]');
            if (opt) {
                setTimeout(runPayrollSimulation, 50);
            }
        });

        // Jalankan simulasi saat halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', () => {
            runPayrollSimulation();
        });

        // ==========================================
        // 2. TOGGLE TIPE FORM SKEMA (ADD / EDIT)
        // ==========================================
        function toggleCommissionModel(mode) {
            const isPercentage = document.getElementById(`${mode}-comm-model-percentage`)?.checked;
            const pctField = document.getElementById(`${mode}-field-commission-pct`);
            const perServiceNotice = document.getElementById(`${mode}-notice-per-service`);
            const pctInput = document.getElementById(`${mode}-input-commission-pct`);

            if (isPercentage) {
                if (pctField) pctField.classList.remove('hidden');
                if (perServiceNotice) perServiceNotice.classList.add('hidden');
                if (pctInput && !pctInput.value) pctInput.value = '30';
                updateSimNotice(mode);
            } else {
                if (pctField) pctField.classList.add('hidden');
                if (perServiceNotice) perServiceNotice.classList.remove('hidden');
                if (pctInput) pctInput.value = '';
            }
        }

        function toggleSchemeFormType(mode, type) {
            const baseSalaryField = document.getElementById(`${mode}-field-base-salary`);
            const commModelField = document.getElementById(`${mode}-field-commission-model`);
            const bonusTiersField = document.getElementById(`${mode}-field-bonus-tiers`);
            const baseInput = document.getElementById(`${mode}-input-base-salary`);

            if (type === 'commission_only') {
                if (baseSalaryField) baseSalaryField.classList.add('hidden');
                if (commModelField) commModelField.classList.remove('hidden');
                if (bonusTiersField) bonusTiersField.classList.remove('hidden');
                if (baseInput) baseInput.value = '0';
                toggleCommissionModel(mode);
            } else if (type === 'fixed') {
                if (baseSalaryField) baseSalaryField.classList.remove('hidden');
                if (commModelField) commModelField.classList.add('hidden');
                if (bonusTiersField) bonusTiersField.classList.add('hidden');
                if (baseInput && (baseInput.value === '0' || !baseInput.value)) baseInput.value = '2.500.000';
            } else {
                // base_plus_commission
                if (baseSalaryField) baseSalaryField.classList.remove('hidden');
                if (commModelField) commModelField.classList.add('hidden');
                if (bonusTiersField) bonusTiersField.classList.remove('hidden');
                if (baseInput && (baseInput.value === '0' || !baseInput.value)) baseInput.value = '2.000.000';
            }
        }

        function updateSimNotice(mode) {
            const input = document.getElementById(`${mode}-input-commission-pct`);
            const notice = document.getElementById(`${mode}-notice-pct`);
            if (!input || !notice) return;
            const pct = parseInt(input.value || '30', 10);
            const barberShare = Math.round((50000 * pct) / 100);
            const shopShare = Math.max(0, 50000 - barberShare);
            notice.innerHTML = `
                <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
                <span>Contoh: Dari layanan seharga Rp50.000, Barber menerima <strong>Rp${barberShare.toLocaleString('id-ID')} (${pct}%)</strong> dan Barbershop menerima <strong>Rp${shopShare.toLocaleString('id-ID')} (${100 - pct}%)</strong>.</span>
            `;
        }

        // ==========================================
        // 3. TARGET BONUS ROWS DINAMIS
        // ==========================================
        let tierRowCounter = 100;

        function addBonusTierRow(mode, minServices = '', bonusAmount = '') {
            const container = document.getElementById(`${mode}-bonus-tier-container`);
            const index = tierRowCounter++;
            const formattedBonus = bonusAmount !== '' && bonusAmount !== null ? formatCurrencyNumber(bonusAmount) : '';

            const row = document.createElement('div');
            row.className = 'flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-2.5 sm:p-2 rounded-xl bg-zinc-50 border border-zinc-200/80 text-xs';
            row.id = `tier-row-${index}`;
            row.innerHTML = `
                <div class="flex-1 flex items-center gap-1.5">
                    <span class="text-zinc-500 font-bold text-[11px] shrink-0">Minimal:</span>
                    <input
                        type="number"
                        name="bonus_tiers[${index}][min_services]"
                        value="${minServices}"
                        placeholder="250"
                        min="1"
                        required
                        class="w-full font-bold px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 num text-xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <span class="text-zinc-400 text-[11px] shrink-0">pangkas</span>
                </div>
                <div class="flex-1 flex items-center gap-1.5">
                    <span class="text-zinc-500 font-bold text-[11px] shrink-0">Bonus: Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="bonus_tiers[${index}][bonus]"
                        value="${formattedBonus}"
                        placeholder="200.000"
                        data-currency-input
                        oninput="formatCurrencyInput(this)"
                        required
                        class="w-full font-bold px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 num text-xs"
                    />
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('tier-row-${index}').remove()"
                    class="p-1.5 rounded-lg text-zinc-400 hover:text-rose-600 hover:bg-rose-50 transition self-end sm:self-center shrink-0 cursor-pointer"
                    title="Hapus Baris Target"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            `;
            container.appendChild(row);
        }

        function openAddSchemeModal() {
            const container = document.getElementById('add-bonus-tier-container');
            container.innerHTML = '';
            // Default 2 baris target contoh
            addBonusTierRow('add', 200, 200000);
            addBonusTierRow('add', 250, 300000);

            document.getElementById('add-input-base-salary').value = '0';
            const perServiceRadio = document.getElementById('add-comm-model-per-service');
            if (perServiceRadio) perServiceRadio.checked = true;

            toggleSchemeFormType('add', 'commission_only');
            document.getElementById('modal-add-scheme').showModal();
        }

        function openEditSchemeModal(scheme) {
            const modal = document.getElementById('modal-edit-scheme');
            const form = document.getElementById('form-edit-scheme');
            form.action = `/owner/payroll/schemes/${scheme.id}`;

            document.getElementById('edit-scheme-name').value = scheme.name;
            document.getElementById('edit-modal-subtitle').textContent = `Mengubah pengaturan untuk ${scheme.name}`;
            document.getElementById('edit-input-base-salary').value = formatCurrencyNumber(scheme.base_salary);
            const alphaInput = document.getElementById('edit-input-alpha-penalty');
            if (alphaInput) {
                alphaInput.value = formatCurrencyNumber(scheme.alpha_penalty_nominal || 0);
            }

            // Radio model komisi
            const isPct = Boolean(scheme.has_percentage_commission);
            const pctRadio = document.getElementById('edit-comm-model-percentage');
            const perServiceRadio = document.getElementById('edit-comm-model-per-service');
            const pctInput = document.getElementById('edit-input-commission-pct');

            if (isPct) {
                if (pctRadio) pctRadio.checked = true;
                if (pctInput) pctInput.value = scheme.commission_percentage || 30;
            } else {
                if (perServiceRadio) perServiceRadio.checked = true;
                if (pctInput) pctInput.value = '';
            }

            // Set radio checked
            if (scheme.type === 'commission_only') {
                document.getElementById('edit-type-comm-only').checked = true;
            } else if (scheme.type === 'fixed') {
                document.getElementById('edit-type-fixed').checked = true;
            } else {
                document.getElementById('edit-type-base-comm').checked = true;
            }

            toggleSchemeFormType('edit', scheme.type);

            // Populate bonus tiers
            const container = document.getElementById('edit-bonus-tier-container');
            container.innerHTML = '';
            if (scheme.bonus_tiers && scheme.bonus_tiers.length > 0) {
                scheme.bonus_tiers.forEach(t => {
                    addBonusTierRow('edit', t.min_services, t.bonus);
                });
            } else {
                addBonusTierRow('edit', 250, 200000);
            }

            modal.showModal();
        }

        // ==========================================
        // 4. MODAL EDIT KOMISI PER LAYANAN
        // ==========================================
        function openEditCommissionModal(srv) {
            currentCommissionServicePrice = srv.price;
            const modal = document.getElementById('modal-edit-commission');
            document.getElementById('input-comm-service-id').value = srv.id;
            document.getElementById('modal-comm-service-title').textContent = `${srv.name}`;
            document.getElementById('modal-comm-price-label').textContent = 'Rp' + srv.price.toLocaleString('id-ID');

            if (srv.comm_type === 'percentage') {
                document.getElementById('rule-type-percentage').checked = true;
                toggleCommInputType('percentage');
                document.getElementById('input-comm-value').value = srv.comm_val;
            } else {
                document.getElementById('rule-type-flat').checked = true;
                toggleCommInputType('flat');
                document.getElementById('input-comm-value').value = formatCurrencyNumber(srv.comm_val);
            }

            recalculateCommHint();
            modal.showModal();
        }

        function toggleCommInputType(type) {
            const prefix = document.getElementById('prefix-comm-value');
            const suffix = document.getElementById('suffix-comm-value');
            const input = document.getElementById('input-comm-value');
            if (type === 'percentage') {
                prefix.classList.add('hidden');
                suffix.classList.remove('hidden');
                input.classList.remove('pl-9');
                input.classList.add('pl-3');
                input.value = input.value.replace(/\D/g, '');
                if (parseInt(input.value || '0', 10) > 100) input.value = 100;
            } else {
                prefix.classList.remove('hidden');
                suffix.classList.add('hidden');
                input.classList.add('pl-9');
                input.classList.remove('pl-3');
                formatCurrencyInput(input);
            }
            recalculateCommHint();
        }

        function handleCommInput(el) {
            const isPct = document.getElementById('rule-type-percentage').checked;
            if (isPct) {
                let num = parseInt(el.value.replace(/\D/g, '') || '0', 10);
                if (num > 100) num = 100;
                el.value = num > 0 ? num : (el.value === '' ? '' : '0');
            } else {
                formatCurrencyInput(el);
            }
            recalculateCommHint();
        }

        function recalculateCommHint() {
            const input = document.getElementById('input-comm-value');
            const isPct = document.getElementById('rule-type-percentage').checked;
            const hint = document.getElementById('hint-comm-calc');
            const rawVal = (input.value || '').replace(/\D/g, '');
            const val = parseInt(rawVal || '0', 10);

            if (isPct) {
                const estBarber = Math.round((currentCommissionServicePrice * val) / 100);
                const estShop = Math.max(0, currentCommissionServicePrice - estBarber);
                hint.textContent = `Barber menerima ~Rp${estBarber.toLocaleString('id-ID')} (${val}%) · Barbershop menerima ~Rp${estShop.toLocaleString('id-ID')}`;
            } else {
                const estShop = Math.max(0, currentCommissionServicePrice - val);
                hint.textContent = `Barber menerima Rp${val.toLocaleString('id-ID')} · Barbershop menerima ~Rp${estShop.toLocaleString('id-ID')}`;
            }
        }

        // ==========================================
        // 5. BERSIHKAN TITIK SEBELUM SUBMIT FORM
        // ==========================================
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-currency-input], [data-comm-input]').forEach(inp => {
                    inp.value = inp.value.replace(/\D/g, '') || '0';
                });
            });
        });

        // ==========================================
        // 6. TUTUP MODAL SAAT KLIK BACKDROP
        // ==========================================
        ['modal-add-scheme', 'modal-edit-scheme', 'modal-edit-commission'].forEach(id => {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.addEventListener('click', (e) => {
                const rect = modal.getBoundingClientRect();
                const isInDialog = (
                    e.clientX >= rect.left &&
                    e.clientX <= rect.right &&
                    e.clientY >= rect.top &&
                    e.clientY <= rect.bottom
                );
                if (!isInDialog) {
                    modal.close();
                }
            });
        });
    </script>
</x-layouts.app>
