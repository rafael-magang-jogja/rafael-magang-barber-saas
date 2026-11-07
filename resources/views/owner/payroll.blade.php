<x-layouts.app title="Gaji & Komisi" subtitle="Kelola slip gaji dan persetujuan payroll karyawan" role="owner">
    @php
        $currentCutoffDay = now()->endOfMonth()->day - 7;
        $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
        $currentPlanCode = $currentTenantCtx->subscription()?->plan?->code ?? 'starter';
        $canPrintSlip = in_array($currentPlanCode, ['business'], true);

        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $staffSelectOptions = ($activeStaff ?? collect())->map(fn ($s) => [
            'value' => (string) $s->id,
            'label' => $s->display_name ?? $s->user?->name ?? 'Karyawan',
            'sublabel' => ($s->role?->value === 'barber' ? 'Barber' : 'Admin / Kasir') . ' · ' . ($s->branch?->name ?? 'Semua Cabang'),
            'branch_id' => $s->branch_id ? (string) $s->branch_id : '',
        ])->all();

        $payrollSwitcherOptions = $payrolls->map(fn ($p) => [
            'value' => (string) $p->id,
            'label' => $p->period_start->format('d M') . ' – ' . $p->period_end->format('d M Y'),
            'sublabel' => strtoupper($p->status->value),
        ])->all();
    @endphp

    <!-- Sub Navigasi Modul Gaji & Komisi -->
    <x-payroll.nav active="payroll" />

    <!-- Header Periode Payroll & Aksi Generate (Rapi di Tablet & Mobile dengan Sidebar Aktif) -->
    <div class="mb-5 p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3.5">
            <!-- Informasi Periode Terpilih -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="calendar" class="w-5 h-5 text-amber-600" />
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Periode Penggajian</span>
                    <div class="flex items-center gap-2 flex-wrap mt-0.5">
                        <h2 class="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-normal break-words">
                            {{ $selectedPayroll ? $selectedPayroll->period_start->translatedFormat('d M') . ' – ' . $selectedPayroll->period_end->translatedFormat('d M Y') : 'Belum ada periode payroll' }}
                        </h2>
                        @if($selectedPayroll)
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-md inline-flex items-center gap-1 shrink-0 {{ $selectedPayroll->status->value === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($selectedPayroll->status->value === 'approved' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                <x-ui.icon name="{{ $selectedPayroll->status->value === 'paid' ? 'check' : ($selectedPayroll->status->value === 'approved' ? 'shield-check' : 'draft') }}" class="w-3 h-3" />
                                <span>{{ strtoupper($selectedPayroll->status->value) }}</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Tombol Generate Baru -->
            <div id="payroll-period-toolbar" class="w-full xl:w-auto pt-3 xl:pt-0 border-t xl:border-t-0 border-zinc-100 flex items-center justify-center shrink-0">
                <button
                    type="button"
                    onclick="openGeneratePayrollModal()"
                    class="w-full xl:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-extrabold shadow-sm transition cursor-pointer whitespace-nowrap"
                >
                    <x-ui.icon name="plus" class="w-4 h-4 text-zinc-950 shrink-0" />
                    <span>Hitung &amp; Generate Payroll Baru</span>
                </button>
            </div>
        </div>
    </div>

    @if($selectedPayroll)
        @php
            $payrollItems = $selectedPayroll->items->filter(function ($it) {
                return $it->membership
                    && $it->membership->role?->value !== 'owner'
                    && $it->membership->role !== \App\Enums\Role::Owner
                    && $it->membership->user?->email !== 'owner@barberking.id';
            });
            $payrollTotalAmount = (int) $payrollItems->sum('net_total');

            // Pemisahan Karyawan Gaji Pokok & Mitra Komisi / Bagi Hasil
            $isFixedStaff = function ($it) {
                return ((int) $it->base_salary > 0)
                    || ((int) ($it->scheme_snapshot['original_base_salary'] ?? 0) > 0)
                    || ($it->membership && (int) $it->membership->effective_base_salary > 0);
            };
            $baseSalaryItems = $payrollItems->filter($isFixedStaff);
            $commissionOnlyItems = $payrollItems->reject($isFixedStaff);

            // Ambang Batas Minimal 7 Hari Mendekati Akhir Bulan (H-7 Akhir Bulan)
            $periodEndCarbon = $selectedPayroll->period_end->copy()->endOfDay();
            $lastDayOfMonth = $periodEndCarbon->copy()->endOfMonth()->day;
            $cutoffDay = $lastDayOfMonth - 7; // misal tgl 24 untuk bulan 31 hari, tgl 23 untuk bulan 30 hari

            // Apakah periode ini adalah bulan lalu (bulan kemarin yang sudah terlewati)?
            $isPastMonth = now()->year > $periodEndCarbon->year
                || (now()->year === $periodEndCarbon->year && now()->month > $periodEndCarbon->month);

            // Untuk bulan berjalan: slip gaji dan pencairan hanya dibuka mulai H-7 akhir bulan (tgl >= cutoffDay)
            $isCurrentMonthNearEnd = now()->isSameMonth($periodEndCarbon)
                && now()->isSameYear($periodEndCarbon)
                && now()->day >= $cutoffDay;

            // Akses Slip & Pembayaran: semua sama di akhir bulan (mulai H-7 akhir bulan atau bulan lalu)
            $canAccessPayout = $isPastMonth || $isCurrentMonthNearEnd;

            // Status Approval: Pembayaran hanya bisa dilakukan jika payroll sudah di-approve
            $isApproved = in_array($selectedPayroll->status->value, ['approved', 'paid'], true);
            $canPayGaji = $canAccessPayout && $isApproved;

            $hasUnpaidItems = $payrollItems->contains(fn ($it) => $it->payment_status !== 'paid');

            $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
            $hasCommissionAuto = $tenant?->hasFeature('commission_auto') ?? false;
        @endphp

        @if(! $hasCommissionAuto)
            <div class="mb-4 p-3.5 sm:p-4 rounded-2xl bg-amber-50/80 border border-amber-200/80 text-amber-950 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                        <x-ui.icon name="info" class="w-4 h-4 text-amber-700" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs sm:text-sm font-extrabold text-amber-950">Skema Payroll Starter</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-200/80 text-amber-900 border border-amber-300 shrink-0">
                                Info
                            </span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-zinc-600 mt-1 leading-relaxed">
                            Transaksi baru tidak memotong komisi staf (100% omzet toko). Komisi staf sebelum paket Starter tetap tersimpan dan wajib dibayarkan di payroll.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if(! $canAccessPayout)
            <div class="mb-4 p-3.5 sm:p-4 rounded-2xl bg-amber-50/90 border border-amber-300 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                        <x-ui.icon name="clock" class="w-4 h-4 text-amber-700" />
                    </div>
                    <div class="min-w-0 text-xs">
                        <p class="font-extrabold text-amber-950 text-sm">Slip Gaji &amp; Pembayaran Dibuka Mendekati Akhir Bulan (H-7)</p>
                        <p class="text-amber-800 mt-0.5 leading-relaxed">
                            Periode payroll ini masih berjalan (hari ini tanggal <strong>{{ now()->translatedFormat('d F Y') }}</strong>). Rincian slip gaji dan pembayaran seluruh karyawan (gaji pokok maupun mitra komisi) akan dibuka otomatis saat mendekati akhir bulan (mulai tanggal {{ $cutoffDay }} {{ $periodEndCarbon->translatedFormat('F') }}).
                        </p>
                    </div>
                </div>
            </div>
        @elseif(! $isApproved)
            <div class="mb-4 p-3.5 sm:p-4 rounded-2xl bg-sky-50/90 border border-sky-300 text-sky-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-sky-500/20 text-sky-800 flex items-center justify-center shrink-0 mt-0.5">
                        <x-ui.icon name="check-circle" class="w-4 h-4 text-sky-700" />
                    </div>
                    <div class="min-w-0 text-xs">
                        <p class="font-extrabold text-sky-950 text-sm">Payroll Siap: Menunggu Persetujuan (Approve)</p>
                        <p class="text-sky-800 mt-0.5 leading-relaxed">
                            Periode sudah memasuki jadwal pencairan. Silakan periksa rincian gaji karyawan, lalu klik tombol <strong>"Setujui Payroll (Approve)"</strong> di kanan atas untuk membuka pembayaran gaji.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Ringkasan Periode Terpilih (Responsif: 1 Kolom di Mobile, 2 Kolom di Tablet, 4 Kolom di Desktop) -->
        <div class="stat-cards-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-3.5 mb-5 transition-all duration-500 ease-in-out">
            <!-- 1. Total Payroll Periode Ini (Highlight Dark Card) -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-950 text-white border border-zinc-800 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-zinc-400 truncate">Total Payroll Periode Ini</p>
                    <span class="w-7 h-7 rounded-xl bg-amber-500/15 text-amber-400 grid place-items-center shrink-0">
                        <x-ui.icon name="banknote" class="w-4 h-4 text-amber-400" />
                    </span>
                </div>
                <div class="mt-2.5">
                    <p class="text-xl sm:text-2xl font-black text-white num leading-tight truncate">
                        Rp{{ number_format($payrollTotalAmount, 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-emerald-400 font-medium mt-0.5 truncate flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                        <span>Gaji Pokok + Komisi Layanan</span>
                    </p>
                </div>
            </div>

            <!-- 2. Total Komisi Pangkas -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-zinc-600 truncate">Total Komisi Pangkas</p>
                    <span class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0 border border-emerald-200/60">
                        <x-ui.icon name="scissors" class="w-4 h-4 text-emerald-600" />
                    </span>
                </div>
                <div class="mt-2.5">
                    <p class="text-xl sm:text-2xl font-black text-emerald-700 num leading-tight truncate">
                        Rp{{ number_format($payrollItems->sum('commission_total'), 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-zinc-400 font-medium mt-0.5 truncate flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <span>Dari {{ $payrollItems->sum('services_count') }}x layanan pangkas</span>
                    </p>
                </div>
            </div>

            <!-- 3. Total Karyawan -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-zinc-600 truncate">Total Karyawan</p>
                    <span class="w-7 h-7 rounded-xl bg-sky-50 text-sky-600 grid place-items-center shrink-0 border border-sky-200/60">
                        <x-ui.icon name="users" class="w-4 h-4 text-sky-600" />
                    </span>
                </div>
                <div class="mt-2.5">
                    <p class="text-xl sm:text-2xl font-black text-zinc-900 num leading-tight truncate">
                        {{ $payrollItems->count() }} <span class="text-xs sm:text-sm font-bold text-zinc-500">orang</span>
                    </p>
                    <p class="text-[11px] text-zinc-400 font-medium mt-0.5 truncate flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 shrink-0"></span>
                        <span>Staf aktif berhak gaji</span>
                    </p>
                </div>
            </div>

            <!-- 4. Status Periode -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col justify-between min-w-0 transition-all duration-500 ease-in-out">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-zinc-600 truncate">Status Periode</p>
                    <span class="w-7 h-7 rounded-xl grid place-items-center shrink-0 border {{ $selectedPayroll->status->value === 'paid' ? 'bg-emerald-50 text-emerald-600 border-emerald-200/60' : ($selectedPayroll->status->value === 'approved' ? 'bg-sky-50 text-sky-600 border-sky-200/60' : 'bg-amber-50 text-amber-600 border-amber-200/60') }}">
                        <x-ui.icon name="{{ $selectedPayroll->status->value === 'paid' ? 'receipt' : ($selectedPayroll->status->value === 'approved' ? 'shield-check' : 'draft') }}" class="w-4 h-4" />
                    </span>
                </div>
                <div class="mt-2.5">
                    <p class="text-xl sm:text-2xl font-black leading-tight truncate {{ $selectedPayroll->status->value === 'paid' ? 'text-emerald-700' : ($selectedPayroll->status->value === 'approved' ? 'text-sky-700' : 'text-amber-700') }}">
                        {{ strtoupper($selectedPayroll->status->value) }}
                    </p>
                    <p class="text-[11px] text-zinc-500 font-medium mt-0.5 truncate flex items-center gap-1.5">
                        @if($selectedPayroll->status->value === 'paid')
                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Lunas dibayar</span>
                        @elseif($selectedPayroll->status->value === 'approved')
                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-sky-600 shrink-0" />
                            <span>Siap dibayarkan</span>
                        @else
                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span>Menunggu persetujuan</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Detail Kontrol Status Payroll (Atas-Bawah Rapi di Tablet & Mobile dengan Sidebar Aktif) -->
        <div class="mb-4 p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col xl:flex-row xl:items-center justify-between gap-3.5">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-ui.icon name="banknote" class="w-4 h-4 text-amber-600 shrink-0" />
                    <h3 class="font-extrabold text-xs sm:text-sm text-zinc-900 whitespace-normal break-words">
                        Periode: {{ $selectedPayroll->period_start->translatedFormat('d F Y') }} – {{ $selectedPayroll->period_end->translatedFormat('d F Y') }}
                    </h3>
                    @if($isPastMonth)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-200 shrink-0">Bulan Kemarin</span>
                    @endif
                </div>
                <p class="text-[11px] sm:text-xs text-zinc-500 mt-1 leading-relaxed whitespace-normal break-words">
                    {{ $selectedPayroll->notes ?? 'Draf payroll dihitung otomatis dari transaksi pangkas dan komisi.' }}
                    @if($selectedPayroll->approved_at)
                        · Disetujui oleh <strong class="text-zinc-800">{{ $selectedPayroll->approver?->name ?? 'Owner' }}</strong> pada {{ $selectedPayroll->approved_at->format('d/m/Y H:i') }}
                    @endif
                    @if($isPastMonth && $hasUnpaidItems)
                        · <span class="text-emerald-700 font-bold">Pembayaran bulan kemarin tetap terbuka untuk dicairkan kapan saja.</span>
                    @endif
                </p>
            </div>

            <!-- Tombol Aksi Payroll (Full Kanan Kiri di Tablet & Mobile, Auto di Desktop) -->
            <div class="w-full xl:w-auto pt-3 xl:pt-0 border-t xl:border-t-0 border-zinc-100 flex items-center justify-center shrink-0">
                @if($selectedPayroll->status->value === 'draft')
                    <button
                        type="button"
                        data-action-url="{{ route('owner.payroll.approve', $selectedPayroll) }}"
                        data-message="Payroll periode {{ $selectedPayroll->period_start->translatedFormat('d M') }} – {{ $selectedPayroll->period_end->translatedFormat('d M Y') }} akan disetujui (Approved) dan membuka opsi pembayaran gaji."
                        onclick="openConfirmModal({
                            actionUrl: this.dataset.actionUrl,
                            title: 'Setujui Draf Payroll?',
                            message: this.dataset.message,
                            submitText: 'Ya, Setujui',
                            variant: 'zinc'
                        })"
                        class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-extrabold shadow-sm transition cursor-pointer whitespace-nowrap"
                    >
                        <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Setujui Payroll (Approve)</span>
                    </button>
                @elseif($selectedPayroll->status->value === 'approved')
                    @if($canPayGaji)
                        <button
                            type="button"
                            data-action-url="{{ route('owner.payroll.mark-paid', $selectedPayroll) }}"
                            onclick="openConfirmModal({
                                actionUrl: this.dataset.actionUrl,
                                title: 'Tandai Seluruh Gaji Lunas?',
                                message: 'Semua slip gaji karyawan pada periode ini akan ditandai Lunas / Telah Dibayar.',
                                submitText: 'Ya, Tandai Lunas',
                                variant: 'emerald'
                            })"
                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-extrabold shadow-sm transition cursor-pointer whitespace-nowrap"
                        >
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Tandai Lunas / Telah Dibayar</span>
                        </button>
                    @else
                        <span class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-zinc-100 text-zinc-600 font-extrabold text-xs whitespace-nowrap border border-zinc-200" title="Pembayaran dibuka mulai tanggal {{ $cutoffDay }}">
                            <x-ui.icon name="lock" class="w-4 h-4 text-zinc-400 shrink-0" />
                            <span>Disetujui · Pembayaran Dibuka tgl {{ $cutoffDay }}</span>
                        </span>
                    @endif
                @else
                    <span class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-50 text-emerald-700 font-extrabold text-xs whitespace-nowrap border border-emerald-200">
                        <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Selesai &amp; Lunas Dibayar</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- Tabel & Kartu Rincian Slip Gaji per Karyawan (Pemisahan Gaji Pokok & Komisi) -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3.5 w-full">
                    <div>
                        <h2 class="font-bold flex items-center gap-2 text-sm sm:text-base text-zinc-900">
                            <x-ui.icon name="users" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Rincian Slip Gaji Karyawan</span>
                        </h2>
                        <p class="text-[11px] text-zinc-400 mt-0.5">Daftar terpisah antara karyawan ber-gaji pokok tetap dan mitra bagi hasil</p>
                    </div>

                    <!-- Segmented Filter Tabs (Tetap & Rapi di Bawah Teks pada Mobile/Tablet, Tanpa Scroll) -->
                    <div class="w-full xl:w-auto grid grid-cols-3 xl:flex items-center gap-1 p-1 bg-zinc-100 rounded-xl text-xs font-bold border border-zinc-200/60" id="payroll-filter-tabs">
                        <button
                            type="button"
                            onclick="filterPayrollTab('all')"
                            id="tab-btn-all"
                            class="text-center py-2 px-2 rounded-lg bg-zinc-950 text-amber-400 shadow-sm transition-all cursor-pointer whitespace-nowrap"
                        >
                            Semua ({{ $payrollItems->count() }})
                        </button>
                        <button
                            type="button"
                            onclick="filterPayrollTab('base')"
                            id="tab-btn-base"
                            class="text-center py-2 px-2 rounded-lg text-zinc-600 hover:text-zinc-900 transition-all cursor-pointer whitespace-nowrap"
                        >
                            Gaji Pokok ({{ $baseSalaryItems->count() }})
                        </button>
                        <button
                            type="button"
                            onclick="filterPayrollTab('commission')"
                            id="tab-btn-commission"
                            class="text-center py-2 px-2 rounded-lg text-zinc-600 hover:text-zinc-900 transition-all cursor-pointer whitespace-nowrap"
                        >
                            Mitra Komisi ({{ $commissionOnlyItems->count() }})
                        </button>
                    </div>
                </div>
            </x-slot:header>

            <!-- TAMPILAN 1: KARTU MOBILE & TABLET (< xl) -->
            <div class="block xl:hidden space-y-5">
                <!-- 1. KELOMPOK: KARYAWAN GAJI POKOK & STAF TETAP -->
                <div class="payroll-group-base space-y-3">
                    <!-- Header Kelompok Gaji Pokok Mobile (Card Banner Rapi & Proporsional) -->
                    <div class="p-3 rounded-2xl bg-zinc-100/90 border border-zinc-200/90 shadow-2xs flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-700 grid place-items-center shrink-0 border border-amber-300/40">
                                <x-ui.icon name="banknote" class="w-4 h-4 text-amber-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-zinc-900 leading-tight truncate">Gaji Pokok &amp; Staf Tetap</p>
                                <p class="text-[10px] text-zinc-500 font-medium mt-0.5">{{ $baseSalaryItems->count() }} Karyawan Aktif</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[9px] uppercase font-bold text-zinc-400 block tracking-wider leading-none mb-1">Subtotal</span>
                            <span class="text-xs sm:text-sm font-black text-zinc-950 num">Rp{{ number_format($baseSalaryItems->sum('net_total'), 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Grid Kartu: 1 Kolom Lapang & Lega di Tablet & Mobile -->
                    <div class="grid grid-cols-1 gap-3.5">
                    @foreach($baseSalaryItems as $item)
                        @php
                            $roleLabel = $item->membership?->specialty ?: ($item->membership?->role?->value === 'barber' ? 'Barber' : 'Admin / Kasir');
                        @endphp
                        <div class="p-4 sm:p-4.5 rounded-2xl border border-zinc-200/80 bg-white hover:border-zinc-300 shadow-2xs transition-all space-y-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-700 font-extrabold text-xs grid place-items-center shrink-0">
                                        {{ strtoupper(substr($item->membership?->display_name ?? $item->membership?->user?->name ?? 'U', 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm text-zinc-900 truncate">{{ $item->membership?->display_name ?? $item->membership?->user?->name }}</p>
                                        <p class="text-xs text-zinc-500 flex items-center gap-1.5 flex-wrap mt-0.5">
                                            <span>{{ $roleLabel }}</span>
                                            <span>·</span>
                                            <span>{{ $item->membership?->branch?->name ?? 'Semua Cabang' }}</span>
                                        </p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0 {{ $item->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $item->payment_status === 'paid' ? 'Lunas' : 'Pending' }}
                                </span>
                            </div>

                            <!-- Skema Gaji Badge -->
                            <div>
                                <span class="px-2.5 py-1 rounded-md bg-zinc-100 text-zinc-700 text-xs font-semibold border border-zinc-200/60 inline-block">
                                    {{ $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? 'Skema Standar') }}
                                </span>
                            </div>

                            <!-- Grid Breakdown Angka (Lega & Jelas) -->
                            <div class="grid grid-cols-3 gap-2.5 p-3 rounded-xl bg-zinc-50 border border-zinc-100 text-xs">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Gaji Pokok</span>
                                    @if(($item->scheme_snapshot['base_salary_status'] ?? '') === 'already_paid')
                                        <span class="font-extrabold text-zinc-800 num text-xs sm:text-sm block">Rp{{ number_format($item->base_salary, 0, ',', '.') }}</span>
                                        <span class="inline-flex items-center text-[9px] font-bold text-zinc-500 bg-zinc-200/60 px-1.5 py-0.5 rounded mt-0.5 block w-fit">Sudah Dibayar</span>
                                    @else
                                        <span class="font-extrabold text-zinc-800 num text-xs sm:text-sm block">Rp{{ number_format($item->base_salary, 0, ',', '.') }}</span>
                                    @endif
                                </div>
                                <div class="text-center border-x border-zinc-200/60 px-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Layanan</span>
                                    <span class="font-black text-amber-700 num text-xs sm:text-sm">{{ $item->services_count }}x</span>
                                    <span class="text-[9px] text-zinc-400 block mt-0.5">pangkas</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Komisi</span>
                                    <span class="font-extrabold text-emerald-700 num text-xs sm:text-sm block">+Rp{{ number_format($item->commission_total, 0, ',', '.') }}</span>
                                    @if(!empty($item->scheme_snapshot['already_paid_commissions']) && $item->scheme_snapshot['already_paid_commissions'] > 0)
                                        <div class="text-[9px] text-zinc-400">(Sisa Komisi)</div>
                                    @endif
                                    @if($item->bonus_total > 0)
                                        <div class="text-[10px] text-amber-700 font-bold mt-0.5">+Rp{{ number_format($item->bonus_total, 0, ',', '.') }} bonus</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Take Home Pay Card Highlight Banner -->
                            <div class="p-3 rounded-xl bg-zinc-100/70 border border-zinc-200/60 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-black text-zinc-700 block uppercase tracking-wider">Take Home Pay</span>
                                    <span class="text-[10px] text-zinc-400 font-medium">Total Gaji Bersih</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-base sm:text-lg font-black text-zinc-950 num block">Rp{{ number_format($item->net_total, 0, ',', '.') }}</span>
                                    @if($item->deduction_total > 0)
                                        <div class="text-[10px] text-rose-600 font-bold leading-tight mt-0.5">
                                            -Rp{{ number_format($item->deduction_total, 0, ',', '.') }} karena alpha
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Aksi Perorangan Mobile: Slip & Bayar -->
                            <div class="pt-1 flex items-center justify-between gap-2.5">
                                @if($canAccessPayout)
                                    <button
                                        type="button"
                                        data-slip="{{ json_encode([
                                            'name' => $item->membership?->display_name ?? $item->membership?->user?->name,
                                            'role' => $roleLabel,
                                            'branch' => $item->membership?->branch?->name ?? 'Semua Cabang',
                                            'scheme' => $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? 'Skema Standar'),
                                            'period' => $selectedPayroll->period_start->translatedFormat('d M Y') . ' – ' . $selectedPayroll->period_end->translatedFormat('d M Y'),
                                            'base_salary' => $item->base_salary,
                                            'original_base_salary' => $item->scheme_snapshot['original_base_salary'] ?? $item->base_salary,
                                            'base_salary_status' => $item->scheme_snapshot['base_salary_status'] ?? 'none',
                                            'base_salary_note' => $item->scheme_snapshot['base_salary_note'] ?? '',
                                            'services_count' => $item->services_count,
                                            'commission_total' => $item->commission_total,
                                            'tip_total' => $item->tip_total ?? 0,
                                            'gross_commissions' => $item->scheme_snapshot['gross_commissions'] ?? $item->commission_total,
                                            'already_paid_commissions' => $item->scheme_snapshot['already_paid_commissions'] ?? 0,
                                            'bonus_total' => $item->bonus_total,
                                            'deduction_total' => $item->deduction_total,
                                            'scheme_snapshot' => $item->scheme_snapshot,
                                            'net_total' => $item->net_total,
                                            'status' => $item->payment_status,
                                            'paid_at' => $item->paid_at ? $item->paid_at->translatedFormat('d M Y H:i') : null,
                                        ]) }}"
                                        onclick="openSlipModal(JSON.parse(this.dataset.slip))"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-xs transition cursor-pointer whitespace-nowrap shrink-0"
                                    >
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-600 shrink-0" />
                                        <span>Slip Gaji</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        onclick="alert('Slip gaji karyawan baru dapat dibuka saat mendekati akhir bulan (mulai tanggal {{ $cutoffDay }} {{ $periodEndCarbon->translatedFormat('F Y') }} / H-7).')"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 text-zinc-400 font-bold text-xs cursor-not-allowed opacity-75 whitespace-nowrap shrink-0"
                                        title="Terkunci hingga H-7 akhir bulan (mulai tgl {{ $cutoffDay }})"
                                    >
                                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <span>Slip Gaji</span>
                                    </button>
                                @endif

                                @if($item->payment_status !== 'paid')
                                    @if($canPayGaji && $item->net_total > 0)
                                        <button
                                            type="button"
                                            data-action-url="{{ route('owner.payroll.items.mark-paid', $item) }}"
                                            data-message="Tandai gaji {{ $item->membership?->display_name ?? 'karyawan ini' }} sebesar Rp{{ number_format($item->net_total, 0, ',', '.') }} telah Lunas dibayar?"
                                            onclick="openConfirmModal({
                                                actionUrl: this.dataset.actionUrl,
                                                title: 'Bayar Gaji Karyawan?',
                                                message: this.dataset.message,
                                                submitText: 'Ya, Bayar Sekarang',
                                                variant: 'emerald'
                                            })"
                                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-2xs transition cursor-pointer whitespace-nowrap shrink-0"
                                        >
                                            <x-ui.icon name="check" class="w-3.5 h-3.5 shrink-0" />
                                            <span>Bayar Gaji Ini</span>
                                        </button>
                                    @elseif(! $isApproved)
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200" title="Setujui Payroll terlebih dahulu untuk membuka pembayaran">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                            <span>Menunggu Approve</span>
                                        </span>
                                    @elseif(! $canAccessPayout)
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-zinc-100 text-zinc-500 text-xs font-bold border border-zinc-200" title="Pembayaran dibuka mulai tanggal {{ $cutoffDay }}">
                                            <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                            <span>Buka tgl {{ $cutoffDay }}</span>
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-black whitespace-nowrap shrink-0 border border-emerald-200/60">
                                        <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span>Lunas Dibayar</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>

                <!-- 2. KELOMPOK: MITRA KOMISI & BAGI HASIL -->
                <div class="payroll-group-commission space-y-3">
                    <!-- Header Kelompok Mitra Komisi Mobile (Card Banner Rapi & Proporsional) -->
                    <div class="p-3 rounded-2xl bg-emerald-50/80 border border-emerald-200/80 shadow-2xs flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-700 grid place-items-center shrink-0 border border-emerald-300/40">
                                <x-ui.icon name="scissors" class="w-4 h-4 text-emerald-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-emerald-950 leading-tight truncate">Mitra Komisi &amp; Bagi Hasil</p>
                                <p class="text-[10px] text-emerald-700 font-medium mt-0.5">{{ $commissionOnlyItems->count() }} Mitra Kerja</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[9px] uppercase font-bold text-emerald-600/80 block tracking-wider leading-none mb-1">Subtotal</span>
                            <span class="text-xs sm:text-sm font-black text-emerald-950 num">Rp{{ number_format($commissionOnlyItems->sum('net_total'), 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Grid Kartu: 1 Kolom Lapang & Lega di Tablet & Mobile -->
                    <div class="grid grid-cols-1 gap-3.5">
                    @foreach($commissionOnlyItems as $item)
                        @php
                            $roleLabel = $item->membership?->specialty ?: ($item->membership?->role?->value === 'barber' ? 'Barber' : 'Admin / Kasir');
                            $commPercent = $item->scheme_snapshot['commission_percentage'] ?? ($item->membership?->salaryScheme?->effective_commission_percentage ?? 0);
                            $hasPercentage = ($item->scheme_snapshot['has_percentage_commission'] ?? false)
                                || ($item->membership?->salaryScheme?->has_percentage_commission ?? false)
                                || ($commPercent > 0);
                            $schemeName = $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? ($hasPercentage ? 'Bagi Hasil ' . $commPercent . '%' : 'Komisi Layanan'));
                        @endphp
                        <div class="p-4 sm:p-4.5 rounded-2xl border border-emerald-200/80 bg-white hover:border-emerald-300 shadow-2xs transition-all space-y-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-700 font-extrabold text-xs grid place-items-center shrink-0">
                                        {{ strtoupper(substr($item->membership?->display_name ?? $item->membership?->user?->name ?? 'U', 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm text-zinc-900 truncate">{{ $item->membership?->display_name ?? $item->membership?->user?->name }}</p>
                                        <p class="text-xs text-zinc-500 flex items-center gap-1.5 flex-wrap mt-0.5">
                                            <span>{{ $roleLabel }}</span>
                                            <span>·</span>
                                            <span>{{ $item->membership?->branch?->name ?? 'Semua Cabang' }}</span>
                                        </p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0 {{ $item->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $item->payment_status === 'paid' ? 'Lunas' : 'Pending' }}
                                </span>
                            </div>

                            <!-- Skema Jabatan Badge -->
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($hasPercentage && $commPercent > 0)
                                    <span class="px-2.5 py-1 rounded-md bg-purple-100 text-purple-800 text-xs font-bold border border-purple-200">
                                        Bagi Hasil {{ $commPercent }}%
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200">
                                        {{ $schemeName }}
                                    </span>
                                @endif
                                <span class="text-xs text-zinc-400 font-medium">Murni komisi tanpa gaji pokok</span>
                            </div>

                            <!-- Grid Breakdown Angka Mitra (Lega & Jelas) -->
                            <div class="grid grid-cols-2 gap-2.5 p-3 rounded-xl bg-emerald-50/30 border border-emerald-100 text-xs">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700/80 block mb-0.5">Layanan Selesai</span>
                                    <span class="font-black text-amber-700 num text-xs sm:text-sm">{{ $item->services_count }}x</span>
                                    <span class="text-[9px] text-zinc-400 block mt-0.5">pangkas selesai</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700/80 block mb-0.5">Total Komisi</span>
                                    <span class="font-black text-emerald-700 num text-xs sm:text-sm block">+Rp{{ number_format($item->commission_total, 0, ',', '.') }}</span>
                                    @if(!empty($item->scheme_snapshot['already_paid_commissions']) && $item->scheme_snapshot['already_paid_commissions'] > 0)
                                        <div class="text-[9px] text-zinc-400">(Sisa Komisi)</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Take Home Pay Card Highlight Banner -->
                            <div class="p-3 rounded-xl bg-emerald-50/50 border border-emerald-200/60 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-black text-emerald-950 block uppercase tracking-wider">Take Home Pay</span>
                                    <span class="text-[10px] text-emerald-700/80 font-medium">Total Bagi Hasil Diterima</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-base sm:text-lg font-black text-emerald-700 num block">Rp{{ number_format($item->net_total, 0, ',', '.') }}</span>
                                    @if($item->deduction_total > 0)
                                        <div class="text-[10px] text-rose-600 font-bold leading-tight mt-0.5">
                                            -Rp{{ number_format($item->deduction_total, 0, ',', '.') }} karena alpha
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Aksi Perorangan Mobile: Slip & Bayar -->
                            <div class="pt-1 flex items-center justify-between gap-2.5">
                                @if($canAccessPayout)
                                    <button
                                        type="button"
                                        data-slip="{{ json_encode([
                                            'name' => $item->membership?->display_name ?? $item->membership?->user?->name,
                                            'role' => $roleLabel,
                                            'branch' => $item->membership?->branch?->name ?? 'Semua Cabang',
                                            'scheme' => $schemeName,
                                            'period' => $selectedPayroll->period_start->translatedFormat('d M Y') . ' – ' . $selectedPayroll->period_end->translatedFormat('d M Y'),
                                            'base_salary' => 0,
                                            'original_base_salary' => 0,
                                            'base_salary_status' => 'none',
                                            'base_salary_note' => '',
                                            'services_count' => $item->services_count,
                                            'commission_total' => $item->commission_total,
                                            'tip_total' => $item->tip_total ?? 0,
                                            'gross_commissions' => $item->scheme_snapshot['gross_commissions'] ?? $item->commission_total,
                                            'already_paid_commissions' => $item->scheme_snapshot['already_paid_commissions'] ?? 0,
                                            'bonus_total' => $item->bonus_total,
                                            'deduction_total' => $item->deduction_total,
                                            'scheme_snapshot' => $item->scheme_snapshot,
                                            'net_total' => $item->net_total,
                                            'status' => $item->payment_status,
                                            'paid_at' => $item->paid_at ? $item->paid_at->translatedFormat('d M Y H:i') : null,
                                        ]) }}"
                                        onclick="openSlipModal(JSON.parse(this.dataset.slip))"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-xs transition cursor-pointer whitespace-nowrap shrink-0"
                                    >
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-600 shrink-0" />
                                        <span>Slip Gaji</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        onclick="alert('Slip gaji karyawan baru dapat dibuka saat mendekati akhir bulan (mulai tanggal {{ $cutoffDay }} {{ $periodEndCarbon->translatedFormat('F Y') }} / H-7).')"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 text-zinc-400 font-bold text-xs cursor-not-allowed opacity-75 whitespace-nowrap shrink-0"
                                        title="Terkunci hingga H-7 akhir bulan (mulai tgl {{ $cutoffDay }})"
                                    >
                                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <span>Slip Gaji</span>
                                    </button>
                                @endif

                                @if($item->payment_status !== 'paid')
                                    @if($canPayGaji && $item->net_total > 0)
                                        <button
                                            type="button"
                                            data-action-url="{{ route('owner.payroll.items.mark-paid', $item) }}"
                                            data-message="Tandai gaji {{ $item->membership?->display_name ?? 'karyawan ini' }} sebesar Rp{{ number_format($item->net_total, 0, ',', '.') }} telah Lunas dibayar?"
                                            onclick="openConfirmModal({
                                                actionUrl: this.dataset.actionUrl,
                                                title: 'Bayar Gaji Karyawan?',
                                                message: this.dataset.message,
                                                submitText: 'Ya, Bayar Sekarang',
                                                variant: 'emerald'
                                            })"
                                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-2xs transition cursor-pointer whitespace-nowrap shrink-0"
                                        >
                                            <x-ui.icon name="check" class="w-3.5 h-3.5 shrink-0" />
                                            <span>Bayar Gaji Ini</span>
                                        </button>
                                    @elseif(! $isApproved)
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200" title="Setujui Payroll terlebih dahulu untuk membuka pembayaran">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                            <span>Menunggu Approve</span>
                                        </span>
                                    @elseif(! $canAccessPayout)
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-zinc-100 text-zinc-500 text-xs font-bold border border-zinc-200" title="Pembayaran dibuka mulai tanggal {{ $cutoffDay }}">
                                            <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                            <span>Buka tgl {{ $cutoffDay }}</span>
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-black whitespace-nowrap shrink-0 border border-emerald-200/60">
                                        <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span>Lunas Dibayar</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>

                <!-- Ringkasan Total Mobile -->
                <div class="p-3.5 rounded-2xl bg-zinc-900 text-white space-y-2 mt-4 shadow-sm">
                    <p class="text-xs font-bold text-amber-400 uppercase tracking-wider">Total Keseluruhan Payroll</p>
                    <div class="grid grid-cols-3 gap-2 text-xs border-b border-zinc-800 pb-2">
                        <div>
                            <span class="text-[10px] text-zinc-400 block">Gaji Pokok:</span>
                            <span class="font-bold num">Rp{{ number_format($payrollItems->sum('base_salary'), 0, ',', '.') }}</span>
                        </div>
                        <div class="text-center">
                            <span class="text-[10px] text-zinc-400 block">Layanan:</span>
                            <span class="font-extrabold text-amber-400 num">{{ $payrollItems->sum('services_count') }}x</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-zinc-400 block">Komisi:</span>
                            <span class="font-bold text-emerald-400 num">Rp{{ number_format($payrollItems->sum('commission_total'), 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-xs font-bold text-zinc-300">Total THP Payroll:</span>
                        <span class="text-base font-black text-amber-400 num">Rp{{ number_format($payrollTotalAmount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- TAMPILAN 2: TABEL DESKTOP (>= xl) -->
            <div class="hidden xl:block overflow-x-auto">
                <table class="w-full text-xs min-w-[1020px] table-auto">
                    <thead class="text-xs text-zinc-400 text-left border-b border-zinc-100 bg-zinc-50/60">
                        <tr>
                            <th class="py-3 px-3 whitespace-nowrap">Karyawan / Staf</th>
                            <th class="py-3 px-3 whitespace-nowrap">Skema Gaji</th>
                            <th class="py-3 px-3 text-right whitespace-nowrap">Gaji Pokok</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">Layanan</th>
                            <th class="py-3 px-3 text-right whitespace-nowrap">Komisi Pangkas</th>
                            <th class="py-3 px-3 text-right font-bold whitespace-nowrap">Take Home Pay</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">Status</th>
                            <th class="py-3 px-3 text-right pr-4 whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <!-- SECTION 1: KARYAWAN GAJI POKOK & STAF TETAP -->
                        <tr class="payroll-group-base bg-zinc-100/90 border-y border-zinc-200">
                            <td colspan="8" class="py-2.5 px-3 text-xs font-black text-zinc-800">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 uppercase tracking-wider text-[11px]">
                                        <x-ui.icon name="banknote" class="w-4 h-4 text-amber-600" />
                                        <span>Karyawan Gaji Pokok &amp; Staf Tetap ({{ $baseSalaryItems->count() }} Orang)</span>
                                    </span>
                                    <span class="text-zinc-500 font-semibold text-[11px]">Subtotal: Rp{{ number_format($baseSalaryItems->sum('net_total'), 0, ',', '.') }}</span>
                                </div>
                            </td>
                        </tr>
                        @foreach($baseSalaryItems as $item)
                            @php
                                $roleLabel = $item->membership?->specialty ?: ($item->membership?->role?->value === 'barber' ? 'Barber' : 'Admin / Kasir');
                            @endphp
                            <tr class="payroll-group-base hover:bg-zinc-50/50 transition">
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <p class="font-bold text-xs text-zinc-900">{{ $item->membership?->display_name ?? $item->membership?->user?->name }}</p>
                                    <p class="text-[11px] text-zinc-400">{{ $roleLabel }}</p>
                                    <p class="text-[10px] text-zinc-500 font-medium flex items-center gap-1 mt-0.5">
                                        <x-ui.icon name="store" class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>{{ $item->membership?->branch?->name ?? 'Semua Cabang' }}</span>
                                    </p>
                                </td>
                                <td class="py-3 px-3 text-xs text-zinc-500 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 text-[11px] font-semibold">
                                        {{ $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? 'Skema Standar') }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-semibold text-zinc-700 whitespace-nowrap">
                                    @if(($item->scheme_snapshot['base_salary_status'] ?? '') === 'already_paid')
                                        <div>Rp{{ number_format($item->base_salary, 0, ',', '.') }}</div>
                                        <span class="inline-flex items-center text-[9px] font-bold text-zinc-500 bg-zinc-100 px-1.5 py-0.5 rounded mt-0.5">Sudah Dibayar</span>
                                    @else
                                        <div>Rp{{ number_format($item->base_salary, 0, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center text-xs num font-extrabold text-amber-700 whitespace-nowrap">
                                    {{ $item->services_count }}x
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-semibold text-emerald-700 whitespace-nowrap">
                                    <div>+Rp{{ number_format($item->commission_total, 0, ',', '.') }}</div>
                                    @if(!empty($item->scheme_snapshot['already_paid_commissions']) && $item->scheme_snapshot['already_paid_commissions'] > 0)
                                        <div class="text-[10px] text-zinc-400 font-normal">Sisa setelah -Rp{{ number_format($item->scheme_snapshot['already_paid_commissions'], 0, ',', '.') }}</div>
                                    @endif
                                    @if($item->bonus_total > 0)
                                        <div class="text-[10px] text-amber-700 font-bold">+Rp{{ number_format($item->bonus_total, 0, ',', '.') }} bonus</div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-extrabold text-zinc-950 whitespace-nowrap">
                                    <div>Rp{{ number_format($item->net_total, 0, ',', '.') }}</div>
                                    @if($item->deduction_total > 0)
                                        <div class="text-[10px] text-rose-600 font-semibold leading-tight font-sans">
                                            -Rp{{ number_format($item->deduction_total, 0, ',', '.') }} karena alpha
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $item->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $item->payment_status === 'paid' ? 'Lunas' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right pr-4 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($canAccessPayout)
                                            <button
                                                type="button"
                                                data-slip="{{ json_encode([
                                                    'name' => $item->membership?->display_name ?? $item->membership?->user?->name,
                                                    'role' => $roleLabel,
                                                    'branch' => $item->membership?->branch?->name ?? 'Semua Cabang',
                                                    'scheme' => $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? 'Skema Standar'),
                                                    'period' => $selectedPayroll->period_start->translatedFormat('d M Y') . ' – ' . $selectedPayroll->period_end->translatedFormat('d M Y'),
                                                    'base_salary' => $item->base_salary,
                                                    'original_base_salary' => $item->scheme_snapshot['original_base_salary'] ?? $item->base_salary,
                                                    'base_salary_status' => $item->scheme_snapshot['base_salary_status'] ?? 'none',
                                                    'base_salary_note' => $item->scheme_snapshot['base_salary_note'] ?? '',
                                                    'services_count' => $item->services_count,
                                                    'commission_total' => $item->commission_total,
                                                    'tip_total' => $item->tip_total ?? 0,
                                                    'gross_commissions' => $item->scheme_snapshot['gross_commissions'] ?? $item->commission_total,
                                                    'already_paid_commissions' => $item->scheme_snapshot['already_paid_commissions'] ?? 0,
                                                    'bonus_total' => $item->bonus_total,
                                                    'deduction_total' => $item->deduction_total,
                                                    'scheme_snapshot' => $item->scheme_snapshot,
                                                    'net_total' => $item->net_total,
                                                    'status' => $item->payment_status,
                                                    'paid_at' => $item->paid_at ? $item->paid_at->translatedFormat('d M Y H:i') : null,
                                                ]) }}"
                                                onclick="openSlipModal(JSON.parse(this.dataset.slip))"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-bold text-xs transition cursor-pointer"
                                                title="Lihat & Cetak Slip Gaji"
                                            >
                                                <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                                <span>Slip</span>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                onclick="alert('Slip gaji karyawan baru dapat dibuka saat mendekati akhir bulan (mulai tanggal {{ $cutoffDay }} {{ $periodEndCarbon->translatedFormat('F Y') }} / H-7).')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-400 font-bold text-xs cursor-not-allowed opacity-75"
                                                title="Terkunci hingga H-7 akhir bulan (mulai tgl {{ $cutoffDay }})"
                                            >
                                                <x-ui.icon name="lock" class="w-3 h-3 text-zinc-400" />
                                                <span>Slip</span>
                                            </button>
                                        @endif

                                        @if($item->payment_status !== 'paid')
                                            @if($canPayGaji && $item->net_total > 0)
                                                <button
                                                    type="button"
                                                    data-action-url="{{ route('owner.payroll.items.mark-paid', $item) }}"
                                                    data-message="Tandai gaji {{ $item->membership?->display_name ?? 'karyawan ini' }} sebesar Rp{{ number_format($item->net_total, 0, ',', '.') }} telah Lunas dibayar?"
                                                    onclick="openConfirmModal({
                                                        actionUrl: this.dataset.actionUrl,
                                                        title: 'Bayar Gaji Karyawan?',
                                                        message: this.dataset.message,
                                                        submitText: 'Ya, Bayar',
                                                        variant: 'emerald'
                                                    })"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-[11px] shadow-2xs transition cursor-pointer"
                                                    title="Tandai Lunas untuk karyawan ini"
                                                >
                                                    <x-ui.icon name="check" class="w-3 h-3" />
                                                    <span>Bayar</span>
                                                </button>
                                            @elseif(! $isApproved)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[10px] font-bold border border-amber-200" title="Setujui Payroll terlebih dahulu untuk membuka pembayaran">
                                                    <x-ui.icon name="clock" class="w-3 h-3 text-amber-500" />
                                                    <span>Menunggu Approve</span>
                                                </span>
                                            @elseif(! $canAccessPayout)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-500 text-[10px] font-bold border border-zinc-200" title="Pembayaran dibuka mulai tanggal {{ $cutoffDay }}">
                                                    <x-ui.icon name="lock" class="w-3 h-3 text-zinc-400" />
                                                    <span>Buka tgl {{ $cutoffDay }}</span>
                                                </span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[10px] font-black border border-emerald-200/60">
                                                <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                                <span>Lunas</span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- SECTION 2: MITRA KOMISI & BAGI HASIL -->
                        <tr class="payroll-group-commission bg-emerald-50/70 border-y border-emerald-200">
                            <td colspan="8" class="py-2 px-3 text-xs font-black text-emerald-950">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 uppercase tracking-wider text-[11px]">
                                        <x-ui.icon name="scissors" class="w-4 h-4 text-emerald-600" />
                                        <span>Mitra Komisi &amp; Bagi Hasil ({{ $commissionOnlyItems->count() }} Orang)</span>
                                    </span>
                                    <span class="text-emerald-700 font-semibold text-[11px]">Subtotal: Rp{{ number_format($commissionOnlyItems->sum('net_total'), 0, ',', '.') }}</span>
                                </div>
                            </td>
                        </tr>
                        @foreach($commissionOnlyItems as $item)
                            @php
                                $roleLabel = $item->membership?->specialty ?: ($item->membership?->role?->value === 'barber' ? 'Barber' : 'Admin / Kasir');
                                $commPercent = $item->scheme_snapshot['commission_percentage'] ?? ($item->membership?->salaryScheme?->effective_commission_percentage ?? 0);
                                $hasPercentage = ($item->scheme_snapshot['has_percentage_commission'] ?? false)
                                    || ($item->membership?->salaryScheme?->has_percentage_commission ?? false)
                                    || ($commPercent > 0);
                                $schemeName = $item->scheme_snapshot['name'] ?? ($item->membership?->salaryScheme?->name ?? ($hasPercentage ? 'Bagi Hasil ' . $commPercent . '%' : 'Komisi Layanan'));
                            @endphp
                            <tr class="payroll-group-commission hover:bg-emerald-50/30 transition">
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <p class="font-bold text-xs text-zinc-900">{{ $item->membership?->display_name ?? $item->membership?->user?->name }}</p>
                                    <p class="text-[11px] text-zinc-400">{{ $roleLabel }}</p>
                                    <p class="text-[10px] text-zinc-500 font-medium flex items-center gap-1 mt-0.5">
                                        <x-ui.icon name="store" class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>{{ $item->membership?->branch?->name ?? 'Semua Cabang' }}</span>
                                    </p>
                                </td>
                                <td class="py-3 px-3 text-xs text-zinc-500 whitespace-nowrap">
                                    @if($hasPercentage && $commPercent > 0)
                                        <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 text-[11px] font-bold">
                                            Bagi Hasil {{ $commPercent }}%
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                                            {{ $schemeName }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-semibold text-zinc-400 italic whitespace-nowrap">
                                    -
                                </td>
                                <td class="py-3 px-3 text-center text-xs num font-extrabold text-amber-700 whitespace-nowrap">
                                    {{ $item->services_count }}x
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-semibold text-emerald-700 whitespace-nowrap">
                                    <div>+Rp{{ number_format($item->commission_total, 0, ',', '.') }}</div>
                                    @if(!empty($item->scheme_snapshot['already_paid_commissions']) && $item->scheme_snapshot['already_paid_commissions'] > 0)
                                        <div class="text-[10px] text-zinc-400 font-normal">Sisa setelah -Rp{{ number_format($item->scheme_snapshot['already_paid_commissions'], 0, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right text-xs num font-extrabold text-emerald-700 whitespace-nowrap">
                                    <div>Rp{{ number_format($item->net_total, 0, ',', '.') }}</div>
                                    @if($item->deduction_total > 0)
                                        <div class="text-[10px] text-rose-600 font-semibold leading-tight font-sans">
                                            -Rp{{ number_format($item->deduction_total, 0, ',', '.') }} karena alpha
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $item->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $item->payment_status === 'paid' ? 'Lunas' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right pr-4 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($canAccessPayout)
                                            <button
                                                type="button"
                                                data-slip="{{ json_encode([
                                                    'name' => $item->membership?->display_name ?? $item->membership?->user?->name,
                                                    'role' => $roleLabel,
                                                    'branch' => $item->membership?->branch?->name ?? 'Semua Cabang',
                                                    'scheme' => $schemeName,
                                                    'period' => $selectedPayroll->period_start->translatedFormat('d M Y') . ' – ' . $selectedPayroll->period_end->translatedFormat('d M Y'),
                                                    'base_salary' => 0,
                                                    'original_base_salary' => 0,
                                                    'base_salary_status' => 'none',
                                                    'base_salary_note' => '',
                                                    'services_count' => $item->services_count,
                                                    'commission_total' => $item->commission_total,
                                                    'tip_total' => $item->tip_total ?? 0,
                                                    'gross_commissions' => $item->scheme_snapshot['gross_commissions'] ?? $item->commission_total,
                                                    'already_paid_commissions' => $item->scheme_snapshot['already_paid_commissions'] ?? 0,
                                                    'bonus_total' => $item->bonus_total,
                                                    'deduction_total' => $item->deduction_total,
                                                    'scheme_snapshot' => $item->scheme_snapshot,
                                                    'net_total' => $item->net_total,
                                                    'status' => $item->payment_status,
                                                    'paid_at' => $item->paid_at ? $item->paid_at->translatedFormat('d M Y H:i') : null,
                                                ]) }}"
                                                onclick="openSlipModal(JSON.parse(this.dataset.slip))"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-bold text-xs transition cursor-pointer"
                                                title="Lihat & Cetak Slip Gaji"
                                            >
                                                <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                                <span>Slip</span>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                onclick="alert('Slip gaji karyawan baru dapat dibuka saat mendekati akhir bulan (mulai tanggal {{ $cutoffDay }} {{ $periodEndCarbon->translatedFormat('F Y') }} / H-7).')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-400 font-bold text-xs cursor-not-allowed opacity-75"
                                                title="Terkunci hingga H-7 akhir bulan (mulai tgl {{ $cutoffDay }})"
                                            >
                                                <x-ui.icon name="lock" class="w-3 h-3 text-zinc-400" />
                                                <span>Slip</span>
                                            </button>
                                        @endif

                                        @if($item->payment_status !== 'paid')
                                            @if($canPayGaji && $item->net_total > 0)
                                                <button
                                                    type="button"
                                                    data-action-url="{{ route('owner.payroll.items.mark-paid', $item) }}"
                                                    data-message="Tandai gaji {{ $item->membership?->display_name ?? 'karyawan ini' }} sebesar Rp{{ number_format($item->net_total, 0, ',', '.') }} telah Lunas dibayar?"
                                                    onclick="openConfirmModal({
                                                        actionUrl: this.dataset.actionUrl,
                                                        title: 'Bayar Gaji Karyawan?',
                                                        message: this.dataset.message,
                                                        submitText: 'Ya, Bayar',
                                                        variant: 'emerald'
                                                    })"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-[11px] shadow-2xs transition cursor-pointer"
                                                    title="Tandai Lunas untuk karyawan ini"
                                                >
                                                    <x-ui.icon name="check" class="w-3 h-3" />
                                                    <span>Bayar</span>
                                                </button>
                                            @elseif(! $isApproved)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[10px] font-bold border border-amber-200" title="Setujui Payroll terlebih dahulu untuk membuka pembayaran">
                                                    <x-ui.icon name="clock" class="w-3 h-3 text-amber-500" />
                                                    <span>Menunggu Approve</span>
                                                </span>
                                            @elseif(! $canAccessPayout)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-500 text-[10px] font-bold border border-zinc-200" title="Pembayaran dibuka mulai tanggal {{ $cutoffDay }}">
                                                    <x-ui.icon name="lock" class="w-3 h-3 text-zinc-400" />
                                                    <span>Buka tgl {{ $cutoffDay }}</span>
                                                </span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[10px] font-black border border-emerald-200/60">
                                                <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                                <span>Lunas</span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-zinc-200 bg-zinc-50/50 text-xs font-bold">
                        <tr>
                            <td colspan="2" class="py-3 px-3.5 text-zinc-700">TOTAL KESELURUHAN</td>
                            <td class="py-3 px-3.5 text-right num text-zinc-700">Rp{{ number_format($payrollItems->sum('base_salary'), 0, ',', '.') }}</td>
                            <td class="py-3 px-3.5 text-center num text-amber-800">{{ $payrollItems->sum('services_count') }}x</td>
                            <td class="py-3 px-3.5 text-right num text-emerald-700">Rp{{ number_format($payrollItems->sum('commission_total'), 0, ',', '.') }}</td>
                            <td class="py-3 px-3.5 text-right num text-zinc-950 font-extrabold">Rp{{ number_format($payrollTotalAmount, 0, ',', '.') }}</td>
                            <td colspan="2" class="py-3 px-3.5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-ui.card>
    @else
        <x-ui.card class="text-center py-12">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 grid place-items-center mx-auto mb-3">
                <x-ui.icon name="banknote" class="w-6 h-6" />
            </div>
            <h3 class="font-extrabold text-base text-zinc-900">Belum Ada Periode Payroll</h3>
            <p class="text-xs text-zinc-500 max-w-md mx-auto mt-1 mb-4">
                Klik tombol di bawah untuk membuat draf perhitungan gaji &amp; komisi barber secara otomatis untuk bulan ini.
            </p>
            <button
                type="button"
                onclick="openGeneratePayrollModal()"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-extrabold shadow-sm transition cursor-pointer"
            >
                <x-ui.icon name="plus" class="w-4 h-4" />
                <span>Hitung Payroll Bulan Ini</span>
            </button>
        </x-ui.card>
    @endif

    <!-- Dialog Modal: Generate Payroll Baru -->
    <dialog id="modal-generate-payroll" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs m-auto fixed inset-0 w-[calc(100%-2rem)] max-w-lg overflow-hidden border border-zinc-200/80 bg-white">
        <!-- Header Dialog -->
        <div class="px-5 sm:px-6 py-4 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/80">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                </div>
                <div>
                    <h3 class="font-extrabold text-sm sm:text-base text-zinc-900 leading-tight">Hitung &amp; Generate Payroll</h3>
                    <p class="text-[11px] text-zinc-500 mt-0.5">Pilih periode kerja untuk draf perhitungan gaji &amp; komisi</p>
                </div>
            </div>
            <button
                type="button"
                onclick="document.getElementById('modal-generate-payroll').close()"
                class="p-1.5 rounded-xl text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200/60 transition cursor-pointer"
                title="Tutup"
            >
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('owner.payroll.generate') }}" class="p-5 sm:p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            @csrf

            <!-- 1. Pilihan Cepat Periode -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700">Pilihan Cepat Periode</label>
                    <span class="text-[10px] text-zinc-400 font-medium">Klik untuk isi tanggal otomatis</span>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 mb-2">
                    <button
                        type="button"
                        onclick="setPayrollPreset(1)"
                        id="preset-btn-1"
                        data-start="{{ now()->startOfMonth()->toDateString() }}"
                        data-end="{{ now()->startOfMonth()->addDays(6)->toDateString() }}"
                        class="preset-choice-btn py-2 px-2 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-50 hover:border-amber-300 text-zinc-700 hover:text-amber-900 transition cursor-pointer text-center group"
                    >
                        <span class="block text-xs font-bold leading-tight">Minggu 1</span>
                        <span class="block text-[10px] text-zinc-400 group-hover:text-amber-700/80 mt-0.5">Tgl 1 – 7</span>
                    </button>
                    <button
                        type="button"
                        onclick="setPayrollPreset(2)"
                        id="preset-btn-2"
                        data-start="{{ now()->startOfMonth()->addDays(7)->toDateString() }}"
                        data-end="{{ now()->startOfMonth()->addDays(13)->toDateString() }}"
                        class="preset-choice-btn py-2 px-2 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-50 hover:border-amber-300 text-zinc-700 hover:text-amber-900 transition cursor-pointer text-center group"
                    >
                        <span class="block text-xs font-bold leading-tight">Minggu 2</span>
                        <span class="block text-[10px] text-zinc-400 group-hover:text-amber-700/80 mt-0.5">Tgl 8 – 14</span>
                    </button>
                    <button
                        type="button"
                        onclick="setPayrollPreset(3)"
                        id="preset-btn-3"
                        data-start="{{ now()->startOfMonth()->addDays(14)->toDateString() }}"
                        data-end="{{ now()->startOfMonth()->addDays(20)->toDateString() }}"
                        class="preset-choice-btn py-2 px-2 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-50 hover:border-amber-300 text-zinc-700 hover:text-amber-900 transition cursor-pointer text-center group"
                    >
                        <span class="block text-xs font-bold leading-tight">Minggu 3</span>
                        <span class="block text-[10px] text-zinc-400 group-hover:text-amber-700/80 mt-0.5">Tgl 15 – 21</span>
                    </button>
                    <button
                        type="button"
                        onclick="setPayrollPreset(4)"
                        id="preset-btn-4"
                        data-start="{{ now()->startOfMonth()->addDays(21)->toDateString() }}"
                        data-end="{{ now()->endOfMonth()->toDateString() }}"
                        class="preset-choice-btn py-2 px-2 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-emerald-50 hover:border-emerald-300 text-zinc-700 hover:text-emerald-900 transition cursor-pointer text-center group"
                    >
                        <span class="block text-xs font-bold leading-tight">Minggu 4</span>
                        <span class="block text-[10px] text-zinc-400 group-hover:text-emerald-700/80 mt-0.5">Tgl 22 – Akhir</span>
                    </button>
                </div>

                <div class="space-y-1.5 pt-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-zinc-600 flex items-center gap-1.5">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-amber-600" />
                            <span>1 Bulan Penuh (Gaji Pokok &amp; Komisi):</span>
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            type="button"
                            onclick="setPayrollPreset('last_month')"
                            id="preset-btn-last_month"
                            data-start="{{ now()->subMonth()->startOfMonth()->toDateString() }}"
                            data-end="{{ now()->subMonth()->endOfMonth()->toDateString() }}"
                            class="preset-choice-btn py-2 px-1.5 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-50 hover:border-amber-300 text-zinc-700 hover:text-amber-900 transition cursor-pointer text-center group"
                        >
                            <span class="block text-xs font-bold leading-tight">Bulan Kemarin</span>
                            <span class="block text-[10px] text-zinc-400 group-hover:text-amber-700/80 mt-0.5 truncate">{{ now()->subMonth()->translatedFormat('F Y') }}</span>
                        </button>
                        <button
                            type="button"
                            onclick="setPayrollPreset('this_month')"
                            id="preset-btn-this_month"
                            data-start="{{ now()->startOfMonth()->toDateString() }}"
                            data-end="{{ now()->endOfMonth()->toDateString() }}"
                            class="preset-choice-btn py-2 px-1.5 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-100 text-zinc-800 transition cursor-pointer text-center group"
                        >
                            <span class="block text-xs font-extrabold leading-tight text-zinc-900">Bulan Ini (Sekarang)</span>
                            <span class="block text-[10px] text-zinc-500 font-medium mt-0.5 truncate">{{ now()->translatedFormat('F Y') }}</span>
                        </button>
                        <button
                            type="button"
                            onclick="setPayrollPreset('next_month')"
                            id="preset-btn-next_month"
                            data-start="{{ now()->addMonth()->startOfMonth()->toDateString() }}"
                            data-end="{{ now()->addMonth()->endOfMonth()->toDateString() }}"
                            class="preset-choice-btn py-2 px-1.5 rounded-xl border border-zinc-200 bg-zinc-50/80 hover:bg-amber-50 hover:border-amber-300 text-zinc-700 hover:text-amber-900 transition cursor-pointer text-center group"
                        >
                            <span class="block text-xs font-bold leading-tight">Bulan Depan (Besok)</span>
                            <span class="block text-[10px] text-zinc-400 group-hover:text-amber-700/80 mt-0.5 truncate">{{ now()->addMonth()->translatedFormat('F Y') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Rentang Tanggal Input -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Mulai Periode <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <input
                        type="date"
                        name="period_start"
                        required
                        value="{{ $selectedPayroll ? $selectedPayroll->period_start->format('Y-m-d') : now()->startOfMonth()->toDateString() }}"
                        onchange="updatePayrollInfoBox()"
                        class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Selesai Periode <span class="text-rose-500 font-bold ml-0.5">*</span></label>
                    <input
                        type="date"
                        name="period_end"
                        required
                        value="{{ $selectedPayroll ? $selectedPayroll->period_end->format('Y-m-d') : now()->endOfMonth()->toDateString() }}"
                        onchange="updatePayrollInfoBox()"
                        class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500"
                    />
                </div>
            </div>

            <!-- 3. Dynamic Rule Guide / Status Box -->
            <div id="payroll-period-info-box" class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-xs transition-all">
                <!-- Mode Mingguan -->
                <div id="payroll-info-weekly" class="hidden text-amber-950">
                    <div class="flex items-center gap-2 font-bold text-amber-900">
                        <div class="w-6 h-6 rounded-lg bg-amber-500/15 text-amber-700 grid place-items-center shrink-0">
                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600" />
                        </div>
                        <span class="text-xs sm:text-sm font-extrabold text-amber-950">Skema Mingguan: Pencairan Bagi Hasil</span>
                    </div>
                    <p class="text-[11px] text-amber-900/90 mt-1.5 leading-relaxed pl-8">
                        Hanya <strong>komisi / bagi hasil</strong> layanan pangkas yang akan dicairkan. Gaji pokok staf tetap otomatis <strong>ditahan</strong> dan baru dibuka saat periode akhir bulan.
                    </p>
                </div>

                <!-- Mode Akhir Bulan / 1 Bulan Penuh -->
                <div id="payroll-info-endofmonth" class="text-emerald-950">
                    <div class="flex items-center gap-2 font-bold text-emerald-900">
                        <div class="w-6 h-6 rounded-lg bg-emerald-500/15 text-emerald-700 grid place-items-center shrink-0">
                            <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-600" />
                        </div>
                        <span class="text-xs sm:text-sm font-extrabold text-emerald-950">Skema Akhir Bulan: Gaji Pokok &amp; Sisa Komisi</span>
                    </div>
                    <p class="text-[11px] text-emerald-900/90 mt-1.5 leading-relaxed pl-8">
                        <strong>Gaji Pokok</strong> staf tetap (1x per bulan) dan seluruh <strong>sisa bagi hasil</strong> yang belum dibayarkan di minggu sebelumnya dibuka untuk pencairan.
                    </p>
                    <div id="payroll-info-cutoff-warning" class="mt-2.5 pt-2.5 border-t border-emerald-200/70 {{ now()->day >= ($currentCutoffDay ?? 24) ? 'hidden' : '' }}">
                        <p class="text-[11px] text-amber-800 font-semibold flex items-start gap-1.5 pl-8">
                            <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
                            <span>Catatan: Hari ini belum menyentuh tanggal {{ $currentCutoffDay ?? 24 }} (H-7 akhir bulan). Tombol pembayaran untuk staf bergaji pokok tetap akan terkunci (abu-abu) sampai mendekati akhir bulan.</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 4. Filter Cabang -->
            <div>
                <x-ui.select
                    label="Cabang / Outlet (Opsional)"
                    name="branch_id"
                    icon="store"
                    placeholder="Semua Cabang (Seluruh Usaha)"
                    :options="$branchFilterOptions"
                    search-placeholder="Cari cabang..."
                />
                <p class="text-[11px] text-zinc-400 mt-1">Kosongkan bila ingin menghitung seluruh cabang.</p>
            </div>

            <!-- 5. Filter Karyawan -->
            <div>
                <x-ui.select
                    label="Pilih Karyawan (Opsional / Khusus Per Orangan)"
                    name="membership_id"
                    icon="users"
                    placeholder="Semua Karyawan (Seluruh Staf)"
                    :options="$staffSelectOptions"
                    search-placeholder="Cari nama karyawan..."
                />
                <p class="text-[11px] text-zinc-400 mt-1">Pilih staf tertentu jika hanya ingin memproses slip per orangan, atau kosongkan untuk menghitung semua karyawan.</p>
            </div>

            <!-- 6. Footer Modal Aksi -->
            <div class="pt-2 flex items-center justify-end gap-2 border-t border-zinc-100">
                <button
                    type="button"
                    onclick="document.getElementById('modal-generate-payroll').close()"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="sm" variant="primary">
                    <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-zinc-950 mr-1" />
                    <span>Mulai Hitung Payroll</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <!-- Dialog Modal: Slip Gaji Digital Resmi Karyawan (Per Orangan) -->
    <dialog id="modal-slip-gaji" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 m-auto fixed inset-0 w-[calc(100%-2rem)] max-w-lg overflow-hidden border border-zinc-200 bg-white">
        <!-- Header Dialog (Clean & Minimalist, No Print) -->
        <div class="px-5 py-3.5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/90 no-print">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 grid place-items-center">
                    <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                </div>
                <div>
                    <h3 class="font-extrabold text-xs sm:text-sm text-zinc-900 leading-none">Slip Gaji Karyawan</h3>
                    <p class="text-[10px] text-zinc-400 mt-0.5">Rincian pendapatan resmi periode berjalan</p>
                </div>
            </div>
            <button
                type="button"
                onclick="document.getElementById('modal-slip-gaji').close()"
                class="p-1.5 rounded-xl text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200/60 transition cursor-pointer"
                title="Tutup"
            >
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <!-- Slip Content Area (Printable, Receipt / Invoice Style) -->
        <div id="slip-print-content" class="p-5 sm:p-6 bg-white space-y-4 sm:space-y-5 text-zinc-900 font-sans max-h-[75vh] overflow-y-auto">
            <!-- Kop Barbershop & Status Dokumen -->
            <div class="border-b border-zinc-200 pb-3.5 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-md bg-zinc-950 text-amber-400 grid place-items-center shrink-0">
                            <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                        </span>
                        <h2 class="text-sm sm:text-base font-black tracking-tight uppercase text-zinc-950 truncate">Barber King</h2>
                    </div>
                    <p class="text-[11px] font-semibold text-zinc-500 mt-1 flex items-center gap-1 truncate">
                        <x-ui.icon name="store" class="w-3 h-3 text-zinc-400 shrink-0" />
                        <span id="slip-branch-name">Semua Cabang</span>
                    </p>
                </div>

                <div class="text-right shrink-0 flex flex-col items-end gap-1">
                    <span id="slip-status-badge" class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800">PENDING</span>
                    <span class="text-[9px] uppercase font-bold text-zinc-400 block tracking-wider">Periode Penggajian</span>
                    <p class="text-xs font-bold text-zinc-800 num" id="slip-period">-</p>
                </div>
            </div>

            <!-- Identitas Karyawan & Skema Penggajian (Card 2 Kolom) -->
            <div class="p-3.5 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 text-xs grid grid-cols-2 gap-3">
                <div class="min-w-0">
                    <span class="text-[9px] uppercase font-bold text-zinc-400 block tracking-wider">Karyawan</span>
                    <p class="font-black text-xs sm:text-sm text-zinc-950 mt-0.5 truncate" id="slip-employee-name">-</p>
                    <p class="text-[10px] sm:text-[11px] text-zinc-500 font-medium truncate" id="slip-employee-role">-</p>
                </div>
                <div class="min-w-0 text-right sm:text-left sm:border-l sm:border-zinc-200/60 sm:pl-3">
                    <span class="text-[9px] uppercase font-bold text-zinc-400 block tracking-wider">Skema Gaji</span>
                    <p class="font-bold text-xs text-zinc-800 mt-0.5 truncate" id="slip-scheme">-</p>
                    <span id="slip-scheme-badge" class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-zinc-200/60 text-zinc-600">
                        Gaji Pokok + Komisi
                    </span>
                </div>
            </div>

            <!-- Rincian Pendapatan (Structured Breakdown) -->
            <div class="space-y-2">
                <div class="flex items-center justify-between px-0.5">
                    <span class="text-[10px] font-extrabold uppercase text-zinc-400 tracking-wider">Rincian Komponen Gaji</span>
                    <span class="text-[10px] font-extrabold uppercase text-zinc-400 tracking-wider">Jumlah</span>
                </div>

                <div class="rounded-xl border border-zinc-200/80 bg-white overflow-hidden divide-y divide-zinc-100 text-xs">
                    <!-- Gaji Pokok (Otomatis disembunyikan untuk Mitra Bagi Hasil) -->
                    <div id="slip-base-salary-row" class="p-2.5 sm:p-3 flex items-center justify-between gap-2 hover:bg-zinc-50/50 transition">
                        <div>
                            <p class="font-bold text-zinc-900 leading-tight">Gaji Pokok Efektif</p>
                            <p class="text-[10px] text-zinc-400 mt-0.5" id="slip-base-salary-note">Gaji pokok bulanan tetap</p>
                        </div>
                        <span class="font-black text-zinc-900 num text-xs sm:text-sm shrink-0" id="slip-base-salary">Rp0</span>
                    </div>

                    <!-- Komisi / Bagi Hasil Layanan -->
                    <div class="p-2.5 sm:p-3 flex items-center justify-between gap-2 hover:bg-zinc-50/50 transition">
                        <div>
                            <p class="font-bold text-zinc-900 leading-tight" id="slip-commission-title">Komisi Layanan Pangkas</p>
                            <p class="text-[10px] text-zinc-400 mt-0.5" id="slip-services-count">0x Layanan Selesai</p>
                        </div>
                        <span class="font-black text-emerald-700 num text-xs sm:text-sm shrink-0" id="slip-commission-total">+Rp0</span>
                    </div>

                    <!-- Bonus Target (Opsional) -->
                    <div id="slip-bonus-row" style="display: none;" class="p-2.5 sm:p-3 flex items-center justify-between gap-2 bg-amber-50/20">
                        <div>
                            <p class="font-bold text-zinc-900 leading-tight">Bonus Target Layanan</p>
                            <p class="text-[10px] text-zinc-400 mt-0.5">Bonus reward pencapaian target pangkas</p>
                        </div>
                        <span class="font-black text-amber-700 num text-xs sm:text-sm shrink-0" id="slip-bonus-total">+Rp0</span>
                    </div>

                    <!-- Potongan Alpha / Denda Absen -->
                    <div id="slip-deduction-row" style="display: none;" class="p-2.5 sm:p-3 flex items-center justify-between gap-2 bg-rose-50/40 border-t border-rose-100/60">
                        <div>
                            <p class="font-bold text-rose-900 leading-tight" id="slip-deduction-title">Potongan Denda Alpha</p>
                            <p class="text-[10px] text-rose-500 mt-0.5" id="slip-deduction-note">Ketidakhadiran tanpa izin</p>
                        </div>
                        <span class="font-black text-rose-700 num text-xs sm:text-sm shrink-0" id="slip-deduction-total">-Rp0</span>
                    </div>
                </div>

                <!-- Total Take Home Pay Banner -->
                <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-950 text-white flex items-center justify-between gap-3 shadow-2xs">
                    <div>
                        <span class="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">Gaji Bersih Diterima</span>
                        <span class="text-xs sm:text-sm font-black text-amber-400 tracking-tight">TAKE HOME PAY</span>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-lg sm:text-xl font-black text-amber-400 num block" id="slip-net-total">Rp0</span>
                        <span class="text-[10px] text-rose-400 font-semibold hidden font-mono" id="slip-net-deduction-hint">-Rp0 karena alpha</span>
                    </div>
                </div>
            </div>

            <!-- Pengesahan / Tanda Tangan Resmi -->
            <div class="pt-4 border-t border-zinc-200/80 grid grid-cols-2 gap-4 text-center text-xs">
                <div>
                    <span class="text-[10px] font-medium text-zinc-400 block mb-14 sm:mb-16">Penerima (Karyawan),</span>
                    <div class="border-t border-zinc-300 pt-1.5 w-32 sm:w-44 mx-auto">
                        <span class="text-zinc-900 font-bold text-xs truncate block" id="slip-employee-signature-name">( Nama Karyawan )</span>
                    </div>
                </div>
                <div>
                    <span class="text-[10px] font-medium text-zinc-400 block mb-14 sm:mb-16">Owner / Pengelola,</span>
                    <div class="border-t border-zinc-300 pt-1.5 w-32 sm:w-44 mx-auto">
                        <span class="text-zinc-900 font-bold text-xs truncate block">( {{ auth()->user()?->name ?? 'Pak Rudi' }} )</span>
                    </div>
                </div>
            </div>

            <p class="text-[9px] sm:text-[10px] text-zinc-400 text-center italic pt-1">
                * Dokumen slip gaji ini diterbitkan secara sah melalui Sistem Barbershop POS.
            </p>
        </div>

        <!-- Action Footer (Clean, Non-print) -->
        <div class="p-3.5 sm:p-4 border-t border-zinc-100 bg-zinc-50 flex items-center justify-end gap-2 no-print">
            <button
                type="button"
                onclick="document.getElementById('modal-slip-gaji').close()"
                class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200 transition cursor-pointer"
            >
                Tutup
            </button>
            @if($canPrintSlip)
                <button
                    type="button"
                    onclick="printSlip()"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-black shadow-2xs transition cursor-pointer"
                >
                    <x-ui.icon name="printer" class="w-3.5 h-3.5" />
                    <span>Cetak Slip</span>
                </button>
            @else
                <a
                    href="{{ route('owner.subscription') }}"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 text-xs font-black shadow-2xs transition cursor-pointer"
                    title="Fitur Cetak Slip Gaji Resmi Eksklusif Paket Business"
                >
                    <x-ui.icon name="crown" class="w-3.5 h-3.5" />
                    <span>Cetak Slip (Eksklusif Business)</span>
                </a>
            @endif
        </div>
    </dialog>

    <!-- Dialog Modal: Konfirmasi Aksi Kecil (Setujui / Bayar Gaji) -->
    <dialog id="modal-confirm-action" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 m-auto fixed inset-0 w-[calc(100%-2.5rem)] max-w-sm overflow-hidden border border-zinc-200 bg-white">
        <div class="p-5 sm:p-6 text-center">
            <!-- Icon Bulat Dinamis -->
            <div id="confirm-modal-icon-bg" class="w-12 h-12 rounded-2xl mx-auto flex items-center justify-center mb-3 bg-emerald-50 text-emerald-600 border border-emerald-200/60">
                <x-ui.icon name="check" class="w-6 h-6" />
            </div>

            <h3 id="confirm-modal-title" class="font-black text-sm sm:text-base text-zinc-900 leading-snug">
                Konfirmasi Pembayaran
            </h3>
            <p id="confirm-modal-message" class="text-xs text-zinc-500 mt-1.5 leading-relaxed">
                Apakah Anda yakin ingin memproses aksi ini?
            </p>

            <!-- Form target untuk POST action -->
            <form id="confirm-modal-form" method="POST" action="" class="mt-5 flex items-center justify-center gap-2">
                @csrf
                <button
                    type="button"
                    onclick="document.getElementById('modal-confirm-action').close()"
                    class="flex-1 px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-100 transition cursor-pointer border border-zinc-200"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="confirm-modal-submit-btn"
                    class="flex-1 px-4 py-2.5 rounded-xl text-xs font-black shadow-sm transition cursor-pointer bg-emerald-600 hover:bg-emerald-500 text-white"
                >
                    Ya, Lanjutkan
                </button>
            </form>
        </div>
    </dialog>

    <!-- JavaScript Modul Payroll -->
    <script>
        function filterPayrollTab(type) {
            var baseEls = document.querySelectorAll('.payroll-group-base');
            var commEls = document.querySelectorAll('.payroll-group-commission');
            var btnAll = document.getElementById('tab-btn-all');
            var btnBase = document.getElementById('tab-btn-base');
            var btnComm = document.getElementById('tab-btn-commission');
            
            var allBtns = [btnAll, btnBase, btnComm];
            allBtns.forEach(function(b) {
                if (!b) return;
                b.classList.remove('bg-zinc-950', 'text-amber-400', 'shadow-sm');
                b.classList.add('text-zinc-600', 'hover:text-zinc-900');
            });

            if (type === 'all') {
                baseEls.forEach(function(el) { el.style.display = ''; });
                commEls.forEach(function(el) { el.style.display = ''; });
                if (btnAll) {
                    btnAll.classList.add('bg-zinc-950', 'text-amber-400', 'shadow-sm');
                    btnAll.classList.remove('text-zinc-600', 'hover:text-zinc-900');
                }
            } else if (type === 'base') {
                baseEls.forEach(function(el) { el.style.display = ''; });
                commEls.forEach(function(el) { el.style.display = 'none'; });
                if (btnBase) {
                    btnBase.classList.add('bg-zinc-950', 'text-amber-400', 'shadow-sm');
                    btnBase.classList.remove('text-zinc-600', 'hover:text-zinc-900');
                }
            } else if (type === 'commission') {
                baseEls.forEach(function(el) { el.style.display = 'none'; });
                commEls.forEach(function(el) { el.style.display = ''; });
                if (btnComm) {
                    btnComm.classList.add('bg-zinc-950', 'text-amber-400', 'shadow-sm');
                    btnComm.classList.remove('text-zinc-600', 'hover:text-zinc-900');
                }
            }
        }

        function formatRupiah(num) {
            return 'Rp' + (num || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function openSlipModal(data) {
            var branchEl = document.getElementById('slip-branch-name');
            var nameEl = document.getElementById('slip-employee-name');
            var roleEl = document.getElementById('slip-employee-role');
            var periodEl = document.getElementById('slip-period');
            var schemeEl = document.getElementById('slip-scheme');

            if (branchEl) branchEl.textContent = data.branch || 'Semua Cabang';
            if (nameEl) nameEl.textContent = data.name || '-';
            if (roleEl) roleEl.textContent = data.role || '-';
            if (periodEl) periodEl.textContent = data.period || '-';
            if (schemeEl) schemeEl.textContent = data.scheme || '-';
            
            var baseSalaryRow = document.getElementById('slip-base-salary-row');
            var commTitle = document.getElementById('slip-commission-title');
            var servicesCountEl = document.getElementById('slip-services-count');
            var schemeBadge = document.getElementById('slip-scheme-badge');

            var isPureCommission = (!data.base_salary || data.base_salary <= 0) && (!data.original_base_salary || data.original_base_salary <= 0);

            if (isPureCommission) {
                if (baseSalaryRow) baseSalaryRow.style.display = 'none';
                if (commTitle) commTitle.textContent = 'Bagi Hasil Layanan';
                if (servicesCountEl) {
                    if (data.already_paid_commissions && data.already_paid_commissions > 0) {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai · Sisa Pendapatan (Total ' + formatRupiah(data.gross_commissions) + ' - Dibayar Sebelumnya ' + formatRupiah(data.already_paid_commissions) + ')';
                    } else {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai · Murni Bagi Hasil';
                    }
                }
                if (schemeBadge) {
                    schemeBadge.className = 'inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800';
                    schemeBadge.textContent = 'Murni Bagi Hasil (Tanpa Gaji Pokok)';
                }
            } else if (data.base_salary > 0) {
                if (baseSalaryRow) baseSalaryRow.style.display = '';
                var baseEl = document.getElementById('slip-base-salary');
                if (baseEl) baseEl.textContent = formatRupiah(data.base_salary);
                var baseNoteEl = document.getElementById('slip-base-salary-note');
                if (baseNoteEl) baseNoteEl.textContent = 'Gaji pokok bulanan tetap';
                if (commTitle) commTitle.textContent = 'Komisi Layanan Pangkas';
                if (servicesCountEl) {
                    if (data.already_paid_commissions && data.already_paid_commissions > 0) {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai · Sisa Komisi (Total ' + formatRupiah(data.gross_commissions) + ' - Dibayar Sebelumnya ' + formatRupiah(data.already_paid_commissions) + ')';
                    } else {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai';
                    }
                }
                if (schemeBadge) {
                    schemeBadge.className = 'inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-zinc-200/60 text-zinc-600';
                    schemeBadge.textContent = (data.commission_total > 0 || data.services_count > 0) ? 'Gaji Pokok + Komisi' : 'Gaji Pokok Tetap';
                }
            } else {
                if (data.base_salary_status === 'already_paid') {
                    if (baseSalaryRow) baseSalaryRow.style.display = '';
                    var baseEl = document.getElementById('slip-base-salary');
                    if (baseEl) baseEl.textContent = 'Rp0';
                    var baseNoteEl = document.getElementById('slip-base-salary-note');
                    if (baseNoteEl) baseNoteEl.textContent = 'Gaji pokok sudah dibayar pada payroll sebelumnya';
                    if (schemeBadge) {
                        schemeBadge.className = 'inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-zinc-100 text-zinc-700';
                        schemeBadge.textContent = 'Gaji Pokok Sudah Dibayar';
                    }
                } else {
                    if (baseSalaryRow) baseSalaryRow.style.display = 'none';
                    if (schemeBadge) {
                        schemeBadge.className = 'inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800';
                        schemeBadge.textContent = 'Mitra Komisi (Bagi Hasil)';
                    }
                }
                if (commTitle) commTitle.textContent = 'Komisi Layanan Pangkas';
                if (servicesCountEl) {
                    if (data.already_paid_commissions && data.already_paid_commissions > 0) {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai · Sisa Komisi (Total ' + formatRupiah(data.gross_commissions) + ' - Dibayar Sebelumnya ' + formatRupiah(data.already_paid_commissions) + ')';
                    } else {
                        servicesCountEl.textContent = (data.services_count || 0) + 'x Layanan Selesai';
                    }
                }
            }

            var commTotalEl = document.getElementById('slip-commission-total');
            if (commTotalEl) commTotalEl.textContent = formatRupiah(data.commission_total);
            
            var bonusRow = document.getElementById('slip-bonus-row');
            if (bonusRow) {
                if (data.bonus_total && data.bonus_total > 0) {
                    bonusRow.style.display = '';
                    var bonusEl = document.getElementById('slip-bonus-total');
                    if (bonusEl) bonusEl.textContent = '+' + formatRupiah(data.bonus_total);
                } else {
                    bonusRow.style.display = 'none';
                }
            }

            var dedRow = document.getElementById('slip-deduction-row');
            if (dedRow) {
                var dedTotal = data.deduction_total || 0;
                var alphaDays = (data.scheme_snapshot && data.scheme_snapshot.alpha_days) ? data.scheme_snapshot.alpha_days : 0;
                var alphaNominal = (data.scheme_snapshot && data.scheme_snapshot.alpha_penalty_nominal) ? data.scheme_snapshot.alpha_penalty_nominal : 0;

                if (dedTotal > 0) {
                    dedRow.style.display = '';
                    var dedEl = document.getElementById('slip-deduction-total');
                    var dedTitle = document.getElementById('slip-deduction-title');
                    var dedNote = document.getElementById('slip-deduction-note');
                    if (dedEl) dedEl.textContent = '-' + formatRupiah(dedTotal);
                    if (alphaDays > 0) {
                        if (dedTitle) dedTitle.textContent = 'Potongan Alpha (' + alphaDays + ' Hari)';
                        if (dedNote) dedNote.textContent = alphaDays + ' hari alpha @ ' + formatRupiah(alphaNominal) + ' tanpa izin disetujui';
                    } else {
                        if (dedTitle) dedTitle.textContent = 'Potongan Lainnya';
                        if (dedNote) dedNote.textContent = 'Potongan operasional / denda';
                    }
                } else {
                    dedRow.style.display = 'none';
                }
            }

            var netTotalEl = document.getElementById('slip-net-total');
            if (netTotalEl) netTotalEl.textContent = formatRupiah(data.net_total);

            var netDedHint = document.getElementById('slip-net-deduction-hint');
            if (netDedHint) {
                var ded = data.deduction_total || 0;
                if (ded > 0) {
                    netDedHint.textContent = '-' + formatRupiah(ded) + ' karena alpha';
                    netDedHint.classList.remove('hidden');
                } else {
                    netDedHint.classList.add('hidden');
                }
            }
            
            var staffSig = document.getElementById('slip-employee-signature-name');
            if (staffSig) {
                staffSig.textContent = '( ' + (data.name || 'Karyawan') + ' )';
            }
            
            var badge = document.getElementById('slip-status-badge');
            if (badge) {
                if (data.status === 'paid') {
                    badge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800';
                    badge.textContent = 'LUNAS' + (data.paid_at ? ' (' + data.paid_at + ')' : '');
                } else {
                    badge.className = 'px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800';
                    badge.textContent = 'PENDING';
                }
            }

            var slipModal = document.getElementById('modal-slip-gaji');
            if (slipModal) slipModal.showModal();
        }

        function printSlip() {
            var originalTitle = document.title;
            var empName = document.getElementById('slip-employee-name')?.textContent?.trim() || '';
            document.title = empName ? 'Slip Gaji - ' + empName : 'Slip Gaji';
            window.print();
            setTimeout(function() {
                document.title = originalTitle;
            }, 1000);
        }

        function openConfirmModal(config) {
            var form = document.getElementById('confirm-modal-form');
            var titleEl = document.getElementById('confirm-modal-title');
            var messageEl = document.getElementById('confirm-modal-message');
            var submitBtn = document.getElementById('confirm-modal-submit-btn');
            var iconBg = document.getElementById('confirm-modal-icon-bg');

            if (!form || !titleEl || !messageEl || !submitBtn || !iconBg) return;

            form.action = config.actionUrl;
            titleEl.textContent = config.title || 'Konfirmasi Tindakan';
            messageEl.textContent = config.message || 'Apakah Anda yakin ingin memproses aksi ini?';
            submitBtn.textContent = config.submitText || 'Ya, Lanjutkan';

            if (config.variant === 'zinc') {
                iconBg.className = 'w-11 h-11 rounded-2xl mx-auto flex items-center justify-center mb-3 bg-amber-500 text-zinc-950';
                submitBtn.className = 'flex-1 px-4 py-2 rounded-xl text-xs font-black shadow-sm transition cursor-pointer bg-amber-500 hover:bg-amber-400 text-zinc-950';
            } else {
                iconBg.className = 'w-11 h-11 rounded-2xl mx-auto flex items-center justify-center mb-3 bg-emerald-50 text-emerald-600 border border-emerald-200/60';
                submitBtn.className = 'flex-1 px-4 py-2 rounded-xl text-xs font-black shadow-sm transition cursor-pointer bg-emerald-600 hover:bg-emerald-500 text-white';
            }

            document.getElementById('modal-confirm-action').showModal();
        }

        function openGeneratePayrollModal() {
            var modal = document.getElementById('modal-generate-payroll');
            if (modal) {
                var branchInput = modal.querySelector('[data-ui-select][data-input-name="branch_id"] input[data-select-input]');
                var currentBranch = branchInput ? branchInput.value : '';
                syncPayrollStaffDropdown(currentBranch);

                var startInput = modal.querySelector('input[name="period_start"]');
                var endInput = modal.querySelector('input[name="period_end"]');

                // Cocokkan tombol preset yang sesuai dengan input tanggal saat ini
                document.querySelectorAll('.preset-choice-btn').forEach(function(btn) {
                    btn.classList.remove('ring-2', 'ring-amber-500', 'bg-amber-100/90', 'border-amber-400', 'text-amber-950');
                    if (startInput && endInput && btn.dataset.start === startInput.value && btn.dataset.end === endInput.value) {
                        btn.classList.add('ring-2', 'ring-amber-500', 'bg-amber-100/90', 'border-amber-400', 'text-amber-950');
                    }
                });

                updatePayrollInfoBox();
                modal.showModal();
            }
        }

        function setPayrollPreset(preset) {
            var startInput = document.querySelector('#modal-generate-payroll input[name="period_start"]');
            var endInput = document.querySelector('#modal-generate-payroll input[name="period_end"]');
            if (!startInput || !endInput) return;

            var activeBtn = document.getElementById('preset-btn-' + preset);
            if (activeBtn && activeBtn.dataset.start && activeBtn.dataset.end) {
                startInput.value = activeBtn.dataset.start;
                endInput.value = activeBtn.dataset.end;
            }

            // Visual active indicator for presets
            document.querySelectorAll('.preset-choice-btn').forEach(function(btn) {
                btn.classList.remove('ring-2', 'ring-amber-500', 'bg-amber-100/90', 'border-amber-400', 'text-amber-950');
            });
            if (activeBtn) {
                activeBtn.classList.add('ring-2', 'ring-amber-500', 'bg-amber-100/90', 'border-amber-400', 'text-amber-950');
            }

            updatePayrollInfoBox();
        }

        function updatePayrollInfoBox() {
            var endInput = document.querySelector('#modal-generate-payroll input[name="period_end"]');
            var infoWeekly = document.getElementById('payroll-info-weekly');
            var infoEndOfMonth = document.getElementById('payroll-info-endofmonth');
            var infoBox = document.getElementById('payroll-period-info-box');
            if (!endInput || !infoWeekly || !infoEndOfMonth || !infoBox) return;

            var endVal = endInput.value;
            if (!endVal) return;

            var parts = endVal.split('-');
            if (parts.length < 3) return;
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1;
            var day = parseInt(parts[2], 10);
            var lastDay = new Date(year, month + 1, 0).getDate();

            if (day >= lastDay) {
                infoWeekly.classList.add('hidden');
                infoEndOfMonth.classList.remove('hidden');
                infoBox.className = 'p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-xs transition-all';
            } else {
                infoWeekly.classList.remove('hidden');
                infoEndOfMonth.classList.add('hidden');
                infoBox.className = 'p-3.5 rounded-2xl bg-amber-50 border border-amber-200/80 text-xs transition-all';
            }
        }

        function syncPayrollStaffDropdown(selectedBranchId) {
            var staffSelectRoot = document.querySelector('#modal-generate-payroll [data-ui-select][data-input-name="membership_id"]');
            if (!staffSelectRoot) return;

            var optionBtns = staffSelectRoot.querySelectorAll('[data-select-option]');
            var currentInput = staffSelectRoot.querySelector('input[data-select-input]');
            var currentVal = currentInput ? currentInput.value : '';
            var shouldReset = false;

            optionBtns.forEach(function(btn) {
                var val = btn.getAttribute('data-value') || '';
                var branchId = btn.getAttribute('data-branch-id') || '';

                if (val === '') {
                    btn.classList.remove('hidden');
                    btn.removeAttribute('data-branch-hidden');
                    return;
                }

                if (!selectedBranchId || selectedBranchId === '') {
                    btn.classList.remove('hidden');
                    btn.removeAttribute('data-branch-hidden');
                } else {
                    if (branchId === String(selectedBranchId)) {
                        btn.classList.remove('hidden');
                        btn.removeAttribute('data-branch-hidden');
                    } else {
                        btn.classList.add('hidden');
                        btn.setAttribute('data-branch-hidden', 'true');
                        if (currentVal === val) {
                            shouldReset = true;
                        }
                    }
                }
            });

            if (shouldReset) {
                var defaultOpt = staffSelectRoot.querySelector('[data-select-option][data-value=""]');
                if (defaultOpt) {
                    defaultOpt.click();
                }
            }
        }

        // Event listener saat cabang berubah di modal generate payroll
        document.addEventListener('ui-select:change', function(e) {
            var selectRoot = e.target.closest('#modal-generate-payroll [data-ui-select][data-input-name="branch_id"]');
            if (selectRoot) {
                var selectedBranch = e.detail?.value || '';
                syncPayrollStaffDropdown(selectedBranch);
            }

            // Ganti periode payroll secara instan melalui dropdown custom
            var switcher = e.target.closest('#payroll-period-switcher-wrapper [data-ui-select][data-input-name="period_switcher_id"]');
            if (switcher && e.detail?.value) {
                var url = new URL(window.location.href);
                url.searchParams.set('payroll_id', e.detail.value);
                window.location.href = url.toString();
            }
        });

        document.addEventListener('click', function(e) {
            var branchOpt = e.target.closest('#modal-generate-payroll [data-ui-select][data-input-name="branch_id"] [data-select-option]');
            if (branchOpt) {
                var selectedBranch = branchOpt.getAttribute('data-value') || '';
                syncPayrollStaffDropdown(selectedBranch);
            }
        });
    </script>

    <style>
        @media print {
            @page {
                margin: 0;
                size: auto;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            body * {
                visibility: hidden !important;
            }
            #modal-slip-gaji,
            #modal-slip-gaji * {
                visibility: visible !important;
            }
            #modal-slip-gaji {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                z-index: 999999 !important;
            }
            #slip-print-content {
                padding: 16mm 14mm !important;
                background: white !important;
                color: black !important;
                max-height: none !important;
                overflow: visible !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</x-layouts.app>
