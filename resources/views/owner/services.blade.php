<x-layouts.app title="Layanan & Harga" subtitle="Atur jasa cukur, perawatan, dan produk beserta durasi" role="owner">
    @php
        $categoryOptions = $categories->map(fn ($c) => [
            'value' => (string) $c->id,
            'label' => $c->name,
        ])->all();

        $typeOptions = [
            ['value' => 'service', 'label' => 'Layanan Jasa'],
            ['value' => 'product', 'label' => 'Produk Fisik'],
        ];

        $filterCategoryId = request()->filled('category_id') ? request()->integer('category_id') : null;
        $filterType = request()->filled('type') ? (string) request('type') : null;
        $editingServiceId = request()->filled('edit') ? request()->integer('edit') : null;
        $editingService = $editingServiceId ? $services->firstWhere('id', $editingServiceId) : null;

        $filteredServices = $services
            ->when($filterCategoryId, fn ($c) => $c->where('service_category_id', $filterCategoryId))
            ->when($filterType, fn ($c) => $c->where('type', $filterType))
            ->values();

        $perPage = 10;
        $totalItems = $filteredServices->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($totalPages, max(1, request()->integer('page', 1)));
        $paginatedServices = $filteredServices->forPage($currentPage, $perPage);

        $hasLowColorStockDot = app(\App\Support\Tenancy\CurrentTenant::class)->hasLowColorStockDot();
    @endphp

    <!-- Navigasi Sub-Halaman Layanan & Warna (Khusus Mobile, Disembunyikan di Tablet & Desktop) -->
    <div class="md:hidden mb-4">
        <div class="grid grid-cols-2 p-1 bg-white border border-zinc-200/80 rounded-2xl shadow-2xs gap-1">
            <a
                href="{{ route('owner.services') }}"
                class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-black bg-amber-500 text-zinc-950 shadow-2xs transition"
            >
                <x-ui.icon name="scissors" class="w-3.5 h-3.5" />
                <span>Layanan</span>
            </a>
            <a
                href="{{ route('owner.colors') }}"
                class="relative flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition"
            >
                <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-amber-600" />
                <span>Stok Warna</span>
                @if($hasLowColorStockDot)
                    <span class="relative flex h-2 w-2 shrink-0 ml-0.5" title="Ada warna coloring yang mendekati minimum stok">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                @endif
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <!-- Form Tambah / Edit Layanan -->
        <x-ui.card class="{{ $editingService ? 'ring-2 ring-amber-500/40 border-amber-500' : '' }}">
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-bold flex items-center gap-1.5">
                        <x-ui.icon :name="$editingService ? 'edit' : 'plus'" class="w-4 h-4 text-amber-600" />
                        <span>{{ $editingService ? 'Edit Layanan / Produk' : 'Tambah Layanan / Produk' }}</span>
                    </h2>
                    @if($editingService)
                        <span class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-700 text-[11px] font-extrabold">
                            Mode Edit
                        </span>
                    @endif
                </div>
            </x-slot:header>

            <form
                method="POST"
                action="{{ $editingService ? route('owner.services.update', $editingService) : route('owner.services.store') }}"
                class="space-y-3"
            >
                @csrf
                @if($editingService)
                    @method('PUT')
                @endif

                <x-ui.input
                    label="Nama Layanan / Produk *"
                    name="name"
                    :value="$editingService?->name"
                    placeholder="Creambath Spa"
                    required
                />

                <x-ui.select
                    label="Kategori"
                    name="service_category_id"
                    icon="scissors"
                    :options="$categoryOptions"
                    :value="$editingService?->service_category_id"
                    search-placeholder="Cari kategori..."
                />

                <x-ui.select
                    label="Jenis"
                    name="type"
                    icon="ticket"
                    :options="$typeOptions"
                    :value="$editingService?->type"
                />

                <div>
                    <label for="price" class="block text-xs font-semibold text-zinc-700 mb-1.5">
                        Harga (Rp) <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none">Rp</span>
                        <input
                            type="text"
                            inputmode="numeric"
                            id="price"
                            name="price"
                            value="{{ number_format((int) old('price', $editingService ? $editingService->price : 50000), 0, ',', '.') }}"
                            required
                            placeholder="50.000"
                            autocomplete="off"
                            class="w-full rounded-xl border border-zinc-200 bg-white pl-10 pr-3.5 py-2.5 text-sm font-black text-zinc-900 num placeholder-zinc-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 min-h-[44px] transition"
                        />
                    </div>
                    @error('price')
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.input
                    label="Estimasi Durasi (Menit) *"
                    name="duration_minutes"
                    type="number"
                    :value="$editingService ? $editingService->duration_minutes : '30'"
                    required
                />

                <x-ui.input
                    label="Deskripsi Singkat"
                    name="description"
                    :value="$editingService?->description"
                    placeholder="Pijat kepala + vitamin"
                />

                @if($editingService)
                    <div class="pt-2 flex items-center gap-2">
                        <x-ui.button :href="route('owner.services')" variant="outline" class="flex-1">
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
                        <span>Simpan Layanan</span>
                    </x-ui.button>
                @endif
            </form>
        </x-ui.card>

        <!-- Daftar Layanan & Harga -->
        <x-ui.card class="lg:col-span-2">
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
                    <div>
                        <h2 class="font-bold">Daftar Layanan &amp; Harga</h2>
                        <span class="text-xs text-zinc-400">Mengedit layanan tidak akan mengubah riwayat transaksi lama (Snapshot)</span>
                    </div>
                    <form method="GET" action="{{ route('owner.services') }}" class="grid grid-cols-2 gap-2 w-full sm:flex sm:items-center sm:w-auto">
                        <div class="w-full sm:w-44">
                            <x-ui.select
                                name="category_id"
                                size="sm"
                                align="right"
                                placeholder="Semua Kategori"
                                icon="scissors"
                                :options="$categoryOptions"
                                :value="request('category_id')"
                                search-placeholder="Cari kategori..."
                                class="w-full"
                                auto-submit
                            />
                        </div>
                        <div class="w-full sm:w-40">
                            <x-ui.select
                                name="type"
                                size="sm"
                                align="right"
                                placeholder="Semua Jenis"
                                icon="filter"
                                :options="$typeOptions"
                                :value="request('type')"
                                class="w-full"
                                auto-submit
                            />
                        </div>
                    </form>
                </div>
            </x-slot:header>

            <div class="divide-y divide-zinc-100">
                @forelse($paginatedServices as $service)
                    @php
                        $isBeingEdited = $editingService && $editingService->id === $service->id;
                        $editUrl = route('owner.services', array_filter([
                            'edit' => $service->id,
                            'category_id' => request('category_id'),
                            'type' => request('type'),
                            'page' => $currentPage > 1 ? $currentPage : null,
                        ]));
                    @endphp
                    <div class="py-3.5 px-2 -mx-2 rounded-xl flex flex-wrap items-center justify-between gap-3 transition hover:bg-zinc-50/50">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-extrabold text-sm text-zinc-900">{{ $service->name }}</p>
                                @if($service->category)
                                    <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-800 text-[11px] font-semibold">
                                        {{ $service->category->name }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500 mt-1">{{ $service->description ?: 'Belum ada deskripsi singkat.' }}</p>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <p class="text-sm font-extrabold text-zinc-900 num">
                                Rp{{ number_format($service->price, 0, ',', '.') }}
                            </p>

                            <x-ui.button :href="$editUrl" size="sm" :variant="$isBeingEdited ? 'primary' : 'outline'">
                                <x-ui.icon name="edit" class="w-3.5 h-3.5" />
                                <span>Edit</span>
                            </x-ui.button>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 py-6 text-center">Tidak ada layanan atau produk yang sesuai filter.</p>
                @endforelse
            </div>

            @if($totalPages > 1)
                @php
                    $prevUrl = $currentPage > 1
                        ? route('owner.services', array_filter([
                            'category_id' => request('category_id'),
                            'type' => request('type'),
                            'edit' => request('edit'),
                            'page' => $currentPage - 1 > 1 ? $currentPage - 1 : null,
                        ]))
                        : null;

                    $nextUrl = $currentPage < $totalPages
                        ? route('owner.services', array_filter([
                            'category_id' => request('category_id'),
                            'type' => request('type'),
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

    <!-- Script Format Pemisah Ribuan (Titik) Real-time -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const priceInput = document.getElementById('price');
            if (!priceInput) return;

            function formatRupiahNumber(val) {
                const clean = String(val).replace(/\D/g, '');
                if (!clean) return '';
                return parseInt(clean, 10).toLocaleString('id-ID');
            }

            priceInput.addEventListener('input', function () {
                this.value = formatRupiahNumber(this.value);
            });

            // Pastikan nilai dikirim sebagai angka murni tanpa titik saat submit form
            priceInput.closest('form')?.addEventListener('submit', function () {
                priceInput.value = priceInput.value.replace(/\D/g, '') || '0';
            });
        });
    </script>
</x-layouts.app>
