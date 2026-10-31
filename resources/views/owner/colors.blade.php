<x-layouts.app title="Stok Warna Coloring" subtitle="Katalog warna rambut dan batas minimum stok" role="owner">
    @php
        $editingColorId = request()->filled('edit') ? request()->integer('edit') : null;
        $editingColor = $editingColorId ? $colors->firstWhere('id', $editingColorId) : null;

        $statusOptions = [
            ['value' => '1', 'label' => 'Tersedia (Aktif)'],
            ['value' => '0', 'label' => 'Disembunyikan (Nonaktif)'],
        ];

        $stockFilterOptions = [
            ['value' => 'low', 'label' => 'Mendekati / Di Bawah Minimum'],
            ['value' => 'safe', 'label' => 'Stok Aman'],
            ['value' => 'inactive', 'label' => 'Nonaktif'],
        ];

        $presetColors = [
            ['name' => 'Ash Grey', 'hex' => '#9CA3AF'],
            ['name' => 'Platinum Blonde', 'hex' => '#FDE68A'],
            ['name' => 'Dark Brown', 'hex' => '#451A03'],
            ['name' => 'Burgundy Wine', 'hex' => '#881337'],
            ['name' => 'Blue Black', 'hex' => '#1E293B'],
            ['name' => 'Caramel Chestnut', 'hex' => '#B45309'],
        ];

        $filterStock = request()->filled('stock_status') ? (string) request('stock_status') : null;
        $lowStockCount = $colors->filter(fn ($c) => $c->isNearOrBelowMinStock())->count();

        $filteredColors = $colors
            ->when($filterStock === 'low', fn ($c) => $c->filter(fn ($item) => $item->isNearOrBelowMinStock()))
            ->when($filterStock === 'safe', fn ($c) => $c->filter(fn ($item) => $item->is_active && ! $item->isNearOrBelowMinStock()))
            ->when($filterStock === 'inactive', fn ($c) => $c->where('is_active', false))
            ->values();

        $perPage = 10;
        $totalItems = $filteredColors->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($totalPages, max(1, request()->integer('page', 1)));
        $paginatedColors = $filteredColors->forPage($currentPage, $perPage);
    @endphp

    <!-- Navigasi Sub-Halaman Layanan & Warna -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="inline-flex items-center gap-1.5 bg-white border border-zinc-200/80 p-1.5 rounded-2xl shadow-2xs">
            <a
                href="{{ route('owner.services') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-extrabold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition"
            >
                <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                <span>Daftar Layanan &amp; Harga</span>
            </a>
            <a
                href="{{ route('owner.colors') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-extrabold bg-zinc-950 text-amber-400 shadow-2xs transition"
            >
                <x-ui.icon name="sparkles" class="w-3.5 h-3.5" />
                <span>Setting Warna &amp; Stok Coloring</span>
                @if($lowStockCount > 0)
                    <span class="relative flex h-2.5 w-2.5 shrink-0" title="{{ $lowStockCount }} warna mendekati minimum stok">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                    </span>
                @endif
            </a>
        </div>

        @if($lowStockCount > 0)
            <div class="inline-flex items-center gap-2 text-amber-700 text-xs font-bold">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                </span>
                <span>{{ $lowStockCount }} warna mendekati atau berada di bawah batas minimum stok</span>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <!-- Form Tambah / Edit Warna Coloring -->
        <x-ui.card class="{{ $editingColor ? 'ring-2 ring-amber-500/40 border-amber-500' : '' }}">
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-bold flex items-center gap-1.5">
                        <x-ui.icon :name="$editingColor ? 'edit' : 'plus'" class="w-4 h-4 text-amber-600" />
                        <span>{{ $editingColor ? 'Edit Warna Coloring' : 'Tambah Warna Coloring' }}</span>
                    </h2>
                    @if($editingColor)
                        <span class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-700 text-[11px] font-extrabold">
                            Mode Edit
                        </span>
                    @endif
                </div>
            </x-slot:header>

            <form
                method="POST"
                action="{{ $editingColor ? route('owner.colors.update', $editingColor) : route('owner.colors.store') }}"
                class="space-y-3"
            >
                @csrf
                @if($editingColor)
                    @method('PUT')
                @endif

                <x-ui.input
                    label="Nama Warna Coloring *"
                    name="name"
                    :value="$editingColor?->name"
                    placeholder="Ash Grey / Platinum Blonde"
                    required
                />

                @php
                    $currentHex = strtoupper((string) old('hex_code', $editingColor?->hex_code ?? '#9CA3AF'));
                    $presetHexList = array_map(fn ($p) => strtoupper($p['hex']), $presetColors);
                    $isCustomHex = ! in_array($currentHex, $presetHexList, true);
                @endphp
                <div class="space-y-2.5" data-color-swatch-Root>
                    <label class="block text-xs font-bold text-zinc-700">
                        Pilih Warna (Preset atau Tambah Warna Sendiri) <span class="text-rose-500 font-bold">*</span>
                    </label>

                    <!-- Pilihan Preset + Tombol (+) Tambah Warna Sendiri -->
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($presetColors as $preset)
                            @php
                                $isPresetSelected = strtoupper($preset['hex']) === $currentHex;
                            @endphp
                            <button
                                type="button"
                                data-preset-swatch
                                data-hex="{{ $preset['hex'] }}"
                                data-name="{{ $preset['name'] }}"
                                class="w-8 h-8 rounded-full border shadow-2xs cursor-pointer hover:scale-105 transition {{ $isPresetSelected ? 'ring-2 ring-amber-500 ring-offset-2 border-zinc-900' : 'border-zinc-300' }}"
                                style="background-color: {{ $preset['hex'] }}"
                                title="Pilih {{ $preset['name'] }} ({{ $preset['hex'] }})"
                            ></button>
                        @endforeach

                        <!-- Tombol (+) untuk Menambah Warna Sendiri (Roda Palet / Kode Hex) -->
                        <button
                            type="button"
                            data-custom-color-toggle
                            class="w-8 h-8 rounded-full border border-dashed border-zinc-400 hover:border-amber-500 text-zinc-700 hover:text-amber-600 grid place-items-center cursor-pointer transition {{ $isCustomHex ? 'ring-2 ring-amber-500 ring-offset-2 border-solid border-amber-500 text-amber-600' : '' }}"
                            title="Tambah warna sendiri dengan Roda Palet atau Kode Hex"
                        >
                            <x-ui.icon name="plus" class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Panel Roda Palet & Input Kode Hex (Muncul saat klik tombol + atau saat mengedit warna kustom) -->
                    <div
                        data-custom-color-panel
                        class="{{ $isCustomHex ? '' : 'hidden' }} pt-2 border-t border-zinc-100 space-y-2"
                    >
                        <p class="text-[11px] font-semibold text-zinc-500">
                            Pilih dari roda palet warna atau ketik kode Hex secara langsung:
                        </p>
                        <div class="flex items-center gap-2.5">
                            <label class="relative flex items-center gap-2 px-2.5 py-1.5 rounded-xl border border-zinc-200 hover:border-zinc-300 cursor-pointer transition shrink-0">
                                <input
                                    type="color"
                                    data-color-wheel
                                    value="{{ $currentHex }}"
                                    class="h-7 w-8 rounded-lg border-0 bg-transparent p-0 cursor-pointer"
                                />
                                <span class="text-xs font-bold text-zinc-700">Roda Palet</span>
                            </label>

                            <div class="relative flex-1">
                                <input
                                    type="text"
                                    name="hex_code"
                                    data-color-hex-input
                                    value="{{ $currentHex }}"
                                    placeholder="#9CA3AF"
                                    maxlength="7"
                                    required
                                    class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 num"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input
                        label="Stok Saat Ini (Tube) *"
                        name="stock"
                        type="number"
                        min="0"
                        :value="$editingColor ? (string) $editingColor->stock : '10'"
                        required
                    />

                    <x-ui.input
                        label="Minimum Stok *"
                        name="min_stock"
                        type="number"
                        min="0"
                        :value="$editingColor ? (string) $editingColor->min_stock : '5'"
                        required
                    />
                </div>
                <p class="text-[11px] text-zinc-400 -mt-1">
                    Indikator peringatan akan menyala otomatis jika stok mendekati (&le; Min + 2) atau berada di bawah Minimum Stok.
                </p>

                <x-ui.select
                    label="Status Ketersediaan *"
                    name="is_active"
                    icon="check"
                    :options="$statusOptions"
                    :value="$editingColor ? ($editingColor->is_active ? '1' : '0') : '1'"
                />

                @if($editingColor)
                    <div class="pt-2 flex items-center gap-2">
                        <x-ui.button :href="route('owner.colors')" variant="outline" class="flex-1">
                            <span>Batal</span>
                        </x-ui.button>
                        <x-ui.button type="submit" class="flex-1">
                            <x-ui.icon name="check" class="w-4 h-4" />
                            <span>Simpan</span>
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.button type="submit" class="w-full">
                        <x-ui.icon name="plus" class="w-4 h-4" />
                        <span>Tambah Warna Coloring</span>
                    </x-ui.button>
                @endif
            </form>
        </x-ui.card>

        <!-- Daftar Stok Warna Coloring -->
        <x-ui.card class="lg:col-span-2">
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-2 w-full">
                    <div>
                        <h2 class="font-bold">Daftar Warna &amp; Monitoring Stok</h2>
                        <span class="text-xs text-zinc-400">Stok hanya ditampilkan untuk Owner &amp; Kasir (disembunyikan dari halaman Customer)</span>
                    </div>
                    <form method="GET" action="{{ route('owner.colors') }}" class="flex items-center gap-2">
                        <x-ui.select
                            name="stock_status"
                            size="sm"
                            align="right"
                            placeholder="Semua Status Stok"
                            icon="filter"
                            :options="$stockFilterOptions"
                            :value="request('stock_status')"
                            class="w-52"
                            auto-submit
                        />
                    </form>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($paginatedColors as $color)
                    @php
                        $isBeingEdited = $editingColor && $editingColor->id === $color->id;
                        $editUrl = route('owner.colors', array_filter([
                            'edit' => $color->id,
                            'stock_status' => request('stock_status'),
                            'page' => $currentPage > 1 ? $currentPage : null,
                        ]));

                        if (! $color->is_active) {
                            $statusTextClass = 'text-zinc-400';
                            $badgeText = 'Nonaktif';
                        } elseif ($color->stock <= 0) {
                            $statusTextClass = 'text-rose-600';
                            $badgeText = 'Stok Habis';
                        } elseif ($color->stock <= $color->min_stock) {
                            $statusTextClass = 'text-rose-600';
                            $badgeText = 'Di Bawah Minimum';
                        } elseif ($color->isNearOrBelowMinStock()) {
                            $statusTextClass = 'text-amber-600';
                            $badgeText = 'Mendekati Minimum';
                        } else {
                            $statusTextClass = 'text-emerald-600';
                            $badgeText = 'Stok Aman';
                        }
                    @endphp
                    <div class="py-3.5 px-2 -mx-2 rounded-xl flex flex-wrap items-center justify-between gap-3 transition hover:bg-zinc-50/50">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span
                                class="w-9 h-9 rounded-xl border border-zinc-300 shadow-2xs shrink-0"
                                style="background-color: {{ $color->hex_code }}"
                            ></span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-extrabold text-sm text-zinc-900">{{ $color->name }}</p>
                                    <span class="text-xs text-zinc-300">·</span>
                                    <span class="text-xs font-extrabold {{ $statusTextClass }}">
                                        {{ $badgeText }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-500 mt-0.5 num">
                                    Stok Tersedia: <b class="{{ $color->isNearOrBelowMinStock() ? 'text-rose-600 font-extrabold' : 'text-zinc-900' }}">{{ $color->stock }} tube</b>
                                    · Minimum Stok: <b class="text-zinc-700">{{ $color->min_stock }} tube</b>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-ui.button :href="$editUrl" size="sm" :variant="$isBeingEdited ? 'primary' : 'outline'">
                                <x-ui.icon name="edit" class="w-3.5 h-3.5" />
                                <span>Edit</span>
                            </x-ui.button>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Belum ada data warna coloring.</p>
                @endforelse
            </div>

            @if($totalPages > 1)
                @php
                    $prevUrl = $currentPage > 1
                        ? route('owner.colors', array_filter([
                            'stock_status' => request('stock_status'),
                            'edit' => request('edit'),
                            'page' => $currentPage - 1 > 1 ? $currentPage - 1 : null,
                        ]))
                        : null;

                    $nextUrl = $currentPage < $totalPages
                        ? route('owner.colors', array_filter([
                            'stock_status' => request('stock_status'),
                            'edit' => request('edit'),
                            'page' => $currentPage + 1,
                        ]))
                        : null;
                @endphp
                <div class="mt-4 pt-3 border-t border-zinc-100 flex items-center justify-between gap-2">
                    @if($prevUrl)
                        <a
                            href="{{ $prevUrl }}"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 text-[11px] font-extrabold transition"
                        >
                            <x-ui.icon name="arrow-left" class="w-3 h-3" />
                            <span>Prev</span>
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-700 opacity-40 pointer-events-none text-[11px] font-extrabold">
                            <x-ui.icon name="arrow-left" class="w-3 h-3" />
                            <span>Prev</span>
                        </span>
                    @endif

                    <span class="text-[11px] font-extrabold text-zinc-600 num">
                        Hal {{ $currentPage }} / {{ $totalPages }}
                    </span>

                    @if($nextUrl)
                        <a
                            href="{{ $nextUrl }}"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 text-[11px] font-extrabold transition"
                        >
                            <span>Next</span>
                            <x-ui.icon name="arrow-right" class="w-3 h-3" />
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-700 opacity-40 pointer-events-none text-[11px] font-extrabold">
                            <span>Next</span>
                            <x-ui.icon name="arrow-right" class="w-3 h-3" />
                        </span>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-color-swatch-Root]');
            if (!root) return;

            const form = root.closest('form');
            const nameInput = form ? form.querySelector('input[name="name"]') : null;
            const hexInput = root.querySelector('[data-color-hex-input]');
            const wheelInput = root.querySelector('[data-color-wheel]');
            const customPanel = root.querySelector('[data-custom-color-panel]');
            const customToggle = root.querySelector('[data-custom-color-toggle]');
            const presetBtns = Array.from(root.querySelectorAll('[data-preset-swatch]'));

            function highlightSwatch(activeHex, isCustomMode = false) {
                const upper = (activeHex || '').toUpperCase();
                let matchedPreset = false;

                presetBtns.forEach((btn) => {
                    const isMatch = !isCustomMode && (btn.getAttribute('data-hex') || '').toUpperCase() === upper;
                    if (isMatch) matchedPreset = true;
                    btn.classList.toggle('ring-2', isMatch);
                    btn.classList.toggle('ring-amber-500', isMatch);
                    btn.classList.toggle('ring-offset-2', isMatch);
                    btn.classList.toggle('border-zinc-900', isMatch);
                    btn.classList.toggle('border-zinc-300', !isMatch);
                });

                const customActive = isCustomMode || !matchedPreset;
                if (customToggle) {
                    customToggle.classList.toggle('ring-2', customActive);
                    customToggle.classList.toggle('ring-amber-500', customActive);
                    customToggle.classList.toggle('ring-offset-2', customActive);
                    customToggle.classList.toggle('border-solid', customActive);
                    customToggle.classList.toggle('border-amber-500', customActive);
                    customToggle.classList.toggle('text-amber-600', customActive);
                }
            }

            presetBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const hex = (btn.getAttribute('data-hex') || '#9CA3AF').toUpperCase();
                    const presetName = btn.getAttribute('data-name') || '';

                    if (hexInput) hexInput.value = hex;
                    if (wheelInput) wheelInput.value = hex;
                    if (customPanel) customPanel.classList.add('hidden');
                    if (nameInput && !nameInput.value.trim() && presetName) {
                        nameInput.value = presetName;
                    }

                    highlightSwatch(hex, false);
                });
            });

            if (customToggle && customPanel) {
                customToggle.addEventListener('click', () => {
                    const isHidden = customPanel.classList.contains('hidden');
                    customPanel.classList.toggle('hidden', !isHidden);
                    if (isHidden) {
                        highlightSwatch(hexInput ? hexInput.value : '', true);
                        if (hexInput) hexInput.focus();
                    }
                });
            }

            if (wheelInput && hexInput) {
                wheelInput.addEventListener('input', () => {
                    const hex = wheelInput.value.toUpperCase();
                    hexInput.value = hex;
                    highlightSwatch(hex, true);
                });

                hexInput.addEventListener('input', () => {
                    let val = hexInput.value.trim().toUpperCase();
                    if (val && !val.startsWith('#')) {
                        val = '#' + val;
                        hexInput.value = val;
                    }
                    if (/^#[0-9A-F]{6}$/.test(val)) {
                        wheelInput.value = val;
                        highlightSwatch(val, true);
                    }
                });
            }
        })();
    </script>
</x-layouts.app>
