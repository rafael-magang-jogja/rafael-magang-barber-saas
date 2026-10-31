@php
$currentTab = $activeTab ?? 'board';

$pageMeta = match ($currentTab) {
'walkin' => [
'title' => 'Input Antrean Walk-in Baru',
'subtitle' => 'Buat nomor antrean untuk pelanggan yang datang langsung ke outlet',
],
'bookings' => [
'title' => 'Daftar & Check-in Booking',
'subtitle' => 'Kelola booking hari ini, tindak lanjuti booking terlewat, dan pantau reservasi mendatang',
],
default => [
'title' => 'Papan Antrean & POS',
'subtitle' => 'Monitor antrean aktif dan proses pembayaran kasir',
],
};

$currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
$knownPending = isset($pendingBookings) ? $pendingBookings->isNotEmpty() : null;
$hasWalkinBookingDot = $currentTenantCtx->hasWalkinBookingDot($knownPending);

$effectiveRole = $currentTenantCtx->role()
    ?? request()->attributes->get('current_role')
    ?? 'cashier';
$isCashierOrAdmin = in_array($effectiveRole, ['admin', 'cashier'], true) && $effectiveRole !== 'owner';

$mobileTabs = [
'board' => ['label' => 'Papan POS', 'href' => route('cashier.dashboard'), 'icon' => 'ticket', 'dot' => false],
'bookings' => ['label' => 'Booking Pelanggan', 'href' => route('cashier.bookings'), 'icon' => 'calendar', 'dot' => $hasWalkinBookingDot],
'attendance' => ['label' => 'Absen & Jadwal', 'href' => route('cashier.attendance'), 'icon' => 'clock', 'dot' => false],
];

$branchOptions = isset($branches) ? $branches->map(fn ($b) => [
'value' => (string) $b->id,
'label' => $b->name,
'sublabel' => $b->code,
])->all() : [];

$serviceOptions = isset($services) ? $services->map(fn ($s) => [
'value' => (string) $s->id,
'label' => $s->name . ' (Rp' . number_format($s->price, 0, ',', '.') . ')',
'sublabel' => ($s->category ? $s->category->name . ' · ' : '') . $s->duration_minutes . ' menit',
])->all() : [];

$barberOptions = isset($barbers) ? $barbers->map(fn ($b) => [
'value' => (string) $b->id,
'label' => $b->display_name ?? $b->user->name,
'sublabel' => ($b->branch?->name ?? 'Cabang Utama') . ' · ' . ($b->specialty ?? 'Barber'),
])->all() : [];

// Khusus di sisi Kasir / Owner: tampilkan informasi sisa stok & status minimum stok warna coloring
$cashierColorOptions = isset($hairColors) ? $hairColors->map(fn ($c) => [
'value' => (string) $c->id,
'label' => $c->name . ' (Stok: ' . $c->stock . ' tube)',
'sublabel' => $c->isNearOrBelowMinStock()
? 'Mendekati min. stok (' . $c->min_stock . ')'
: 'Min. stok: ' . $c->min_stock,
'color' => $c->hex_code,
])->all() : [];

$firstColor = isset($hairColors) ? $hairColors->first() : null;

$firstServiceIsColoring = isset($services) && $services->first()
&& (stripos($services->first()->name, 'color') !== false || stripos($services->first()->name, 'warna') !== false || ($services->first()->category && (stripos($services->first()->category->name, 'warna') !== false || stripos($services->first()->category->name, 'color') !== false)));

$workStatusOptions = [
['value' => 'active', 'label' => 'Siap Melayani (Active)'],
['value' => 'break', 'label' => 'Istirahat (Break)'],
['value' => 'off', 'label' => 'Selesai Shift (Off)'],
];

$paymentMethodOptions = [
['value' => 'cash', 'label' => 'Tunai (Cash)'],
['value' => 'qris', 'label' => 'QRIS'],
];

