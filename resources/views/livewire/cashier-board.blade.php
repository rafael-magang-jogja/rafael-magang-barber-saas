@php
$columns = [
    'waiting' => [
        'title' => 'Menunggu',
        'short' => 'Menunggu',
        'dot' => 'bg-zinc-500',
        'header_bg' => 'bg-zinc-900 text-white',
        'col_bg' => 'bg-zinc-200/55 border-zinc-300/70',
    ],
    'called' => [
        'title' => 'Dipanggil',
        'short' => 'Dipanggil',
        'dot' => 'bg-sky-500',
        'header_bg' => 'bg-sky-600 text-white',
        'col_bg' => 'bg-sky-50/80 border-sky-200/80',
    ],
    'in_service' => [
        'title' => 'Sedang Dicukur',
        'short' => 'Dicukur',
        'dot' => 'bg-amber-500',
        'header_bg' => 'bg-amber-500 text-zinc-950',
        'col_bg' => 'bg-amber-50/80 border-amber-200/80',
    ],
    'completed' => [
        'title' => 'Selesai / Bayar',
        'short' => 'Bayar',
        'dot' => 'bg-emerald-500',
        'header_bg' => 'bg-emerald-600 text-white',
        'col_bg' => 'bg-emerald-50/80 border-emerald-200/80',
    ],
];

$branchOptions = isset($branches) ? $branches->map(fn ($b) => [
    'value' => (string) $b->id,
    'label' => $b->name,
    'sublabel' => $b->code,
])->all() : [];

$barberOptions = isset($barbers) ? $barbers->map(fn ($b) => [
    'value' => (string) $b->id,
    'label' => $b->display_name ?? $b->user->name,
    'sublabel' => ($b->branch?->name ?? 'Cabang Utama') . ' · ' . ($b->specialty ?? 'Barber'),
])->all() : [];

$cashierDateOptions = [
    ['value' => 'today', 'label' => 'Hari Ini (' . now()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean hari ini'],
    ['value' => 'yesterday', 'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean kemarin'],
    ['value' => 'custom', 'label' => 'Pilih Tanggal...', 'sublabel' => 'Cek tanggal tertentu'],
];

$isCustomDate = ($currentDateFilter ?? 'today') === 'custom';

$filteredByColumn = [];
$totalFilteredTickets = 0;
foreach ($columns as $statusKey => $colMeta) {
    $colTickets = $tickets->get($statusKey, collect())
        ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
        ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId));

    if ($statusKey === 'completed') {
        // Selesai: urut dari yang TERBARU selesai (Newest first)
        $colTickets = $colTickets->sortByDesc(function ($t) {
            return $t->completed_at?->timestamp ?? $t->updated_at?->timestamp ?? $t->sequence;
        })->values();
    } elseif ($statusKey === 'waiting') {
        // Menunggu: urut berdasarkan prioritas jadwal/kedatangan (Priority first) agar adil dan kronologis
        $colTickets = $colTickets->sortBy(function ($t) {
            return $t->priority_at?->timestamp ?? $t->created_at?->timestamp ?? $t->sequence;
        })->values();
    } else {
        // Yang lain (called, in_service) dari yang TERLAMA terlebih dahulu (Oldest first)
        $colTickets = $colTickets->sortBy(function ($t) use ($statusKey) {
            if ($statusKey === 'called') {
                return $t->called_at?->timestamp ?? $t->sequence;
            }
            if ($statusKey === 'in_service') {
                return $t->started_at?->timestamp ?? $t->sequence;
            }
            return $t->created_at?->timestamp ?? $t->sequence;
        })->values();
    }

    $filteredByColumn[$statusKey] = $colTickets;
    $totalFilteredTickets += $colTickets->count();
}

$visibleColumns = ($filterStatus && isset($columns[$filterStatus]))
    ? [$filterStatus => $columns[$filterStatus]]
    : $columns;
@endphp

