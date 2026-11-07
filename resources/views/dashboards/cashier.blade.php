@php
$currentTab = $activeTab ?? 'board';

$pageMeta = match ($currentTab) {
'walkin' => [
'title' => 'Input Antrean Walk-in Baru',
'subtitle' => 'Buat nomor antrean untuk pelanggan yang datang langsung ke outlet',
],
'bookings' => [
'title' => 'Daftar Booking',
'subtitle' => 'Kelola booking hari ini, tindak lanjuti booking terlewat, dan pantau reservasi mendatang',
],
default => [
'title' => 'Papan Antrean & POS',
'subtitle' => 'Monitor antrean aktif dan proses pembayaran kasir',
],
};

$currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
$knownPending = isset($allBookings)
    ? $allBookings->contains(fn ($b) => $b->scheduled_at >= now()->startOfDay())
    : (isset($pendingBookings) ? $pendingBookings->isNotEmpty() : null);
$hasWalkinBookingDot = $currentTenantCtx->hasWalkinBookingDot($knownPending);

$effectiveRole = $currentTenantCtx->role()
    ?? request()->attributes->get('current_role')
    ?? 'cashier';
$isCashierOrAdmin = in_array($effectiveRole, ['admin', 'cashier'], true) && $effectiveRole !== 'owner';