$cashierDateOptions = [
['value' => 'today', 'label' => 'Hari Ini (' . now()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean hari ini'],
['value' => 'yesterday', 'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean kemarin'],
['value' => 'custom', 'label' => 'Pilih Tanggal...', 'sublabel' => 'Kustom tanggal'],
];

$filterBranchId = request()->filled('branch_id') ? request()->integer('branch_id') : null;
$filterBarberId = request()->filled('barber_id') ? request()->integer('barber_id') : null;
$filterDateType = request()->input('date_filter', 'today');
@endphp

<x-layouts.app :title="$pageMeta['title']" :subtitle="$pageMeta['subtitle']" :role="$effectiveRole" :full-width="true">
    @if(! $isCashierOrAdmin)
    <!-- Sub-Navigation khusus Mobile (Hanya tampil jika Owner mengakses halaman Kasir, karena Kasir/Admin menggunakan Bottom Navigation) -->
    <div class="grid grid-cols-3 gap-1 mb-4 bg-zinc-200/80 p-1 rounded-2xl md:hidden">
        @foreach($mobileTabs as $tabKey => $tab)
        @php
        $isTabActive = $currentTab === $tabKey || ($tabKey === 'bookings' && $currentTab === 'walkin');
        $badgeType = match ($tabKey) {
            'board' => 'queue',
            'bookings' => 'booking',
            default => null,
        };
        @endphp
        <a
            href="{{ $tab['href'] }}"
            class="relative flex items-center justify-center gap-1 py-2 px-1.5 rounded-xl text-[11px] font-bold text-center transition {{ $isTabActive ? 'bg-zinc-950 text-amber-400 shadow-sm' : 'text-zinc-600 hover:text-zinc-900' }}">
            <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5" />
            <span class="truncate">{{ $tab['label'] }}</span>
            @if($badgeType)
                <livewire:cashier-nav-badge :type="$badgeType" context="subnav" :key="'badge-' . $badgeType . '-subnav'" />
            @endif
        </a>
        @endforeach
    </div>
    @endif

    @if($currentTab === 'walkin')
    <!-- HALAMAN KHUSUS: Input Antrean Walk-in Baru (Full Width Kanan-Kiri di Desktop) -->
    <div class="w-full space-y-4">
        <div class="flex items-center justify-between">
            <a
                href="{{ route('cashier.dashboard') }}"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-600 hover:text-zinc-950 bg-white border border-zinc-200/80 px-3.5 py-2 rounded-xl shadow-2xs transition">
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Kembali ke Papan Antrean</span>
            </a>
        </div>

        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold text-base flex items-center gap-2">
                    <x-ui.icon name="plus" class="w-4 h-4 text-amber-600" />
                    <span>Form Pendaftaran Antrean Walk-in</span>
                </h2>
            </x-slot:header>

            <form method="POST" action="{{ route('cashier.walk-in.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-ui.select
                        label="Cabang"
                        name="branch_id"
                        icon="store"
                        :options="$branchOptions"
                        search-placeholder="Cari cabang..." />

                    <x-ui.input label="Nama Pelanggan *" name="customer_name" placeholder="Andika" required />

                    <x-ui.input label="Nomor HP (Opsional)" name="customer_phone" placeholder="0812..." />

                    <x-ui.select
                        label="Pilih Barber"
                        name="barber_membership_id"
                        placeholder="Otomatis (Tercepat)"
                        icon="users"
                        :options="$barberOptions"
                        search-placeholder="Cari nama barber..." />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-4">
                        <x-ui.select
                            label="Layanan (Bisa Pilih Lebih dari Satu: Potong + Keramas, dll.)"
                            name="service_ids[]"
                            icon="scissors"
                            multiple
                            :options="$serviceOptions"
                            search-placeholder="Cari layanan..." />

                        @if(!empty($cashierColorOptions))
                        <div data-coloring-picker class="{{ $firstServiceIsColoring ? '' : 'hidden' }} space-y-2">
                            <x-ui.select
                                label="Pilih Warna Coloring (Info Stok Khusus Kasir & Owner)"
                                name="hair_color_id"
                                :options="$cashierColorOptions"
                                search-placeholder="Cari warna coloring..." />

                            <!-- Live Preview Warna Terpilih -->
                            <div data-color-live-preview class="flex items-center gap-3 p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 shadow-2xs">
                                <span
                                    data-preview-swatch
                                    class="w-9 h-9 rounded-xl border border-black/15 shadow-2xs shrink-0 transition"
                                    style="background-color: {{ $firstColor?->hex_code ?? '#B45309' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Preview Warna:</span>
                                        <span data-preview-name class="text-xs font-extrabold text-zinc-900">{{ $firstColor?->name ?? 'Pilih Warna' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span data-preview-hex class="text-[11px] font-extrabold text-zinc-500 uppercase num">{{ $firstColor?->hex_code ?? '' }}</span>
                                        @if($firstColor)
                                        <span class="text-zinc-300">·</span>
                                        <span data-preview-stock class="text-[11px] font-semibold text-zinc-600 num">Stok: {{ $firstColor->stock }} tube</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <x-ui.input label="Catatan Gaya Rambut / Permintaan Khusus (Opsional)" name="notes" placeholder="Contoh: Fade tipis samping + keramas air hangat" />
                </div>

                <div class="pt-3 border-t border-zinc-100 flex flex-wrap items-center justify-end gap-2.5">
                    <x-ui.button :href="route('cashier.bookings')" variant="outline" size="md" class="min-w-[140px]">
                        <span>Batal</span>
                    </x-ui.button>
                    <x-ui.button type="submit" size="md" class="min-w-[180px]">
                        <x-ui.icon name="plus" class="w-4 h-4" />
                        <span>Buat Tiket Antrean</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <script>
        (function() {
            function syncWalkinColorPicker() {
                document.querySelectorAll('form').forEach((form) => {
                    const picker = form.querySelector('[data-coloring-picker]');
                    const serviceSelect = form.querySelector('[data-ui-select][data-input-name="service_ids[]"]');
                    if (!picker || !serviceSelect) return;

                    const selected = Array.from(serviceSelect.querySelectorAll('[data-select-option][aria-selected="true"]'));
                    const hasColoring = selected.some((btn) => {
                        const label = (btn.getAttribute('data-label') || '').toLowerCase();
                        const sublabel = (btn.getAttribute('data-sublabel') || '').toLowerCase();
                        return label.includes('color') || label.includes('warna') || sublabel.includes('pewarnaan') || sublabel.includes('warna');
                    });

                    picker.classList.toggle('hidden', !hasColoring);
                    picker.querySelectorAll('input[name="hair_color_id"]').forEach((inp) => {
                        inp.disabled = !hasColoring;
                    });

                    syncColorLivePreview(form);
                });
            }

            function syncColorLivePreview(form) {
                const colorSelect = form.querySelector('[data-ui-select][data-input-name="hair_color_id"]');
                const previewBox = form.querySelector('[data-color-live-preview]');
                if (!colorSelect || !previewBox) return;

                const activeOpt = colorSelect.querySelector('[data-select-option][aria-selected="true"]') ||
                    colorSelect.querySelector('[data-select-option]:not(.hidden)');
                if (!activeOpt) return;

                const color = activeOpt.getAttribute('data-color') || '';
                const label = activeOpt.getAttribute('data-label') || '';
                const sublabel = activeOpt.getAttribute('data-sublabel') || '';

                const swatch = previewBox.querySelector('[data-preview-swatch]');
                const name = previewBox.querySelector('[data-preview-name]');
                const hex = previewBox.querySelector('[data-preview-hex]');
                const stock = previewBox.querySelector('[data-preview-stock]');

                if (swatch && color) {
                    swatch.style.backgroundColor = color;
                }
                if (name) {
                    name.textContent = label.split('(')[0].trim();
                }
                if (hex) {
                    hex.textContent = color;
                }
                if (stock && sublabel) {
                    stock.textContent = sublabel;
                }
            }

            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-select-option]')) {
                    requestAnimationFrame(syncWalkinColorPicker);
                }
            });
            syncWalkinColorPicker();
        })();
    </script>

    @elseif($currentTab === 'bookings')
    @php
        $todayBookingsList = $todayBookings ?? collect();
        $pastBookingsList = $pastBookings ?? collect();
        $futureBookingsList = $futureBookings ?? collect();
        $allBookingsList = $allBookings ?? collect();

        $todayCount = $todayBookingsList->count();
        $pastCount = $pastBookingsList->count();
        $futureCount = $futureBookingsList->count();
        $totalCount = $allBookingsList->count();

        // Selalu prioritaskan tab Hari Ini saat halaman dibuka
        $initialGroup = 'today';
    @endphp

    <!-- HALAMAN KHUSUS: Check-in & Daftar Semua Booking (Hari Ini, Sudah Lewat, & Mendatang) -->
    <div x-data="{ activeGroup: '{{ $initialGroup }}' }" class="space-y-4">
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm sm:text-base flex items-center gap-2 text-zinc-900 truncate">
                            <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Daftar Booking Pelanggan</span>
                        </h2>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Total <span class="font-bold num text-zinc-700">{{ $totalCount }}</span> booking terdata (Hari ini, Sudah Lewat, &amp; Mendatang)
                        </p>
                    </div>
                </div>
            </x-slot:header>

            <!-- Segmented Filter Tabs: Grid 2x2 pada Mobile/Tablet, 4 Kolom pada Desktop (Tanpa Animasi & Tanpa Outline) -->
            <div class="p-2 sm:p-2.5 bg-zinc-50/90 border-b border-zinc-100">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <!-- Tab: Hari Ini -->
                    <button
                        type="button"
                        @click="activeGroup = 'today'"
                        :class="activeGroup === 'today' ? 'bg-amber-500 text-zinc-950 font-bold border-amber-500' : 'bg-white hover:bg-zinc-100 text-zinc-700 border-zinc-200 font-medium'"
                        style="outline: none !important; box-shadow: none !important; -webkit-tap-highlight-color: transparent !important;"
                        class="w-full flex items-center justify-between gap-1.5 py-2 px-2.5 sm:px-3 rounded-xl text-xs border outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 shrink-0" />
                            <span class="truncate">Hari Ini</span>
                        </span>
                        <span
                            :class="activeGroup === 'today' ? 'bg-zinc-950 text-amber-400' : 'bg-zinc-100 text-zinc-700'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0">
                            {{ $todayCount }}
                        </span>
                    </button>

                    <!-- Tab: Sudah Lewat (Perlu Ditindak) -->
                    <button
                        type="button"
                        @click="activeGroup = 'past'"
                        :class="activeGroup === 'past' ? 'bg-rose-600 text-white font-bold border-rose-600' : 'bg-white hover:bg-zinc-100 text-zinc-700 border-zinc-200 font-medium'"
                        style="outline: none !important; box-shadow: none !important; -webkit-tap-highlight-color: transparent !important;"
                        class="w-full flex items-center justify-between gap-1.5 py-2 px-2.5 sm:px-3 rounded-xl text-xs border outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="clock" class="w-3.5 h-3.5 shrink-0" />
                            <span class="truncate">Sudah Lewat</span>
                        </span>
                        <span
                            :class="activeGroup === 'past' ? 'bg-rose-950 text-rose-200' : ({{ $pastCount > 0 ? 'true' : 'false' }} ? 'bg-rose-100 text-rose-700 font-black' : 'bg-zinc-100 text-zinc-700')"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0">
                            {{ $pastCount }}
                        </span>
                    </button>

                    <!-- Tab: Hari Lain / Mendatang -->
                    <button
                        type="button"
                        @click="activeGroup = 'future'"
                        :class="activeGroup === 'future' ? 'bg-sky-600 text-white font-bold border-sky-600' : 'bg-white hover:bg-zinc-100 text-zinc-700 border-zinc-200 font-medium'"
                        style="outline: none !important; box-shadow: none !important; -webkit-tap-highlight-color: transparent !important;"
                        class="w-full flex items-center justify-between gap-1.5 py-2 px-2.5 sm:px-3 rounded-xl text-xs border outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 shrink-0" />
                            <span class="truncate">Mendatang</span>
                        </span>
                        <span
                            :class="activeGroup === 'future' ? 'bg-sky-950 text-sky-200' : 'bg-zinc-100 text-zinc-700'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0">
                            {{ $futureCount }}
                        </span>
                    </button>

                    <!-- Tab: Semua Kategori -->
                    <button
                        type="button"
                        @click="activeGroup = 'all'"
                        :class="activeGroup === 'all' ? 'bg-zinc-900 text-white font-bold border-zinc-900' : 'bg-white hover:bg-zinc-100 text-zinc-700 border-zinc-200 font-medium'"
                        style="outline: none !important; box-shadow: none !important; -webkit-tap-highlight-color: transparent !important;"
                        class="w-full flex items-center justify-between gap-1.5 py-2 px-2.5 sm:px-3 rounded-xl text-xs border outline-none focus:outline-none focus:ring-0 focus-visible:outline-none select-none">
                        <span class="truncate">Semua Kategori</span>
                        <span
                            :class="activeGroup === 'all' ? 'bg-zinc-700 text-zinc-200' : 'bg-zinc-100 text-zinc-700'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0">
                            {{ $totalCount }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Konten Grup 1: Hari Ini -->
            <div x-show="activeGroup === 'today' || activeGroup === 'all'" class="p-3.5 sm:p-4 space-y-3">
                <div class="flex items-center justify-between pb-1 border-b border-zinc-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-700 flex items-center gap-1.5">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                        <span>Hari Ini (Sesuai Hari Booking) — {{ $todayCount }} Booking</span>
                    </h3>
                    <span class="text-[11px] text-zinc-400 font-medium">{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>

                @forelse($todayBookingsList as $booking)
                    @include('dashboards.partials.cashier-booking-item', ['booking' => $booking, 'group' => 'today', 'cashierColorOptions' => $cashierColorOptions])
                @empty
                    <div class="py-6 text-center text-xs text-zinc-400 bg-zinc-50/50 rounded-xl border border-dashed border-zinc-200">
                        Tidak ada booking yang menunggu check-in hari ini.
                    </div>
                @endforelse
            </div>

            <!-- Konten Grup 2: Sudah Lewat (Perlu Ditindak) -->
            <div x-show="activeGroup === 'past' || activeGroup === 'all'" class="p-3.5 sm:p-4 space-y-3 border-t border-zinc-100 bg-rose-50/20">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 pb-1 border-b border-rose-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 flex items-center gap-1.5">
                        <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                        <span>Sudah Lewat (Biasanya Lupa Ditindak) — {{ $pastCount }} Booking</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500">
                        Segera check-in jika pelanggan tetap dilayani, atau tandai No-Show / Batal.
                    </p>
                </div>

                @forelse($pastBookingsList as $booking)
                    @include('dashboards.partials.cashier-booking-item', ['booking' => $booking, 'group' => 'past', 'cashierColorOptions' => $cashierColorOptions])
                @empty
                    <div class="py-6 text-center text-xs text-zinc-400 bg-white rounded-xl border border-dashed border-zinc-200">
                        Tidak ada booking terlewat yang belum ditindak. Semua data rapi!
                    </div>
                @endforelse
            </div>

            <!-- Konten Grup 3: Hari Lain / Mendatang -->
            <div x-show="activeGroup === 'future' || activeGroup === 'all'" class="p-3.5 sm:p-4 space-y-3 border-t border-zinc-100 bg-sky-50/20">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 pb-1 border-b border-sky-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-sky-700 flex items-center gap-1.5">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                        <span>Hari Lain / Mendatang — {{ $futureCount }} Booking</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500">
                        Reservasi hari esok dan seterusnya. Check-in awal diperbolehkan bila pelanggan hadir lebih cepat.
                    </p>
                </div>

                @forelse($futureBookingsList as $booking)
                    @include('dashboards.partials.cashier-booking-item', ['booking' => $booking, 'group' => 'future', 'cashierColorOptions' => $cashierColorOptions])
                @empty
                    <div class="py-6 text-center text-xs text-zinc-400 bg-white rounded-xl border border-dashed border-zinc-200">
                        Belum ada reservasi booking untuk hari-hari mendatang.
                    </div>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    @else
    <!-- HALAMAN 1 (Default): Papan Antrean & POS -->

    @php
    $filterStatus = request()->filled('status') ? (string) request('status') : null;

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

    $filteredByColumn = [];
    $totalFilteredTickets = 0;
    foreach ($columns as $statusKey => $colMeta) {
        $colTickets = $tickets->get($statusKey, collect())
            ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
            ->when($filterBarberId, fn ($c) => $c->where('barber_membership_id', $filterBarberId));

        if (in_array($statusKey, ['waiting', 'completed'], true)) {
            // Urut dari yang TERBARU (Newest first)
            $colTickets = $colTickets->sortByDesc(function ($t) use ($statusKey) {
                if ($statusKey === 'completed') {
                    return $t->completed_at?->timestamp ?? $t->updated_at?->timestamp ?? $t->sequence;
                }
                return $t->created_at?->timestamp ?? $t->sequence;
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

    $cashierDateOptions = [
        ['value' => 'today', 'label' => 'Hari Ini (' . now()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean hari ini'],
        ['value' => 'yesterday', 'label' => 'Kemarin (' . now()->subDay()->translatedFormat('d M') . ')', 'sublabel' => 'Antrean kemarin'],
        ['value' => 'custom', 'label' => 'Pilih Tanggal...', 'sublabel' => 'Cek tanggal tertentu'],
    ];
    $currentDateFilter = request('date_filter', 'today');
    $isCustomDate = $currentDateFilter === 'custom';
    @endphp

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
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                    <h2 class="text-base sm:text-lg font-black text-zinc-950 tracking-tight whitespace-nowrap">
                        Papan Antrean &amp; POS
                    </h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black uppercase tracking-wider shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live Monitor
                    </span>
                </div>
                <p class="text-xs text-zinc-500 mt-0.5 leading-relaxed">
                    Monitor antrean live dan layani pembayaran kasir di outlet
                </p>
            </div>
        </div>

        <!-- Tombol Input Antrean Walk-in (Didesain Rapi & Menonjol) -->
        <a
            href="{{ route('cashier.walk-in') }}"
            class="cashier-pos-cta-btn group inline-flex items-center justify-center gap-2.5 px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs sm:text-sm shadow-md shadow-zinc-950/10 transition cursor-pointer select-none shrink-0 w-full sm:w-auto"
            title="Daftarkan antrean pelanggan walk-in yang datang langsung ke outlet"
        >
            <span class="w-6 h-6 rounded-xl bg-zinc-950/15 group-hover:bg-zinc-950/20 text-zinc-950 flex items-center justify-center shrink-0 transition">
                <x-ui.icon name="plus" class="w-3.5 h-3.5 stroke-[3]" />
            </span>
            <span class="whitespace-nowrap">Input Antrean Walk-in</span>
        </a>
    </div>

    <!-- 2. TOOLBAR FILTER & TAB STATUS (SIMETRIS & RAPI DI DESKTOP, TABLET, MOBILE) -->
    <div class="bg-white rounded-2xl border border-zinc-200/80 p-3 sm:p-3.5 shadow-xs mb-4 space-y-3">
        <!-- Form Filter Dropdown 3 Kolom Seimbang -->
        <form method="GET" action="{{ route('cashier.dashboard') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            @if($filterStatus)
            <input type="hidden" name="status" value="{{ $filterStatus }}" />
            @endif

            <div>
                <x-ui.select
                    name="branch_id"
                    size="sm"
                    placeholder="Semua Cabang"
                    icon="store"
                    :options="$branchOptions"
                    :value="request('branch_id')"
                    search-placeholder="Cari cabang..."
                    class="w-full"
                    auto-submit />
            </div>

            <div>
                <x-ui.select
                    name="barber_id"
                    size="sm"
                    placeholder="Semua Barber"
                    icon="scissors"
                    :options="$barberOptions"
                    :value="request('barber_id')"
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
                        value="{{ request('custom_date', $selectedDate ?? now()->toDateString()) }}"
                        onchange="this.form.submit()"
                        class="w-full sm:max-w-xs h-9 px-3 rounded-xl border border-zinc-200 text-xs font-semibold text-zinc-800 bg-white shadow-2xs focus:outline-none focus:ring-2 focus:ring-zinc-950/10 focus:border-zinc-950 transition" />
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

        <!-- Filter Tab Status (Pola Baris 1 - 2 - 2 di Mobile/Tablet, 1 Baris di Desktop) -->
        @php
        $baseQueryParams = array_filter([
            'branch_id' => $filterBranchId,
            'barber_id' => $filterBarberId,
            'date_filter' => $currentDateFilter !== 'today' ? $currentDateFilter : null,
            'custom_date' => $currentDateFilter === 'custom' ? request('custom_date') : null,
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

    <!-- Monitor Kursi Barber (Compact Live Monitor - Read Only) -->
    @php
    $allTodayTickets = $tickets->flatten(1);
    $filteredBarbers = $barbers
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
            <div class="rounded-xl border {{ $cardBorder }} bg-white p-2.5 shadow-2xs transition flex flex-col justify-between gap-2 flex-1 min-w-[150px] sm:min-w-[170px] xl:min-w-[190px]">
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
        <div class="rounded-2xl border {{ $col['col_bg'] }} p-3.5 flex flex-col">
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
                <div class="bg-white rounded-2xl p-4 shadow-xs border border-zinc-200/80 hover:border-zinc-300 transition space-y-3 relative" data-queue-card>
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
                            <span class="w-2.5 h-2.5 rounded-full border border-black/15 shrink-0" style="background-color: {{ $ticket->hairColor->hex_code }}"></span>
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
                                    onclick="openRescheduleModal(
                                        '{{ $ticket->id }}',
                                        '{{ $ticket->number }}',
                                        '{{ addslashes($ticket->customerProfile?->name ?? 'Pelanggan Walk-in') }}',
                                        '{{ $ticket->barber_membership_id ?? '' }}',
                                        '{{ addslashes($ticket->barber?->display_name ?? 'Otomatis') }}',
                                        '{{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}',
                                        '{{ ($ticket->priority_at ?? $ticket->created_at)->format('Y-m-d') }}'
                                    )"
                                    class="min-h-[34px] px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 text-xs font-extrabold cursor-pointer transition shadow-2xs inline-flex items-center gap-1.5"
                                    title="Reschedule jadwal / antrean ini"
                                >
                                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-950" />
                                    <span>Reschedule</span>
                                </button>
                                <button
                                    type="button"
                                    onclick="openCancelTicketModal(
                                        '{{ route('cashier.tickets.transition', $ticket) }}',
                                        '{{ $ticket->number }}',
                                        '{{ addslashes($ticket->customerProfile?->name ?? 'Pelanggan Walk-in') }}'
                                    )"
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
                                onclick="openRescheduleModal(
                                    '{{ $ticket->id }}',
                                    '{{ $ticket->number }}',
                                    '{{ addslashes($ticket->customerProfile?->name ?? 'Pelanggan Walk-in') }}',
                                    '{{ $ticket->barber_membership_id ?? '' }}',
                                    '{{ addslashes($ticket->barber?->display_name ?? 'Otomatis') }}',
                                    '{{ ($ticket->priority_at ?? $ticket->created_at)->format('H:i') }}',
                                    '{{ ($ticket->priority_at ?? $ticket->created_at)->format('Y-m-d') }}'
                                )"
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
                                <a
                                    href="{{ route('cashier.tickets.payment', $ticket) }}"
                                    class="w-full min-h-[38px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white text-xs font-black inline-flex items-center justify-center gap-2 shadow-sm transition cursor-pointer"
                                    title="Buka halaman baru untuk memproses pembayaran kasir"
                                >
                                    <x-ui.icon name="banknote" class="w-4 h-4 stroke-[2.5]" />
                                    <span>Bayar di Kasir</span>
                                </a>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-zinc-100 text-zinc-500 text-xs font-bold capitalize">
                                {{ $ticket->status->value }}
                            </span>
                        @endif
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
    @endif
    <!-- Mini Popup Modal Konfirmasi Pembayaran Kasir (Mencegah Human Error) -->
    <div id="payment-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200">
        <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200/80 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Icon & Judul -->
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="help-circle" class="w-6 h-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base text-zinc-950">Konfirmasi Pembayaran</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Cek mutasi atau uang fisik sebelum menerbitkan invoice resmi.</p>
                </div>
            </div>

            <!-- Box Rincian Tiket & Nominal -->
            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Tiket &amp; Pelanggan:</span>
                    <span class="font-extrabold text-zinc-900 truncate" id="modal-ticket-info">-</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Metode Pembayaran:</span>
                    <span class="px-2 py-0.5 rounded-md bg-amber-200 text-amber-950 font-black text-[10px] uppercase tracking-wider" id="modal-method-info">-</span>
                </div>
                <div class="border-t border-zinc-200/60 pt-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-zinc-600">Total Tagihan:</span>
                    <span class="text-base font-black text-zinc-950 num" id="modal-total-info">Rp0</span>
                </div>
            </div>

            <!-- Pertanyaan Konfirmasi -->
            <div class="p-2.5 rounded-xl bg-amber-50/80 border border-amber-200 text-center">
                <p class="text-xs text-amber-950 font-bold">
                    Apakah uang pembayaran sudah benar-benar masuk?
                </p>
            </div>

            <!-- Tombol Aksi Ya / Tidak -->
            <div class="grid grid-cols-2 gap-2.5 pt-1">
                <button
                    type="button"
                    onclick="closePaymentConfirmModal()"
                    class="min-h-[42px] px-4 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-extrabold text-xs transition cursor-pointer text-center"
                >
                    Belum (Batal)
                </button>
                <form id="modal-payment-form" method="POST" action="">
                    @csrf
                    <button
                        type="submit"
                        class="w-full min-h-[42px] px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition cursor-pointer text-center"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Ya, Sudah Masuk</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Reschedule Antrean / Jadwal Kasir -->
    <div id="reschedule-ticket-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200">
        <div class="relative w-full max-w-md bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200/80 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Icon & Judul -->
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="calendar" class="w-6 h-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base text-zinc-950">Reschedule Antrean &amp; Jadwal</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Atur ulang jadwal kedatangan pelanggan sesuai permintaan. Urutan antrean otomatis diperbarui.</p>
                </div>
            </div>

            <!-- Box Ringkasan Tiket -->
            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Nomor Antrean:</span>
                    <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-black text-xs num" id="reschedule-modal-number">-</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Pelanggan:</span>
                    <span class="font-extrabold text-zinc-900 truncate" id="reschedule-modal-customer">-</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Waktu Semula:</span>
                    <span class="font-mono font-bold text-zinc-700" id="reschedule-modal-time">-</span>
                </div>
            </div>

            <!-- Form Reschedule -->
            <form id="reschedule-ticket-form" method="POST" action="" class="space-y-3.5">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                            Tanggal Baru <span class="text-rose-500 font-bold ml-0.5">*</span>
                        </label>
                        <input
                            type="date"
                            name="date"
                            id="reschedule-input-date"
                            min="{{ now()->toDateString() }}"
                            required
                            class="w-full px-3 py-2 rounded-xl border border-zinc-200 text-xs font-semibold focus:outline-hidden focus:border-amber-500 focus:ring-1 focus:ring-amber-500 bg-white"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                            Jam Baru <span class="text-rose-500 font-bold ml-0.5">*</span>
                        </label>
                        <input
                            type="time"
                            name="time"
                            id="reschedule-input-time"
                            step="300"
                            required
                            class="w-full px-3 py-2 rounded-xl border border-zinc-200 text-xs font-semibold focus:outline-hidden focus:border-amber-500 focus:ring-1 focus:ring-amber-500 bg-white"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                        Pilihan Barber
                    </label>
                    <x-ui.select
                        name="barber_membership_id"
                        placeholder="Otomatis / Siapa Saja"
                        icon="scissors"
                        :options="$barberOptions"
                        search-placeholder="Cari nama barber..."
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                        Catatan / Alasan Reschedule (Opsional)
                    </label>
                    <input
                        type="text"
                        name="notes"
                        id="reschedule-input-notes"
                        placeholder="Contoh: Customer minta diundur jam 15:30"
                        maxlength="255"
                        class="w-full px-3 py-2 rounded-xl border border-zinc-200 text-xs placeholder:text-zinc-400 focus:outline-hidden focus:border-amber-500 focus:ring-1 focus:ring-amber-500 bg-white"
                    />
                </div>

                <!-- Tombol Aksi Batal / Simpan -->
                <div class="grid grid-cols-2 gap-2.5 pt-2">
                    <button
                        type="button"
                        onclick="closeRescheduleModal()"
                        class="min-h-[42px] px-4 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-extrabold text-xs transition cursor-pointer text-center"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="w-full min-h-[42px] px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition cursor-pointer text-center"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Reschedule</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popup Kecil Konfirmasi Batal Antrean -->
    <div id="cancel-ticket-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200">
        <div class="relative w-full max-w-sm bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200/80 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Icon & Judul -->
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-600 grid place-items-center shrink-0">
                    <x-ui.icon name="x" class="w-6 h-6 stroke-[2.5]" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base text-zinc-950">Batalkan Antrean?</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Antrean yang dibatalkan akan dihapus dari antrean aktif dan alasan akan disampaikan ke pelanggan.</p>
                </div>
            </div>

            <!-- Box Rincian Tiket -->
            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Nomor Antrean:</span>
                    <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-black text-xs num" id="cancel-modal-number">-</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500 font-medium">Pelanggan:</span>
                    <span class="font-extrabold text-zinc-900 truncate" id="cancel-modal-customer">-</span>
                </div>
            </div>

            <!-- Form Batal dengan Alasan -->
            <form id="cancel-ticket-form" method="POST" action="" class="space-y-3">
                @csrf
                <input type="hidden" name="status" value="cancelled" />

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                        Alasan Pembatalan (Diberitahukan ke Customer) <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <textarea
                        name="cancel_reason"
                        id="cancel-modal-reason"
                        rows="2"
                        required
                        placeholder="Contoh: Customer minta batalkan, berhalangan hadir..."
                        class="w-full px-3 py-2 rounded-xl border border-zinc-200 text-xs placeholder:text-zinc-400 focus:outline-hidden focus:border-rose-500 focus:ring-1 focus:ring-rose-500 bg-white resize-none"
                    ></textarea>

                    <!-- Pilihan Cepat Alasan -->
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <button
                            type="button"
                            onclick="setQuickCancelReason('Permintaan Pelanggan')"
                            class="px-2 py-1 rounded-lg bg-zinc-100 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 text-zinc-600 text-[10px] font-semibold border border-zinc-200 transition cursor-pointer"
                        >
                            + Permintaan Pelanggan
                        </button>
                        <button
                            type="button"
                            onclick="setQuickCancelReason('Pelanggan Tidak Hadir (No Show)')"
                            class="px-2 py-1 rounded-lg bg-zinc-100 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 text-zinc-600 text-[10px] font-semibold border border-zinc-200 transition cursor-pointer"
                        >
                            + No Show
                        </button>
                        <button
                            type="button"
                            onclick="setQuickCancelReason('Kendala Operasional / Barber Berhalangan')"
                            class="px-2 py-1 rounded-lg bg-zinc-100 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 text-zinc-600 text-[10px] font-semibold border border-zinc-200 transition cursor-pointer"
                        >
                            + Kendala Operasional
                        </button>
                    </div>
                </div>

                <!-- Tombol Aksi Batal / Ya -->
                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    <button
                        type="button"
                        onclick="closeCancelTicketModal()"
                        class="min-h-[42px] px-4 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-extrabold text-xs transition cursor-pointer text-center"
                    >
                        Kembali
                    </button>
                    <button
                        type="submit"
                        class="w-full min-h-[42px] px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-95 text-white font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-md shadow-rose-600/20 transition cursor-pointer text-center"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" />
                        <span>Ya, Batalkan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setSelectValue(container, targetVal) {
            if (!container) return;
            const targetValStr = String(targetVal ?? '');
            const opt = container.querySelector(`[data-select-option][data-value="${targetValStr}"]`)
                     || container.querySelector('[data-select-option][data-value=""]')
                     || container.querySelector('[data-select-option]');
            if (!opt) return;

            const val = opt.getAttribute('data-value') ?? '';
            const label = opt.getAttribute('data-label') ?? '';

            const inputsContainer = container.querySelector('[data-select-inputs]');
            const inputName = container.getAttribute('data-input-name') || '';
            const labelEl = container.querySelector('[data-select-label]');

            if (inputsContainer && inputName) {
                inputsContainer.innerHTML = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = val;
                input.setAttribute('data-select-input', '');
                inputsContainer.appendChild(input);
            }
            if (labelEl) {
                labelEl.textContent = label;
            }

            container.querySelectorAll('[data-select-option]').forEach(btn => {
                const isSel = btn === opt;
                btn.setAttribute('aria-selected', isSel ? 'true' : 'false');
                btn.classList.toggle('bg-amber-500/15', isSel);
                btn.classList.toggle('text-zinc-950', isSel);
                btn.classList.toggle('font-bold', isSel);
                btn.classList.toggle('text-zinc-700', !isSel);
                btn.classList.toggle('hover:bg-zinc-100', !isSel);
                btn.classList.toggle('font-medium', !isSel);
                const check = btn.querySelector('[data-select-check]');
                if (check) check.classList.toggle('hidden', !isSel);
            });
        }

        function openRescheduleModal(ticketId, ticketNumber, customerName, currentBarberId, currentBarberName, currentTime, currentDate) {
            const modal = document.getElementById('reschedule-ticket-modal');
            const form = document.getElementById('reschedule-ticket-form');
            if (!modal || !form) return;

            form.action = `/cashier/tickets/${ticketId}/reschedule`;
            document.getElementById('reschedule-modal-number').textContent = ticketNumber;
            document.getElementById('reschedule-modal-customer').textContent = customerName;
            document.getElementById('reschedule-modal-time').textContent = currentTime + ' WIB (' + currentDate + ')';

            document.getElementById('reschedule-input-date').value = currentDate;
            document.getElementById('reschedule-input-time').value = currentTime;
            document.getElementById('reschedule-input-notes').value = '';

            const barberContainer = form.querySelector('[data-ui-select][data-input-name="barber_membership_id"]');
            if (barberContainer) {
                setSelectValue(barberContainer, currentBarberId ? String(currentBarberId) : '');
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRescheduleModal() {
            const modal = document.getElementById('reschedule-ticket-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.getElementById('reschedule-ticket-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeRescheduleModal();
            }
        });

        document.getElementById('reschedule-ticket-form')?.addEventListener('submit', function() {
            document.body.style.overflow = '';
        });

        function setQuickCancelReason(text) {
            const reasonInput = document.getElementById('cancel-modal-reason');
            if (reasonInput) {
                reasonInput.value = text;
                reasonInput.focus();
            }
        }

        function openCancelTicketModal(actionUrl, ticketNumber, customerName) {
            const modal = document.getElementById('cancel-ticket-modal');
            const form = document.getElementById('cancel-ticket-form');
            if (!modal || !form) return;

            form.action = actionUrl;
            document.getElementById('cancel-modal-number').textContent = ticketNumber;
            document.getElementById('cancel-modal-customer').textContent = customerName;

            const reasonInput = document.getElementById('cancel-modal-reason');
            if (reasonInput) {
                reasonInput.value = '';
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeCancelTicketModal() {
            const modal = document.getElementById('cancel-ticket-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.getElementById('cancel-ticket-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelTicketModal();
            }
        });

        document.getElementById('cancel-ticket-form')?.addEventListener('submit', function() {
            document.body.style.overflow = '';
        });

        function openPaymentConfirmModal(actionUrl, ticketNumber, customerName, method, total) {
            const modal = document.getElementById('payment-confirm-modal');
            const form = document.getElementById('modal-payment-form');
            const ticketInfo = document.getElementById('modal-ticket-info');
            const methodInfo = document.getElementById('modal-method-info');
            const totalInfo = document.getElementById('modal-total-info');

            if (!modal || !form) return;

            form.action = actionUrl;
            if (ticketInfo) ticketInfo.textContent = `${ticketNumber} · ${customerName}`;
            if (methodInfo) methodInfo.textContent = method;
            if (totalInfo) totalInfo.textContent = total;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePaymentConfirmModal() {
            const modal = document.getElementById('payment-confirm-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.getElementById('payment-confirm-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentConfirmModal();
            }
        });

        document.getElementById('modal-payment-form')?.addEventListener('submit', function() {
            document.body.style.overflow = '';
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePaymentConfirmModal();
                closeRescheduleModal();
                closeCancelTicketModal();
            }
        });
    </script>
</x-layouts.app>