<div wire:poll.5s class="w-full">
    @if(isset($selectedDate) && $selectedDate !== now()->toDateString())
    <div class="px-3.5 py-2.5 rounded-2xl bg-amber-50/90 border border-amber-200/80 text-amber-950 text-xs font-semibold flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 shadow-2xs">
        <div class="flex items-center gap-2 min-w-0">
            <x-ui.icon name="info" class="w-4 h-4 text-amber-600 shrink-0" />
            <span class="truncate">Menampilkan data antrean: <strong class="font-extrabold text-amber-900">{{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}</strong></span>
        </div>
        <a href="{{ route('cashier.dashboard', array_filter(['branch_id' => $filterBranchId, 'barber_id' => $filterBarberId])) }}" class="text-[11px] font-extrabold text-amber-800 hover:text-amber-950 underline shrink-0 self-start sm:self-auto">Kembali ke Hari Ini</a>
    </div>
    @endif

    <style>
        @media (min-width: 768px) and (max-width: 1023.98px) {
            html.tablet-sidebar-open .cashier-pos-header {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 1rem !important;
            }
            html.tablet-sidebar-open .cashier-pos-header .cashier-pos-title-block {
                width: 100% !important;
            }
            html.tablet-sidebar-open .cashier-pos-header .cashier-pos-cta-btn {
                width: 100% !important;
                justify-content: center !important;
            }
        }
    </style>

    <!-- 1. HEADER POS & CALL-TO-ACTION WALK-IN (BERSIH & MEWAH DI SEMUA MODE) -->
    <div class="cashier-pos-header p-4 sm:p-5 rounded-3xl bg-white border border-zinc-200/90 shadow-2xs mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 transition-all duration-300">
        <div class="cashier-pos-title-block flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-amber-500/15 text-amber-700 flex items-center justify-center shrink-0">
                <x-ui.icon name="ticket" class="w-5 h-5 sm:w-5.5 sm:h-5.5" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-base sm:text-lg font-black text-zinc-950 tracking-tight whitespace-nowrap">
                        Papan Antrean &amp; POS
                    </h2>
                </div>
                <p class="text-xs text-zinc-500 mt-0.5 leading-relaxed">
                    Monitor antrean live dan layani pembayaran kasir di outlet (otomatis disegarkan tiap 5 detik)
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
            @php
                $futureBookingsFiltered = collect($futureBookings ?? [])
                    ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
                    ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId));
                $futureCount = $futureBookingsFiltered->count();
            @endphp

            @if($futureCount > 0)
                <button
                    type="button"
                    onclick="document.getElementById('modal-future-bookings').showModal()"
                    class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2.5 sm:py-3 rounded-2xl bg-sky-50 hover:bg-sky-100/90 active:scale-95 border border-sky-200 text-sky-800 font-extrabold text-xs transition cursor-pointer select-none shrink-0"
                    title="Lihat reservasi jadwal esok dan seterusnya"
                >
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-sky-600" />
                    <span>{{ $futureCount }} Jadwal Mendatang</span>
                </button>
            @endif

            <!-- Tombol Input Antrean Walk-in (Didesain Rapi & Menonjol) -->
            <a
                href="{{ route('cashier.walk-in') }}"
                class="cashier-pos-cta-btn group inline-flex items-center justify-center gap-2.5 px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs sm:text-sm shadow-md shadow-zinc-950/10 transition cursor-pointer select-none shrink-0 flex-1 sm:flex-initial"
                title="Daftarkan antrean pelanggan walk-in yang datang langsung ke outlet"
            >
                <span class="w-6 h-6 rounded-xl bg-zinc-950/15 group-hover:bg-zinc-950/20 text-zinc-950 flex items-center justify-center shrink-0 transition">
                    <x-ui.icon name="plus" class="w-3.5 h-3.5 stroke-[3]" />
                </span>
                <span class="whitespace-nowrap">Input Antrean Walk-in</span>
            </a>
        </div>
    </div>

    <!-- 2. TOOLBAR FILTER & TAB STATUS (SIMETRIS & RAPI DI DESKTOP, TABLET, MOBILE) -->
    <div class="bg-white rounded-2xl border border-zinc-200/80 p-3 sm:p-3.5 shadow-xs mb-4 space-y-3">
        <!-- Form Filter Dropdown 3 Kolom Seimbang (Di-ignore dari morphing agar tidak mereset dropdown aktif) -->
        <div wire:ignore>
            <form method="GET" action="{{ route('cashier.dashboard') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                @if($filterStatus)
                <input type="hidden" name="status" value="{{ $filterStatus }}" />
                @endif

                <div>
                    @if(isset($branches) && $branches->count() === 1)
                        <div class="h-9 px-3 rounded-xl bg-zinc-100/90 border border-zinc-200/90 flex items-center justify-between text-xs font-bold text-zinc-800 shadow-2xs select-none" title="Cabang penugasan kasir">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-ui.icon name="store" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                <span class="truncate">{{ $branches->first()->name }}</span>
                            </div>
                            <span class="px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-800 text-[10px] font-black shrink-0 uppercase tracking-wider">
                                {{ $branches->first()->code }}
                            </span>
                        </div>
                        <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}" />
                    @else
                        <x-ui.select
                            name="branch_id"
                            size="sm"
                            placeholder="Semua Cabang"
                            icon="store"
                            :options="$branchOptions"
                            :value="$filterBranchId"
                            search-placeholder="Cari cabang..."
                            class="w-full"
                            auto-submit />
                    @endif
                </div>

                <div>
                    <x-ui.select
                        name="barber_id"
                        size="sm"
                        placeholder="Semua Barber"
                        icon="scissors"
                        :options="$barberOptions"
                        :value="$filterBarberId"
                        search-placeholder="Cari nama barber..."
                        class="w-full"
                        auto-submit />
                </div>

                <div>
                    <x-ui.select
                        name="date_filter"
                        size="sm"
                        icon="calendar"
                        :options="$cashierDateOptions"
                        :value="$currentDateFilter"
                        class="w-full"
                        auto-submit />
                </div>

                @if($isCustomDate)
                <!-- Baris Tanggal Kustom (Full Kanan-Kiri & Tombol Reset Solid di Samping) -->
                <div class="col-span-1 sm:col-span-3 pt-2.5 border-t border-zinc-100 flex items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span class="text-xs font-bold text-zinc-500 shrink-0 hidden sm:inline">Pilih Tanggal:</span>
                        <input
                            type="date"
                            name="custom_date"
                            value="{{ $customDate ?? $selectedDate ?? now()->toDateString() }}"
                            onchange="this.form.submit()"
                            class="w-full sm:max-w-xs h-9 px-3 rounded-xl border border-zinc-200 text-xs font-semibold text-zinc-800 bg-white shadow-2xs focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 transition" />
                    </div>
                    @if($filterBranchId || $filterBarberId || $filterStatus || ($currentDateFilter !== 'today'))
                    <a
                        href="{{ route('cashier.dashboard') }}"
                        class="inline-flex items-center justify-center gap-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 active:scale-95 px-3.5 py-2 rounded-xl shadow-2xs transition shrink-0 cursor-pointer"
                        title="Reset semua filter">
                        <x-ui.icon name="x" class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span>Reset</span>
                    </a>
                    @endif
                </div>
                @elseif($filterBranchId || $filterBarberId || $filterStatus || ($currentDateFilter !== 'today'))
                <!-- Baris Reset Filter Jika Sedang Ada Filter Aktif -->
                <div class="col-span-1 sm:col-span-3 pt-2 border-t border-zinc-100 flex items-center justify-end">
                    <a
                        href="{{ route('cashier.dashboard') }}"
                        class="inline-flex items-center justify-center gap-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 active:scale-95 px-3.5 py-1.5 rounded-xl shadow-2xs transition shrink-0 cursor-pointer"
                        title="Reset semua filter">
                        <x-ui.icon name="x" class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span>Reset Filter</span>
                    </a>
                </div>
                @endif
            </form>
        </div>

        <!-- Filter Tab Status (Pola Baris 1 - 2 - 2 di Mobile/Tablet, 1 Baris di Desktop) -->
        @php
        $baseQueryParams = array_filter([
            'branch_id' => $filterBranchId,
            'barber_id' => $filterBarberId,
            'date_filter' => $currentDateFilter !== 'today' ? $currentDateFilter : null,
            'custom_date' => $currentDateFilter === 'custom' ? $customDate : null,
        ]);
        $allStatusUrl = route('cashier.dashboard', $baseQueryParams);
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-1.5 sm:gap-2">
            <!-- Baris 1: Semua (1 Item Full Width di Mobile/Tablet) -->
            <a
                href="{{ $allStatusUrl }}"
                class="col-span-2 lg:col-span-1 flex items-center justify-between gap-2 py-2 px-3 rounded-xl text-xs font-bold transition select-none {{ ! $filterStatus ? 'bg-zinc-950 text-amber-400 shadow-sm ring-1 ring-zinc-800' : 'bg-zinc-100 hover:bg-zinc-200/80 text-zinc-600 hover:text-zinc-950' }}">
                <span>Semua</span>
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-md text-[11px] font-black num shrink-0 {{ ! $filterStatus ? 'bg-amber-400 text-zinc-950' : 'bg-white text-zinc-800 shadow-2xs' }}">{{ $totalFilteredTickets }}</span>
            </a>

            <!-- Baris 2 & 3: Masing-masing 2 Item (50% - 50% di Mobile/Tablet) -->
            @foreach($columns as $statusKey => $col)
            @php
            $cnt = $filteredByColumn[$statusKey]->count();
            $isStatusActive = $filterStatus === $statusKey;
            $statusUrl = route('cashier.dashboard', array_merge($baseQueryParams, ['status' => $statusKey]));
            @endphp
            <a
                wire:key="status-filter-tab-{{ $statusKey }}"
                href="{{ $statusUrl }}"
                class="col-span-1 flex items-center justify-between gap-2 py-2 px-3 rounded-xl text-xs font-bold transition select-none {{ $isStatusActive ? 'bg-zinc-950 text-amber-400 shadow-sm ring-1 ring-zinc-800' : 'bg-zinc-100 hover:bg-zinc-200/80 text-zinc-600 hover:text-zinc-950' }}">
                <span class="flex items-center gap-1.5 min-w-0">
                    <span class="w-2 h-2 rounded-full {{ $col['dot'] }} shrink-0"></span>
                    <span class="truncate">{{ $col['short'] }}</span>
                </span>
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-md text-[11px] font-black num shrink-0 {{ $isStatusActive ? 'bg-amber-400 text-zinc-950' : 'bg-white text-zinc-800 shadow-2xs' }}">{{ $cnt }}</span>
            </a>
            @endforeach
        </div>
    </div>

    <!-- Monitor Kursi Barber (Hanya menampilkan barber yang sudah absen dan belum pulang) -->
    @php
    $allTodayTickets = $tickets->flatten(1);
    $filteredBarbers = ($presentBarbers ?? $barbers)
        ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
        ->when($filterBarberId, fn ($c) => $c->where('id', $filterBarberId));
    @endphp

    @if($filteredBarbers->isNotEmpty())
    <div class="bg-white rounded-2xl border border-zinc-200/80 p-3 sm:p-3.5 shadow-xs mb-4 space-y-2.5">
        <div class="flex items-center justify-between gap-2 px-0.5">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-zinc-900 text-amber-400 flex items-center justify-center shrink-0 shadow-2xs">
                    <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                </div>
                <h3 class="text-xs sm:text-sm font-extrabold text-zinc-900">Monitor Kursi Barber</h3>
                <span class="text-[10px] font-bold text-zinc-400 hidden sm:inline">· Pantau kesiapan &amp; aktivitas live</span>
            </div>
            <div class="flex items-center gap-2 text-[11px] font-bold text-zinc-500">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-700 text-[10px] font-black">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    {{ $filteredBarbers->count() }} Barber
                </span>
            </div>
        </div>

        <div class="cashier-chairs-compact-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-5 xl:flex xl:flex-wrap xl:items-stretch gap-2 sm:gap-2.5 transition-all duration-500 ease-in-out w-full" data-tablet-flip>
            @foreach($filteredBarbers as $barber)
            @php
            $barberTickets = $allTodayTickets->where('barber_membership_id', $barber->id);
            $inServiceTicket = $barberTickets->firstWhere('status', \App\Enums\QueueStatus::InService);
            $calledTicket = $barberTickets->firstWhere('status', \App\Enums\QueueStatus::Called);
            $waitingCount = $barberTickets->where('status', \App\Enums\QueueStatus::Waiting)->count();

            if ($inServiceTicket !== null) {
                $statusKey = 'in_service';
                $statusTitle = 'Memproses';
                $statusDetail = '#' . $inServiceTicket->number;
                $customerHint = $inServiceTicket->customerProfile?->name ?? 'Walk-in';
                $dotClass = 'bg-amber-500 animate-pulse';
                $badgeClass = 'text-amber-800 bg-amber-50 border border-amber-200/80';
                $cardBorder = 'border-amber-200/80 hover:border-amber-300';
            } elseif ($calledTicket !== null) {
                $statusKey = 'called';
                $statusTitle = 'Memanggil';
                $statusDetail = '#' . $calledTicket->number;
                $customerHint = $calledTicket->customerProfile?->name ?? 'Walk-in';
                $dotClass = 'bg-sky-500 animate-pulse';
                $badgeClass = 'text-sky-700 bg-sky-50 border border-sky-200/80';
                $cardBorder = 'border-sky-200/80 hover:border-sky-300';
            } elseif ($barber->work_status === \App\Enums\WorkStatus::Break || $barber->work_status?->value === 'break') {
                $statusKey = 'break';
                $statusTitle = 'Istirahat';
                $statusDetail = 'Break';
                $customerHint = null;
                $dotClass = 'bg-zinc-400';
                $badgeClass = 'text-zinc-600 bg-zinc-100 border border-zinc-200';
                $cardBorder = 'border-zinc-200/70 opacity-80';
            } elseif ($barber->work_status === \App\Enums\WorkStatus::Off || $barber->work_status?->value === 'off') {
                $statusKey = 'off';
                $statusTitle = 'Off';
                $statusDetail = 'Selesai Shift';
                $customerHint = null;
                $dotClass = 'bg-rose-400';
                $badgeClass = 'text-rose-700 bg-rose-50 border border-rose-200';
                $cardBorder = 'border-zinc-200/70 opacity-60';
            } else {
                $statusKey = 'ready';
                $statusTitle = 'Ready';
                $statusDetail = $waitingCount > 0 ? $waitingCount . ' antrean' : 'Tersedia';
                $customerHint = null;
                $dotClass = 'bg-emerald-500';
                $badgeClass = 'text-emerald-700 bg-emerald-50 border border-emerald-200/80';
                $cardBorder = 'border-zinc-200/80 hover:border-zinc-300';
            }

            $initial = strtoupper(substr($barber->display_name ?? $barber->user->name ?? 'B', 0, 1));
            $displayName = $barber->display_name ?? $barber->user->name;
            $branchCode = $barber->branch?->code ?? $barber->branch?->name ?? 'Outlet';
            @endphp
            <div wire:key="chair-barber-{{ $barber->id }}" class="rounded-xl border {{ $cardBorder }} bg-white p-2.5 shadow-2xs transition flex flex-col justify-between gap-2 flex-1 min-w-[150px] sm:min-w-[170px] xl:min-w-[190px]">
                <!-- Header Barber -->
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-zinc-900 text-amber-400 font-black text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        {{ $initial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-black text-zinc-900 truncate leading-tight" title="{{ $displayName }}">
                            {{ $displayName }}
                        </p>
                        <p class="text-[10px] font-semibold text-zinc-400 truncate leading-tight">
                            {{ $branchCode }}
                        </p>
                    </div>
                </div>

                <!-- Status Live Badge & Detail -->
                <div class="pt-1.5 border-t border-zinc-100 flex items-center justify-between gap-1.5">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-black {{ $badgeClass }} shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }} shrink-0"></span>
                        <span>{{ $statusTitle }}</span>
                    </span>
                    <span class="text-[10px] font-bold text-zinc-600 truncate num text-right" title="{{ $customerHint ? $statusDetail . ' (' . $customerHint . ')' : $statusDetail }}">
                        {{ $statusDetail }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @php
    $upcomingTodayList = collect($upcomingTodayBookings ?? [])
        ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
        ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId));
    @endphp

    @if($upcomingTodayList->isNotEmpty())
    <!-- PITA RINGKAS RESERVASI HARI INI (HEMAT RUANG & SANGAT RAPI) -->
    <div x-data="{ expanded: false }" class="mb-4 rounded-2xl bg-amber-50/80 border border-amber-200/90 p-2.5 sm:px-4 sm:py-3 shadow-2xs transition-all duration-200">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-7 h-7 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 shadow-2xs">
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 stroke-[2.5]" />
                </span>
                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                    <span class="text-xs font-black text-amber-950">
                        {{ $upcomingTodayList->count() }} Booking Hari Ini Menunggu Jam Tiba
                    </span>
                    @php $firstTodayBooking = $upcomingTodayList->first(); @endphp
                    @if($firstTodayBooking)
                    <span class="text-[11px] font-bold text-amber-900 bg-white/90 border border-amber-300/80 px-2 py-0.5 rounded-lg shadow-2xs inline-flex items-center gap-1.5 truncate">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Terdekat: <strong>{{ $firstTodayBooking->scheduled_at->format('H:i') }}</strong> ({{ $firstTodayBooking->customerProfile?->name ?? 'Pelanggan' }} &middot; {{ $firstTodayBooking->barber?->display_name ?? 'Barber' }})</span>
                    </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="expanded = !expanded"
                    class="text-xs font-black text-amber-900 hover:text-amber-950 px-2.5 py-1 rounded-xl bg-amber-200/60 hover:bg-amber-200/90 border border-amber-300/70 transition flex items-center gap-1.5 cursor-pointer select-none"
                >
                    <span x-text="expanded ? 'Tutup' : 'Lihat Detail (' + {{ $upcomingTodayList->count() }} + ')'"></span>
                    <x-ui.icon name="chevron-down" class="w-3 h-3 transition-transform duration-200" ::class="expanded ? 'rotate-180' : ''" />
                </button>
            </div>
        </div>

        <!-- Detail Drawer / Grid (Terbuka saat diklik) -->
        <div x-show="expanded" x-collapse class="mt-3 pt-3 border-t border-amber-200/70 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
            @foreach($upcomingTodayList as $ub)
            <div class="p-3 rounded-xl bg-white border border-amber-200 shadow-2xs flex flex-col justify-between space-y-2">
                <div class="space-y-1">
                    <div class="flex items-center justify-between gap-1.5">
                        <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-black text-[11px] num">
                            {{ $ub->scheduled_at->format('H:i') }} WIB
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-200">
                            {{ $ub->code }}
                        </span>
                    </div>
                    <div class="font-extrabold text-xs text-zinc-900 truncate">
                        {{ $ub->customerProfile?->name ?? 'Pelanggan' }}
                    </div>
                    <div class="text-[11px] text-zinc-500 truncate flex items-center gap-1">
                        <x-ui.icon name="scissors" class="w-3 h-3 text-zinc-400 shrink-0" />
                        <span>{{ $ub->barber?->display_name ?? 'Barber Tersedia' }} &middot; {{ $ub->items->pluck('service_name')->join(', ') }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('cashier.bookings.check-in', $ub) }}" onsubmit="return confirm('Check-in reservasi ini sekarang ke antrean live?')">
                    @csrf
                    <button
                        type="submit"
                        class="w-full py-1.5 px-3 rounded-lg bg-zinc-950 hover:bg-zinc-800 active:scale-[0.99] text-amber-400 hover:text-amber-300 font-extrabold text-[11px] flex items-center justify-center gap-1.5 transition shadow-2xs cursor-pointer"
                        title="Klik jika pelanggan sudah tiba lebih awal"
                    >
                        <x-ui.icon name="check" class="w-3.5 h-3.5 text-amber-400" />
                        <span>Check-in Lebih Awal</span>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @php
    $cancelledTickets = $tickets->get('cancelled', collect())
        ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
        ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId));
    $pendingRefundTickets = $cancelledTickets->filter(fn ($t) => $t->refund_status === 'pending');

    $pendingRefundBookingsList = collect($pendingRefundBookings ?? [])
        ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
        ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId))
        ->whereNotIn('id', $pendingRefundTickets->pluck('booking_id')->filter());

    $totalRefundsCount = $pendingRefundTickets->count() + $pendingRefundBookingsList->count();
    @endphp

    @if($totalRefundsCount > 0)
    <!-- NOTIFIKASI / KARTU PERMINTAAN REFUND DANA PEMBATALAN (ANTREAN & BOOKING) -->
    <div class="bg-amber-500/10 border-2 border-amber-500/40 rounded-2xl p-4 shadow-sm mb-4 space-y-3 animate-in fade-in duration-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-500/20 pb-2.5">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 shadow-2xs">
                    <x-ui.icon name="wallet" class="w-4 h-4 stroke-[2.5]" />
                </span>
                <div>
                    <h3 class="text-sm font-black text-amber-950 flex items-center gap-2">
                        <span>Permintaan Pengembalian Dana (Refund)</span>
                        <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] font-black num">
                            {{ $totalRefundsCount }} Menunggu
                        </span>
                    </h3>
                    <p class="text-xs text-amber-900/80 mt-0.5">
                        Pelanggan membatalkan pesanan yang telah dibayar. Harap segera transfer dana refund sesuai rekening tujuan di bawah ini.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($pendingRefundTickets as $refundTicket)
            <div wire:key="refund-ticket-card-{{ $refundTicket->id }}" class="bg-white rounded-xl p-3.5 border border-amber-300 shadow-2xs space-y-2.5 flex flex-col justify-between">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="px-2 py-0.5 rounded-lg bg-zinc-950 text-amber-400 font-black text-xs num">
                                {{ $refundTicket->number }}
                            </span>
                            <span class="font-extrabold text-xs text-zinc-900 truncate">
                                {{ $refundTicket->customerProfile?->name ?? 'Pelanggan Walk-in' }}
                            </span>
                        </div>
                        <span class="font-black text-sm text-rose-600 num shrink-0">
                            Rp{{ number_format($refundTicket->refund_amount, 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-200/80 text-[11px] space-y-1">
                        <div class="flex justify-between text-zinc-600">
                            <span>Bank / E-Wallet:</span>
                            <span class="font-bold text-zinc-900">{{ $refundTicket->refund_bank_name ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600">
                            <span>Nomor Rekening:</span>
                            <span class="font-black text-zinc-900 num font-mono">{{ $refundTicket->refund_account_number ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600">
                            <span>Atas Nama:</span>
                            <span class="font-bold text-zinc-900">{{ $refundTicket->refund_account_name ?: '-' }}</span>
                        </div>
                        @if($refundTicket->notes)
                        <div class="pt-1 border-t border-zinc-200/60 text-zinc-500 italic">
                            {{ $refundTicket->notes }}
                        </div>
                        @endif
                    </div>
                </div>

                <button
                    type="button"
                    onclick="openRefundTicketModal('{{ route('cashier.tickets.refund', $refundTicket) }}', '{{ $refundTicket->number }}', '{{ addslashes($refundTicket->customerProfile?->name ?? 'Pelanggan') }}', 'Rp{{ number_format($refundTicket->refund_amount, 0, ',', '.') }}', '{{ addslashes($refundTicket->refund_bank_name) }}', '{{ addslashes($refundTicket->refund_account_number) }}', '{{ addslashes($refundTicket->refund_account_name) }}')"
                    class="w-full min-h-[36px] px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white text-xs font-black inline-flex items-center justify-center gap-1.5 shadow-xs transition cursor-pointer"
                >
                    <x-ui.icon name="check" class="w-4 h-4" />
                    <span>Konfirmasi Telah Ditransfer (Selesaikan Refund)</span>
                </button>
            </div>
            @endforeach

            @foreach($pendingRefundBookingsList as $refundBooking)
            <div wire:key="refund-booking-card-{{ $refundBooking->id }}" class="bg-white rounded-xl p-3.5 border border-amber-300 shadow-2xs space-y-2.5 flex flex-col justify-between">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="px-2 py-0.5 rounded-lg bg-zinc-950 text-amber-400 font-black text-xs num">
                                {{ $refundBooking->code }}
                            </span>
                            <span class="font-extrabold text-xs text-zinc-900 truncate">
                                {{ $refundBooking->customerProfile?->name ?? 'Pelanggan Booking' }}
                            </span>
                        </div>
                        <span class="font-black text-sm text-rose-600 num shrink-0">
                            Rp{{ number_format($refundBooking->refund_amount, 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-200/80 text-[11px] space-y-1">
                        <div class="flex justify-between text-zinc-600">
                            <span>Bank / E-Wallet:</span>
                            <span class="font-bold text-zinc-900">{{ $refundBooking->refund_bank_name ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600">
                            <span>Nomor Rekening:</span>
                            <span class="font-black text-zinc-900 num font-mono">{{ $refundBooking->refund_account_number ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600">
                            <span>Atas Nama:</span>
                            <span class="font-bold text-zinc-900">{{ $refundBooking->refund_account_name ?: '-' }}</span>
                        </div>
                        @if($refundBooking->notes)
                        <div class="pt-1 border-t border-zinc-200/60 text-zinc-500 italic">
                            {{ $refundBooking->notes }}
                        </div>
                        @endif
                    </div>
                </div>

                <button
                    type="button"
                    onclick="openRefundTicketModal('{{ route('cashier.bookings.refund', $refundBooking) }}', '{{ $refundBooking->code }}', '{{ addslashes($refundBooking->customerProfile?->name ?? 'Pelanggan') }}', 'Rp{{ number_format($refundBooking->refund_amount, 0, ',', '.') }}', '{{ addslashes($refundBooking->refund_bank_name) }}', '{{ addslashes($refundBooking->refund_account_number) }}', '{{ addslashes($refundBooking->refund_account_name) }}')"
                    class="w-full min-h-[36px] px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white text-xs font-black inline-flex items-center justify-center gap-1.5 shadow-xs transition cursor-pointer"
                >
                    <x-ui.icon name="check" class="w-4 h-4" />
                    <span>Konfirmasi Telah Ditransfer (Selesaikan Refund)</span>
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Daftar Antrean per Bagian (Scroll vertikal dengan batas tinggi maksimal) -->
    @php
    $isSingleStatusFiltered = count($visibleColumns) === 1;
    @endphp
    <div class="space-y-4">
        @foreach($visibleColumns as $statusKey => $col)
        @php
        $list = $filteredByColumn[$statusKey]->values();
        $totalItems = $list->count();
        @endphp
        <div wire:key="column-group-{{ $statusKey }}" class="rounded-2xl border {{ $col['col_bg'] }} p-3.5 flex flex-col">
            <!-- Header Bagian Status -->
            <div class="flex items-center justify-between px-1 pb-2.5 mb-3 border-b border-zinc-200/70 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full {{ $col['dot'] }}"></span>
                    <p class="font-extrabold text-sm text-zinc-900">{{ $col['title'] }}</p>
                </div>
                <span class="text-xs font-extrabold bg-white text-zinc-800 border border-zinc-200/80 px-2.5 py-0.5 rounded-full num shadow-2xs">
                    {{ $totalItems }}
                </span>
            </div>

            <!-- Daftar Item Antrean (Responsif & Rapi Tanpa Batasan Scroll yang Terjebak) -->
            <div class="{{ $isSingleStatusFiltered ? 'grid grid-cols-1 xl:grid-cols-2 gap-4' : 'space-y-4' }}">
                @forelse($list as $ticket)
                @php
                $ticketTotal = (int) $ticket->items->sum('price');
                @endphp
                <div wire:key="ticket-card-{{ $ticket->id }}" class="bg-white rounded-2xl p-4 shadow-xs border border-zinc-200/80 hover:border-zinc-300 transition space-y-3 relative" data-queue-card>
                    <!-- Baris Atas: Nomor Antrean, Info Pelanggan, Barber & Harga/Durasi -->
                    <div class="flex items-start justify-between gap-3">
                        <!-- Sisi Kiri: Nomor Antrean, Nama Pelanggan, Barber & Cabang -->
                        <div class="flex items-start gap-3.5 sm:gap-4 min-w-0 flex-1">
                            <span class="inline-flex items-center justify-center min-w-[58px] px-2.5 py-1.5 rounded-xl bg-zinc-950 text-amber-400 text-xs sm:text-sm font-black num tracking-tight shrink-0 shadow-2xs">
                                {{ $ticket->number }}
                            </span>
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-extrabold text-sm text-zinc-900 truncate">
                                        {{ $ticket->customerProfile?->name ?? 'Pelanggan Walk-in' }}
                                    </p>
                                    @if($ticket->branch)
                                    <span class="px-1.5 py-0.5 rounded-md bg-zinc-100 text-[10px] font-black text-zinc-500 uppercase shrink-0">
                                        {{ $ticket->branch->code }}
                                    </span>
                                    @endif
                                    @if($ticket->booking_id || $ticket->type === 'booking')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-black uppercase tracking-wider shrink-0" title="Antrean Reservasi Booking Jam {{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}">
                                        <x-ui.icon name="calendar" class="w-3 h-3 text-blue-600" />
                                        <span>Booking {{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}</span>
                                    </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 flex-wrap text-xs text-zinc-500 pt-0.5">
                                    <div class="flex items-center gap-1 text-zinc-700">
                                        <x-ui.icon name="scissors" class="w-3 h-3 text-amber-600 shrink-0" />
                                        <span class="font-semibold truncate">{{ $ticket->barber?->display_name ?? 'Otomatis' }}</span>
                                    </div>
                                    <span class="text-zinc-300">·</span>
                                    <div class="flex items-center gap-1 font-mono font-bold text-zinc-700" title="Jam Jadwal / Antrean">
                                        <x-ui.icon name="clock" class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>{{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }} WIB</span>
                                    </div>
                                    <span class="px-1.5 py-0.2 rounded bg-zinc-100 text-zinc-600 text-[10px] font-bold num" title="Nomor Urutan Antrean">
                                        #{{ $ticket->sequence }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Sisi Kanan: Harga & Durasi -->
                        <div class="text-right shrink-0 pl-2">
                            <p class="text-sm font-black text-zinc-950 num leading-tight">
                                Rp{{ number_format($ticketTotal, 0, ',', '.') }}
                            </p>
                            <p class="text-[11px] font-semibold text-zinc-400 num leading-tight mt-0.5">
                                ~{{ $ticket->estimated_duration_minutes }} mnt
                            </p>
                        </div>
                    </div>

                    <!-- Garis Pemisah Halus -->
                    <div class="border-t border-zinc-100"></div>

                    <!-- Baris Layanan & Catatan Khusus -->
                    <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                        @foreach($ticket->items as $item)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-zinc-100 text-zinc-700 text-[11px] font-semibold">
                            {{ $item->service_name }}
                        </span>
                        @endforeach
                        @if($ticket->hairColor)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200/80 text-amber-900 text-[11px] font-extrabold shadow-2xs">
                            <span class="w-2.5 h-2.5 rounded-full border border-black/15 shrink-0" @style(['background-color: ' . $ticket->hairColor->hex_code])></span>
                            <span>Warna: {{ $ticket->hairColor->name }}</span>
                        </span>
                        @endif
                    </div>

                    @if($ticket->notes)
                    <div class="rounded-xl bg-amber-50/70 border border-amber-200/60 px-3 py-1.5 text-xs text-amber-900 flex items-start gap-1.5">
                        <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
                        <span class="leading-relaxed"><b class="font-bold">Catatan:</b> {{ $ticket->notes }}</span>
                    </div>
                    @endif

                    <!-- Baris Status & Aksi Operasional (Responsif Rapi di Mobile & Tablet) -->
                    <div class="pt-1">
                        @if($ticket->status === \App\Enums\QueueStatus::Waiting)
                        <div class="flex items-center justify-between gap-2 flex-wrap sm:flex-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-zinc-100 text-zinc-600 text-xs font-bold whitespace-nowrap">
                                <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                                <span>Menunggu Giliran (#{{ $ticket->sequence }})</span>
                            </span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    type="button"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->number }}"
                                    data-customer-name="{{ $ticket->customerProfile?->name ?? 'Pelanggan Walk-in' }}"
                                    data-barber-id="{{ $ticket->barber_membership_id ?? '' }}"
                                    data-barber-name="{{ $ticket->barber?->display_name ?? 'Otomatis' }}"
                                    data-time="{{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}"
                                    data-date="{{ ($ticket->priority_at ?? $ticket->created_at)->format('Y-m-d') }}"
                                    onclick="openRescheduleModal(this.dataset.ticketId, this.dataset.ticketNumber, this.dataset.customerName, this.dataset.barberId, this.dataset.barberName, this.dataset.time, this.dataset.date)"
                                    class="min-h-[34px] px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 text-xs font-extrabold cursor-pointer transition shadow-2xs inline-flex items-center gap-1.5"
                                    title="Reschedule jadwal / antrean ini"
                                >
                                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-950" />
                                    <span>Reschedule</span>
                                </button>
                                <button
                                    type="button"
                                    data-cancel-url="{{ route('cashier.tickets.transition', $ticket) }}"
                                    data-ticket-number="{{ $ticket->number }}"
                                    data-customer-name="{{ $ticket->customerProfile?->name ?? 'Pelanggan Walk-in' }}"
                                    onclick="openCancelTicketModal(this.dataset.cancelUrl, this.dataset.ticketNumber, this.dataset.customerName)"
                                    class="min-h-[34px] px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-95 text-white text-xs font-bold cursor-pointer transition shadow-2xs inline-flex items-center gap-1"
                                    title="Batalkan antrean ini"
                                >
                                    <span>Batal</span>
                                </button>
                            </div>
                        </div>
                        @elseif($ticket->status === \App\Enums\QueueStatus::Called)
                        <div class="flex items-center justify-between gap-2 flex-wrap sm:flex-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-900 text-xs font-bold whitespace-nowrap shadow-2xs">
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Dipanggil Barber ({{ $ticket->barber?->display_name ?? 'Kursi Barber' }})</span>
                            </span>
                            <button
                                type="button"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->number }}"
                                data-customer-name="{{ $ticket->customerProfile?->name ?? 'Pelanggan Walk-in' }}"
                                data-barber-id="{{ $ticket->barber_membership_id ?? '' }}"
                                data-barber-name="{{ $ticket->barber?->display_name ?? 'Otomatis' }}"
                                data-time="{{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}"
                                data-date="{{ ($ticket->priority_at ?? $ticket->created_at)->format('Y-m-d') }}"
                                onclick="openRescheduleModal(this.dataset.ticketId, this.dataset.ticketNumber, this.dataset.customerName, this.dataset.barberId, this.dataset.barberName, this.dataset.time, this.dataset.date)"
                                class="min-h-[32px] px-3 py-1 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-700 text-xs font-bold cursor-pointer transition inline-flex items-center gap-1"
                                title="Reschedule jika pelanggan minta waktu tambahan"
                            >
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-600" />
                                <span>Reschedule</span>
                            </button>
                        </div>
                        @elseif($ticket->status === \App\Enums\QueueStatus::InService)
                        <div class="flex items-center justify-start">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-50 border border-sky-200/80 text-sky-800 text-xs font-bold whitespace-nowrap shadow-2xs">
                                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                <span>Sedang Dicukur ({{ $ticket->barber?->display_name ?? 'Barber' }})</span>
                            </span>
                        </div>
                        @elseif($ticket->status === \App\Enums\QueueStatus::Completed)
                        <div class="flex items-center justify-start">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-bold whitespace-nowrap shadow-2xs">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>Layanan Selesai Dikerjakan</span>
                            </span>
                        </div>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-zinc-100 text-zinc-500 text-xs font-bold capitalize">
                                {{ $ticket->status->value }}
                            </span>
                        @endif

                        <!-- Bagian Pembayaran di Muka / Saat Antre / Selesai -->
                        <div class="mt-2.5 pt-2 border-t border-zinc-100">
                            @if($ticket->order && $ticket->order->payment_status === \App\Enums\PaymentStatus::Paid)
                                @php
                                $paidMethod = match ($ticket->order->payments->first()?->method?->value ?? 'cash') {
                                'qris' => 'QRIS',
                                'transfer' => 'Transfer',
                                default => 'Tunai',
                                };
                                $isOnlineCustomer = $ticket->customerProfile?->user_id !== null || $ticket->booking_id !== null;
                                @endphp
                                <div class="rounded-xl bg-emerald-50/80 border border-emerald-200/80 p-2.5 sm:px-3 sm:py-2 flex items-center justify-between gap-3 w-full">
                                    <div class="min-w-0 space-y-0.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-800">
                                                <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                <span>Lunas</span>
                                            </span>
                                            <span class="text-emerald-300 font-bold text-xs select-none">·</span>
                                            <span class="px-2 py-0.5 rounded-md bg-white border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-wider">
                                                {{ $paidMethod }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] font-bold text-emerald-950 num truncate">
                                            {{ $ticket->order->number }}
                                        </p>
                                    </div>
                                    <a
                                        href="{{ route('invoices.show', $ticket->order->number) }}"
                                        class="min-h-[32px] px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold inline-flex items-center justify-center gap-1.5 shrink-0 shadow-2xs transition"
                                    >
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                        <span>Invoice</span>
                                    </a>
                                </div>
                            @elseif($ticket->order && $ticket->order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                                @php
                                    $pendingPayment = $ticket->order->payments->first();
                                    $isQris = $pendingPayment?->method === \App\Enums\PaymentMethod::Qris;
                                @endphp
                                <div class="w-full rounded-2xl bg-amber-500/10 border border-amber-500/25 p-3 space-y-2.5 shadow-2xs">
                                    <!-- Baris Atas: Info Status & Nominal Tagihan Rapi Terpisah -->
                                    <div class="flex items-center justify-between gap-2.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
                                            <span class="text-xs font-black text-amber-950 truncate">Menunggu Konfirmasi</span>
                                            <span class="px-2 py-0.5 rounded-md bg-amber-200 text-amber-950 text-[10px] font-black uppercase tracking-wider shrink-0">
                                                {{ $isQris ? 'QRIS' : 'Tunai' }}
                                            </span>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <span class="text-xs font-black text-amber-950 num">
                                                Rp{{ number_format($ticket->order->grand_total, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Baris Bawah: Tombol Mengarah ke Halaman Baru Pembayaran Kasir -->
                                    <a
                                        href="{{ route('cashier.tickets.payment', $ticket) }}"
                                        class="w-full min-h-[38px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white text-xs font-black inline-flex items-center justify-center gap-2 shadow-sm transition cursor-pointer"
                                        title="Buka halaman proses pembayaran kasir"
                                    >
                                        <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                                        <span>{{ $isQris ? 'Cek QRIS & Selesaikan' : 'Terima Tunai & Selesaikan' }}</span>
                                    </a>
                                </div>
                            @else
                                @if($ticket->is_cashier_input)
                                    <!-- Dimasukkan langsung oleh kasir: tidak perlu di-lock -->
                                    <div class="flex items-center justify-between gap-2 p-2 rounded-xl bg-rose-50 border border-rose-200/80">
                                        <div class="min-w-0">
                                            <span class="text-xs font-extrabold text-rose-800 block">Belum Bayar</span>
                                            <span class="text-[11px] font-bold text-rose-600 num">Rp{{ number_format($ticket->items->sum('price'), 0, ',', '.') }}</span>
                                        </div>
                                        <a
                                            href="{{ route('cashier.tickets.payment', $ticket) }}"
                                            class="min-h-[34px] px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 text-xs font-black inline-flex items-center justify-center gap-1.5 shadow-2xs transition cursor-pointer"
                                            title="Buka halaman baru untuk memproses pembayaran kasir"
                                        >
                                            <x-ui.icon name="banknote" class="w-4 h-4 stroke-[2.5]" />
                                            <span>Bayar di Kasir</span>
                                        </a>
                                    </div>
                                @else
                                    <!-- Tiket customer (Booking / QR mandiri): Tombol bayar di-lock menunggu customer memilih metode pembayaran -->
                                    <div class="w-full rounded-2xl bg-amber-500/10 border border-amber-500/30 p-3 space-y-2 text-center shadow-2xs">
                                        <div class="flex items-center justify-between gap-2 text-xs font-black text-amber-950">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
                                                <span class="truncate">Menunggu Pilihan Pembayaran</span>
                                            </div>
                                            <span class="text-xs font-black text-amber-950 num shrink-0">
                                                Rp{{ number_format($ticket->items->sum('price'), 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-amber-900/80 leading-tight text-left">
                                            Customer sedang memilih metode pembayaran (QRIS / Tunai) di ponselnya.
                                        </p>
                                        <button
                                            type="button"
                                            disabled
                                            class="w-full min-h-[36px] px-3 py-2 rounded-xl bg-zinc-200/90 text-zinc-400 text-xs font-black inline-flex items-center justify-center gap-2 cursor-not-allowed select-none border border-zinc-300/60"
                                            title="Tombol terkunci sementara sampai customer memilih metode pembayaran di ponselnya"
                                        >
                                            <x-ui.icon name="lock" class="w-4 h-4 text-zinc-400 shrink-0" />
                                            <span>Tombol Bayar Terkunci</span>
                                        </button>
                                        <div class="pt-0.5">
                                            <a
                                                href="{{ route('cashier.tickets.payment', ['ticket' => $ticket, 'bypass' => 1]) }}"
                                                class="text-[10px] text-zinc-500 hover:text-amber-800 underline transition inline-flex items-center gap-1"
                                                title="Klik jika customer tidak membawa ponsel atau ingin bayar langsung di kasir"
                                            >
                                                <span>Customer bayar langsung tanpa HP?</span>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="{{ $isSingleStatusFiltered ? 'xl:col-span-2' : '' }} rounded-xl border border-dashed border-zinc-300/80 bg-white/50 py-3.5 px-3 text-center">
                    <p class="text-xs font-semibold text-zinc-400">Tidak ada antrean pada bagian ini</p>
                </div>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

    <!-- DIALOG MODAL: DAFTAR JADWAL RESERVASI MENDATANG -->
    <dialog
        id="modal-future-bookings"
        onclick="if(event.target===this)this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white text-zinc-900 p-0 shadow-2xl border border-zinc-200 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs"
    >
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-sky-100 text-sky-700 grid place-items-center shrink-0">
                        <x-ui.icon name="calendar" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-zinc-950">Jadwal Reservasi Mendatang</h3>
                        <p class="text-xs text-zinc-500">Daftar pelanggan yang telah memesan jadwal esok &amp; seterusnya</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-future-bookings').close()"
                    class="p-2 rounded-xl text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 transition cursor-pointer"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto space-y-2.5 pr-1">
                @forelse($futureBookingsFiltered ?? [] as $fb)
                    <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex items-start justify-between gap-3">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-800 text-[10px] font-black uppercase">
                                    {{ $fb->scheduled_at->translatedFormat('D, d M Y · H:i') }} WIB
                                </span>
                                <span class="text-[10px] font-mono font-bold text-zinc-500">{{ $fb->code }}</span>
                            </div>
                            <div class="font-bold text-xs text-zinc-900 truncate">
                                {{ $fb->customerProfile?->name ?? 'Pelanggan' }}
                                @if($fb->customerProfile?->phone)
                                    <span class="font-normal text-zinc-500 text-[11px]">({{ $fb->customerProfile->phone }})</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-zinc-500 truncate flex items-center gap-1.5">
                                <x-ui.icon name="scissors" class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span>{{ $fb->barber?->display_name ?? 'Barber Bebas' }} &middot; {{ $fb->items->pluck('service_name')->join(', ') }}</span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $fb->status->value === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $fb->status->value === 'confirmed' ? 'Terkonfirmasi' : 'Menunggu' }}
                        </span>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-400 bg-zinc-50 rounded-2xl border border-dashed border-zinc-200">
                        Belum ada reservasi booking untuk hari-hari mendatang.
                    </div>
                @endforelse
            </div>

            <div class="pt-2 border-t border-zinc-100 flex justify-end">
                <button
                    type="button"
                    onclick="document.getElementById('modal-future-bookings').close()"
                    class="px-4 py-2 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-white font-bold text-xs transition cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </dialog>
</div>