$mobileTabs = [
'board' => ['label' => 'Papan POS', 'href' => route('cashier.dashboard'), 'icon' => 'ticket', 'dot' => false],
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
'sublabel' => $s->category?->name,
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
    <div class="grid grid-cols-2 gap-1 mb-4 bg-zinc-100/90 border border-zinc-200/80 p-1 rounded-2xl shadow-2xs md:hidden">
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
            class="relative flex items-center justify-center gap-1.5 py-2 px-1 rounded-xl text-xs font-bold text-center transition-all duration-150 select-none min-w-0 active:scale-95 {{ $isTabActive ? 'bg-zinc-950 text-white shadow-xs font-black ring-1 ring-zinc-900/10' : 'text-zinc-600 hover:text-zinc-950 hover:bg-white/80 active:bg-zinc-200/50' }}">
            <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0 {{ $isTabActive ? 'text-amber-400' : 'text-zinc-400' }}" />
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
                    @if(isset($branches) && $branches->count() === 1)
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1">Cabang</label>
                            <div class="h-10 px-3.5 rounded-xl bg-zinc-100/90 border border-zinc-200/90 flex items-center justify-between text-xs font-bold text-zinc-800 shadow-2xs select-none">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-ui.icon name="store" class="w-4 h-4 text-amber-600 shrink-0" />
                                    <span class="truncate">{{ $branches->first()->name }}</span>
                                </div>
                                <span class="px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-800 text-[10px] font-black shrink-0 uppercase tracking-wider">
                                    {{ $branches->first()->code }}
                                </span>
                            </div>
                            <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}" />
                        </div>
                    @else
                        <x-ui.select
                            label="Cabang"
                            name="branch_id"
                            icon="store"
                            :options="$branchOptions"
                            search-placeholder="Cari cabang..." />
                    @endif

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
                                    @style(['background-color: ' . ($firstColor?->hex_code ?? '#B45309')])></span>
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
                    <x-ui.button :href="route('cashier.dashboard')" variant="outline" size="md" class="min-w-[140px]">
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

            <!-- Segmented Filter Tabs: Rounded Island Capsule -->
            <div class="p-3 sm:p-4 pb-1">
                <div class="p-1 rounded-2xl bg-zinc-100/90 border border-zinc-200/80 grid grid-cols-2 lg:grid-cols-4 gap-1 shadow-2xs">
                    <!-- Tab: Hari Ini -->
                    <button
                        type="button"
                        @click="activeGroup = 'today'"
                        :class="activeGroup === 'today' ? 'bg-white text-zinc-950 shadow-sm font-extrabold border-zinc-200/80' : 'text-zinc-600 hover:text-zinc-900 font-semibold border-transparent hover:bg-white/50'"
                        class="w-full flex items-center justify-between gap-1.5 py-2.5 px-3 rounded-xl text-xs border transition-all duration-150 cursor-pointer select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 shrink-0 text-amber-500" />
                            <span class="truncate">Hari Ini</span>
                        </span>
                        <span
                            :class="activeGroup === 'today' ? 'bg-amber-500/20 text-amber-900 border border-amber-500/30' : 'bg-zinc-200/80 text-zinc-600'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0 transition">
                            {{ $todayCount }}
                        </span>
                    </button>

                    <!-- Tab: Sudah Lewat (Perlu Ditindak) -->
                    <button
                        type="button"
                        @click="activeGroup = 'past'"
                        :class="activeGroup === 'past' ? 'bg-white text-zinc-950 shadow-sm font-extrabold border-zinc-200/80' : 'text-zinc-600 hover:text-zinc-900 font-semibold border-transparent hover:bg-white/50'"
                        class="w-full flex items-center justify-between gap-1.5 py-2.5 px-3 rounded-xl text-xs border transition-all duration-150 cursor-pointer select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="clock" class="w-3.5 h-3.5 shrink-0 text-rose-500" />
                            <span class="truncate">Sudah Lewat</span>
                        </span>
                        <span
                            :class="activeGroup === 'past' ? 'bg-rose-100 text-rose-800 border border-rose-300' : ({{ $pastCount > 0 ? 'true' : 'false' }} ? 'bg-rose-100 text-rose-700 font-black' : 'bg-zinc-200/80 text-zinc-600')"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0 transition">
                            {{ $pastCount }}
                        </span>
                    </button>

                    <!-- Tab: Hari Lain / Mendatang -->
                    <button
                        type="button"
                        @click="activeGroup = 'future'"
                        :class="activeGroup === 'future' ? 'bg-white text-zinc-950 shadow-sm font-extrabold border-zinc-200/80' : 'text-zinc-600 hover:text-zinc-900 font-semibold border-transparent hover:bg-white/50'"
                        class="w-full flex items-center justify-between gap-1.5 py-2.5 px-3 rounded-xl text-xs border transition-all duration-150 cursor-pointer select-none">
                        <span class="flex items-center gap-1.5 truncate">
                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 shrink-0 text-sky-500" />
                            <span class="truncate">Mendatang</span>
                        </span>
                        <span
                            :class="activeGroup === 'future' ? 'bg-sky-100 text-sky-800 border border-sky-300' : 'bg-zinc-200/80 text-zinc-600'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0 transition">
                            {{ $futureCount }}
                        </span>
                    </button>

                    <!-- Tab: Semua Kategori -->
                    <button
                        type="button"
                        @click="activeGroup = 'all'"
                        :class="activeGroup === 'all' ? 'bg-white text-zinc-950 shadow-sm font-extrabold border-zinc-200/80' : 'text-zinc-600 hover:text-zinc-900 font-semibold border-transparent hover:bg-white/50'"
                        class="w-full flex items-center justify-between gap-1.5 py-2.5 px-3 rounded-xl text-xs border transition-all duration-150 cursor-pointer select-none">
                        <span class="truncate">Semua Kategori</span>
                        <span
                            :class="activeGroup === 'all' ? 'bg-zinc-900 text-white' : 'bg-zinc-200/80 text-zinc-600'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold num shrink-0 transition">
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
    <!-- HALAMAN 1 (Default): Papan Antrean & POS (Livewire Component dengan polling otomatis 5 detik) -->
    <livewire:cashier-board
        :branch-id="$filterBranchId"
        :barber-id="$filterBarberId"
        :date-filter="$filterDateType"
        :custom-date="request('custom_date')"
        :status-filter="request('status')"
    />
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

    <!-- Modal Konfirmasi Proses Refund Kasir -->
    <div id="refund-ticket-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200">
        <div class="relative w-full max-w-md bg-white rounded-3xl p-5 sm:p-6 shadow-2xl border border-zinc-200/80 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Icon & Judul -->
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="wallet" class="w-6 h-6 stroke-[2]" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base text-zinc-950">Konfirmasi Penyelesaian Refund</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Pastikan Anda telah mentransfer dana ke rekening pelanggan sesuai nominal yang tertera.</p>
                </div>
            </div>

            <!-- Box Rincian Refund -->
            <div class="p-3.5 rounded-2xl bg-amber-50/50 border border-amber-200/70 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 font-medium">Nomor Tiket:</span>
                    <span class="px-2 py-0.5 rounded-md bg-zinc-950 text-amber-400 font-black num" id="refund-modal-number">-</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 font-medium">Pelanggan:</span>
                    <span class="font-extrabold text-zinc-900 truncate" id="refund-modal-customer">-</span>
                </div>
                <div class="flex items-center justify-between pt-1 border-t border-amber-200/60">
                    <span class="text-zinc-700 font-bold">Nominal Refund:</span>
                    <span class="font-black text-amber-700 text-sm num" id="refund-modal-amount">Rp0</span>
                </div>
                <div class="pt-2 border-t border-amber-200/60 space-y-1 bg-white/70 p-2.5 rounded-xl border border-amber-100">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Bank / E-Wallet:</span>
                        <span class="font-extrabold text-zinc-900" id="refund-modal-bank">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Nomor Rekening:</span>
                        <span class="font-extrabold text-zinc-900 num font-mono" id="refund-modal-account">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Atas Nama:</span>
                        <span class="font-extrabold text-zinc-900" id="refund-modal-name">-</span>
                    </div>
                </div>
            </div>

            <!-- Form Proses Refund -->
            <form id="refund-ticket-form" method="POST" action="" class="space-y-3">
                @csrf
                <div>
                    <x-ui.select
                        label="Metode Pengembalian Dana *"
                        name="method"
                        icon="credit-card"
                        value="transfer"
                        :options="[
                            ['value' => 'transfer', 'label' => 'Transfer Bank', 'sublabel' => 'Kirim ke rekening pelanggan'],
                            ['value' => 'ewallet', 'label' => 'E-Wallet (GoPay/OVO/DANA/ShopeePay)', 'sublabel' => 'Kirim ke akun e-wallet'],
                            ['value' => 'cash', 'label' => 'Tunai (Di Outlet)', 'sublabel' => 'Diserahkan langsung di outlet'],
                            ['value' => 'qris', 'label' => 'QRIS Refund', 'sublabel' => 'Pengembalian via merchant QRIS'],
                        ]"
                    />
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                        Catatan Kasir / No. Referensi Transfer (Opsional)
                    </label>
                    <input
                        type="text"
                        name="notes"
                        id="refund-modal-notes"
                        placeholder="Contoh: Berhasil ditransfer via BCA, Ref: 981240"
                        class="w-full px-3 py-2 rounded-xl border border-zinc-200 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:border-amber-500 bg-white"
                    />
                </div>

                <!-- Tombol Aksi -->
                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    <button
                        type="button"
                        onclick="closeRefundTicketModal()"
                        class="min-h-[42px] px-4 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-extrabold text-xs transition cursor-pointer text-center"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="w-full min-h-[42px] px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition cursor-pointer text-center"
                    >
                        <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
                        <span>Selesaikan Refund</span>
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

        function openRefundTicketModal(actionUrl, ticketNumber, customerName, refundAmount, bankName, accountNumber, accountName) {
            const modal = document.getElementById('refund-ticket-modal');
            const form = document.getElementById('refund-ticket-form');
            if (!modal || !form) return;

            form.action = actionUrl;
            document.getElementById('refund-modal-number').textContent = ticketNumber;
            document.getElementById('refund-modal-customer').textContent = customerName;
            document.getElementById('refund-modal-amount').textContent = refundAmount;
            document.getElementById('refund-modal-bank').textContent = bankName || '-';
            document.getElementById('refund-modal-account').textContent = accountNumber || '-';
            document.getElementById('refund-modal-name').textContent = accountName || '-';

            const notesInput = document.getElementById('refund-modal-notes');
            if (notesInput) notesInput.value = '';

            const methodSelect = form.querySelector('[data-ui-select][data-input-name="method"]');
            if (methodSelect && typeof setSelectValue === 'function') {
                setSelectValue(methodSelect, 'transfer');
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRefundTicketModal() {
            const modal = document.getElementById('refund-ticket-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.getElementById('refund-ticket-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeRefundTicketModal();
            }
        });

        document.getElementById('refund-ticket-form')?.addEventListener('submit', function() {
            document.body.style.overflow = '';
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePaymentConfirmModal();
                closeRescheduleModal();
                closeCancelTicketModal();
                closeRefundTicketModal();
            }
        });
    </script>
</x-layouts.app>