<x-layouts.app :title="'Katalog Iklan & Promosi Produk'">
    <div class="space-y-6 max-w-7xl mx-auto pb-36 sm:pb-24">
        <!-- Header Halaman -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0 border border-amber-500/20 shadow-2xs">
                    <x-ui.icon name="tag" class="w-6 h-6 text-amber-600" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-xl font-black text-zinc-950 tracking-tight">Katalog Iklan & Promosi Produk</h1>
                    <p class="text-xs sm:text-sm text-zinc-500 mt-0.5">Kelola banner promosi produk yang berputar otomatis di dashboard pelanggan & portal tenant.</p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <button
                    type="button"
                    onclick="addNewPromotedProduct()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-xs transition active:scale-95 cursor-pointer"
                >
                    <x-ui.icon name="plus" class="w-4 h-4" />
                    <span>Tambah Produk Baru</span>
                </button>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 flex items-center gap-3 text-xs sm:text-sm font-bold shadow-2xs">
                <div class="w-7 h-7 rounded-xl bg-emerald-500/20 text-emerald-700 flex items-center justify-center shrink-0">
                    <x-ui.icon name="check" class="w-4 h-4" />
                </div>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-800 text-xs sm:text-sm space-y-1.5 shadow-2xs">
                <div class="flex items-center gap-2 font-black text-rose-900 mb-1">
                    <x-ui.icon name="alert-circle" class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>Unggahan atau pengisian data ditolak oleh sistem:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 pl-1 font-semibold text-rose-700 text-xs">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Ringkasan Statistik Singkat -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 block uppercase tracking-wider">Total Produk</span>
                <span class="text-xl sm:text-2xl font-black text-zinc-900 mt-1 block num">{{ count($promotedProducts) }}</span>
                <span class="text-[11px] text-zinc-400 mt-0.5 block">Item dalam katalog</span>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-emerald-600 block uppercase tracking-wider">Status Aktif Tayang</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 mt-1 block num">
                    {{ collect($promotedProducts)->where('is_active', true)->count() }}
                </span>
                <span class="text-[11px] text-zinc-400 mt-0.5 block">Tampil di slide pelanggan</span>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 block uppercase tracking-wider">Durasi Pergantian</span>
                <span class="text-xl sm:text-2xl font-black text-zinc-900 mt-1 block num">{{ $slideDuration }}s</span>
                <span class="text-[11px] text-zinc-400 mt-0.5 block">Interval antar slide banner</span>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 block uppercase tracking-wider">Tautan Pembelian</span>
                <span class="text-xl sm:text-2xl font-black text-amber-500 mt-1 block truncate">Link Langsung</span>
                <span class="text-[11px] text-zinc-400 mt-0.5 block">Shopee / Tokopedia / Web</span>
            </div>
        </div>

        <!-- Form Pengaturan Promosi & Iklan -->
        <form
            id="promotions-form"
            method="POST"
            action="{{ route('platform.promotions.update') }}"
            enctype="multipart/form-data"
            class="space-y-6"
        >
            @csrf

            <!-- Card 1: Pengaturan Global Banner (Slide Duration) -->
            <div class="p-5 sm:p-6 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100">
                    <div class="w-8 h-8 rounded-lg bg-zinc-100 text-zinc-700 flex items-center justify-center shrink-0">
                        <x-ui.icon name="sliders" class="w-4 h-4 text-zinc-700" />
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-zinc-900">Konfigurasi Slide Banner</h2>
                        <p class="text-xs text-zinc-500">Atur kecepatan durasi perpindahan slide banner iklan produk official</p>
                    </div>
                </div>

                <div class="max-w-md">
                    <x-ui.select
                        label="Interval Pergantian Banner *"
                        name="promoted_products_slide_seconds"
                        :options="$slideDurationOptions"
                        :value="(string) old('promoted_products_slide_seconds', $slideDuration)"
                        icon="clock"
                    />
                    <p class="text-[11px] text-zinc-400 mt-1.5">Standar rekomendasi: 5 detik per slide agar informasi produk mudah terbaca.</p>
                </div>
            </div>

            <!-- Card 2: Daftar Produk Promosi -->
            <div class="p-5 sm:p-6 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-zinc-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="package" class="w-4 h-4 text-amber-600" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-zinc-900">Daftar Produk yang Dipromosikan</h2>
                            <p class="text-xs text-zinc-500">Masing-masing produk akan ditampilkan bergiliran pada banner slide pelanggan.</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        onclick="addNewPromotedProduct()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-xs transition cursor-pointer self-start sm:self-auto shrink-0"
                    >
                        <x-ui.icon name="plus" class="w-3.5 h-3.5" />
                        <span>Tambah Produk</span>
                    </button>
                </div>

                <!-- Kontainer Kartu Produk -->
                <div id="promoted-products-container" class="space-y-5">
                    @forelse($promotedProducts as $index => $prod)
                        @php
                            $isStockReady = ($prod['stock_status'] ?? 'ready') !== 'habis';
                        @endphp
                        <div class="product-item-card p-4 sm:p-5 rounded-2xl border border-zinc-200/90 bg-zinc-50/60 space-y-4 transition hover:border-zinc-300 relative shadow-2xs" data-product-index="{{ $index }}">
                            <!-- Bar Header Produk -->
                            <div class="flex items-center justify-between gap-3 pb-3 border-b border-zinc-200/70">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-12 h-12 rounded-xl bg-zinc-950 border border-zinc-800 p-1.5 flex items-center justify-center shrink-0 shadow-xs">
                                        <img
                                            src="{{ !empty($prod['image']) ? $prod['image'] : asset('images/products/pomade.svg') }}"
                                            alt="{{ $prod['name'] ?? 'Produk' }}"
                                            class="w-full h-full object-contain card-cover-image"
                                            onerror="this.src='{{ asset('images/products/pomade.svg') }}'"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs sm:text-sm font-black text-zinc-900 truncate card-header-title">
                                                {{ $prod['name'] ?? 'Produk Official' }}
                                            </span>

                                            <!-- Badge Status Tampil -->
                                            @if(!empty($prod['is_active']))
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/15 text-emerald-700 border border-emerald-500/20 shrink-0">
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-zinc-200 text-zinc-600 border border-zinc-300 shrink-0">
                                                    Nonaktif
                                                </span>
                                            @endif

                                            <!-- Badge Status Stok -->
                                            @if($isStockReady)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/15 text-emerald-700 border border-emerald-500/20 shrink-0 card-stock-badge">
                                                    Ready Stock
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500/15 text-rose-700 border border-rose-500/20 shrink-0 card-stock-badge">
                                                    Stok Habis
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs font-black text-amber-600 num">{{ $prod['price'] ?? 'Rp0' }}</span>
                                            @if(!empty($prod['original_price']))
                                                <span class="text-[11px] font-semibold text-zinc-400 line-through num">{{ $prod['original_price'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Tombol Hapus Solid & Icon SVG Jelas -->
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onclick="removeProductCard(this)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition active:scale-95 cursor-pointer"
                                        title="Hapus produk ini dari daftar promosi"
                                    >
                                        <svg class="w-3.5 h-3.5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden inputs for ID & Existing Images -->
                            <input type="hidden" name="promoted_products[{{ $index }}][id]" value="{{ $prod['id'] ?? ('prod-' . ($index + 1)) }}" />
                            <input type="hidden" name="promoted_products[{{ $index }}][image_existing]" value="{{ $prod['image'] ?? '' }}" />
                            <input type="hidden" name="promoted_products[{{ $index }}][images_existing]" value="{{ json_encode($prod['images'] ?? (!empty($prod['image']) ? [$prod['image']] : [])) }}" />

                            <!-- Baris 1: Nama & Tagline -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                        Nama Produk <span class="text-rose-500 font-bold ml-0.5">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="promoted_products[{{ $index }}][name]"
                                        value="{{ $prod['name'] ?? '' }}"
                                        required
                                        oninput="updateCardPreviewTitle(this)"
                                        placeholder="Contoh: BarberOS Matte Clay Pomade"
                                        class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Tagline Singkat</label>
                                    <input
                                        type="text"
                                        name="promoted_products[{{ $index }}][tagline]"
                                        value="{{ $prod['tagline'] ?? '' }}"
                                        placeholder="Contoh: Strong Hold & Natural Matte Finish"
                                        class="w-full px-3 py-2 text-xs font-semibold rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                                    />
                                </div>
                            </div>

                            <!-- Baris 2: Harga Promo, Normal, Warna Teks Harga, Status Tampil, Status Stok -->
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-start">
                                <div class="sm:col-span-6 lg:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                        Harga Promo <span class="text-rose-500 font-bold ml-0.5">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="promoted_products[{{ $index }}][price]"
                                        value="{{ $prod['price'] ?? '' }}"
                                        required
                                        placeholder="Rp65.000"
                                        data-rupiah-price-input
                                        class="w-full px-3 py-2 text-xs font-black rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                                    />
                                </div>

                                <div class="sm:col-span-6 lg:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Harga Normal (Coret)</label>
                                    <input
                                        type="text"
                                        name="promoted_products[{{ $index }}][original_price]"
                                        value="{{ $prod['original_price'] ?? '' }}"
                                        placeholder="Rp85.000"
                                        data-rupiah-price-input
                                        class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-zinc-200 bg-white text-zinc-600 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                                    />
                                </div>

                                <div class="sm:col-span-4 lg:col-span-3">
                                    <x-ui.select
                                        label="Warna Teks Harga *"
                                        name="promoted_products[{{ $index }}][price_color]"
                                        :options="$colorOptions"
                                        :value="$prod['price_color'] ?? 'text-amber-400'"
                                        size="sm"
                                    />
                                </div>

                                <div class="sm:col-span-4 lg:col-span-2">
                                    <x-ui.select
                                        label="Status Tampil *"
                                        name="promoted_products[{{ $index }}][is_active]"
                                        :options="$statusOptions"
                                        :value="!empty($prod['is_active']) ? '1' : '0'"
                                        size="sm"
                                    />
                                </div>

                                <div class="sm:col-span-4 lg:col-span-3">
                                    <x-ui.select
                                        label="Status Stok *"
                                        name="promoted_products[{{ $index }}][stock_status]"
                                        :options="$stockStatusOptions"
                                        :value="$prod['stock_status'] ?? 'ready'"
                                        size="sm"
                                    />
                                </div>
                            </div>

                            <!-- Baris Link Pembelian Produk (Marketplace / Toko Online) -->
                            <div>
                                <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                    Tautan / Link Pembelian Produk (Shopee, Tokopedia, Web Toko, dll)
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-400" />
                                    </div>
                                    <input
                                        type="url"
                                        name="promoted_products[{{ $index }}][url]"
                                        value="{{ $prod['url'] ?? 'https://shopee.co.id' }}"
                                        placeholder="Contoh: https://shopee.co.id/nama-produk atau link toko lainnya"
                                        class="w-full pl-9 pr-3 py-2 text-xs font-medium rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                                    />
                                </div>
                                <p class="text-[11px] text-zinc-400 mt-1">Tautan eksternal yang akan dibuka langsung oleh pelanggan saat menekan tombol "Beli Sekarang".</p>
                            </div>

                            <!-- Baris 3: Deskripsi & Poin Keunggulan -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Deskripsi Lengkap Produk</label>
                                    <textarea
                                        name="promoted_products[{{ $index }}][desc]"
                                        rows="3"
                                        placeholder="Jelaskan manfaat, kandungan, dan hasil penggunaan produk..."
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition leading-relaxed resize-none"
                                    >{{ $prod['desc'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Keunggulan Utama (1 baris per poin)</label>
                                    <textarea
                                        name="promoted_products[{{ $index }}][features]"
                                        rows="3"
                                        placeholder="100% Water-soluble, mudah dibilas&#10;Daya set kuat tahan seharian&#10;Aroma maskulin mewah segar"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition leading-relaxed resize-none"
                                    >{{ is_array($prod['features'] ?? null) ? implode("\n", $prod['features']) : ($prod['features'] ?? '') }}</textarea>
                                </div>
                            </div>

                            <!-- Baris 4: Galeri Foto & Box Unggah yang Jelas dan Informatif -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 space-y-4 shadow-2xs">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-zinc-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                                            <x-ui.icon name="image" class="w-4 h-4 text-amber-600" />
                                        </div>
                                        <div>
                                            <span class="text-xs font-black text-zinc-900">Foto & Galeri Produk</span>
                                            <span class="text-[10px] text-zinc-400 block">Foto produk yang tampil pada slide banner dan galeri popup</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-zinc-500 flex-wrap">
                                        <span>Format: <strong class="text-zinc-700 font-bold">PNG, JPG, JPEG, WEBP, SVG</strong></span>
                                        <span class="text-zinc-300">·</span>
                                        <span>Ukuran: <strong class="text-rose-600 font-bold">Maks. 2MB / File</strong></span>
                                    </div>
                                </div>

                                <!-- Galeri Thumbnail Saat Ini -->
                                @php
                                    $galleryList = $prod['images'] ?? (!empty($prod['image']) ? [$prod['image']] : []);
                                @endphp
                                @if(!empty($galleryList))
                                    <div class="space-y-1.5">
                                        <span class="text-[11px] font-bold text-zinc-600 block">
                                            Galeri Foto Aktif ({{ count($galleryList) }} foto tersimpan):
                                        </span>
                                        <div class="flex items-center gap-2.5 overflow-x-auto no-scrollbar py-1">
                                            @foreach($galleryList as $gIdx => $gUrl)
                                                <div class="relative group w-16 h-16 rounded-xl bg-zinc-950 border border-zinc-800 p-1.5 flex items-center justify-center shrink-0 shadow-xs">
                                                    <img src="{{ $gUrl }}" alt="Foto {{ $gIdx + 1 }}" class="w-full h-full object-contain" />
                                                    @if($gIdx === 0)
                                                        <span class="absolute -top-1.5 -left-1.5 bg-amber-500 text-zinc-950 text-[9px] font-black px-1.5 py-0.2 rounded-md shadow-xs">
                                                            Utama
                                                        </span>
                                                    @else
                                                        <span class="absolute -bottom-1 -right-1 bg-zinc-800 text-zinc-300 text-[8px] font-bold px-1 rounded">
                                                            #{{ $gIdx + 1 }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Dropzone Input Unggah Foto Produk Baru -->
                                <div class="relative rounded-2xl border-2 border-dashed border-zinc-300 hover:border-amber-500 bg-zinc-50/70 p-4 sm:p-5 text-center transition cursor-pointer group">
                                    <input
                                        type="file"
                                        name="promoted_products[{{ $index }}][image_files][]"
                                        multiple
                                        accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"
                                        onchange="handleProductImageSelection(this)"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                        title="Pilih file foto produk"
                                    />
                                    <div class="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center group-hover:scale-110 transition duration-200 shadow-2xs">
                                            <x-ui.icon name="upload-cloud" class="w-5 h-5 text-amber-600" />
                                        </div>
                                        <div class="space-y-0.5">
                                            <p class="text-xs sm:text-sm font-black text-zinc-900 group-hover:text-amber-600 transition">
                                                Pilih atau Seret Foto Produk ke Sini
                                            </p>
                                            <p class="text-[11px] text-zinc-500">
                                                Dapat memilih beberapa file sekaligus untuk galeri sudut produk berbeda.
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] font-semibold text-zinc-400 mt-1">
                                            <span>Format: PNG, JPG, JPEG, WEBP, SVG</span>
                                            <span>·</span>
                                            <span class="text-rose-600 font-bold">Maks. 2MB per file (Ditolak jika melebihi)</span>
                                        </div>
                                    </div>

                                    <!-- Feedback list file yang baru dipilih -->
                                    <div class="file-selection-preview hidden mt-3 pt-3 border-t border-zinc-200/80 text-left pointer-events-none">
                                        <span class="text-[11px] font-bold text-zinc-700 block mb-1.5">File yang Siap Diunggah:</span>
                                        <div class="file-selection-list flex flex-wrap gap-1.5"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div id="promoted-products-empty" class="p-8 text-center rounded-2xl border-2 border-dashed border-zinc-200 bg-white">
                            <x-ui.icon name="tag" class="w-8 h-8 text-zinc-300 mx-auto mb-2" />
                            <p class="text-xs font-bold text-zinc-500">Belum ada produk iklan terdaftar.</p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">Klik tombol "Tambah Produk Baru" untuk menambahkan promosi pertama.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Floating / Sticky Bottom Action Bar (Diatur Agar Tidak Tertutup Docking Bottom Navbar Mobile) -->
            <div class="sticky bottom-20 sm:bottom-4 z-30 p-4 rounded-2xl bg-zinc-950/95 backdrop-blur-md text-white border border-zinc-800 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                    <span class="text-xs sm:text-sm font-bold text-zinc-200">
                        Pastikan seluruh data harga, status stok, dan foto produk sudah sesuai sebelum menyimpan.
                    </span>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                    <a
                        href="{{ route('platform.settings') }}"
                        class="px-4 py-2.5 rounded-xl text-xs font-extrabold bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700/80 shadow-xs transition active:scale-95 text-center"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-md transition active:scale-95 cursor-pointer"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Pengaturan Promosi</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Hidden Template untuk Penambahan Produk Baru secara Dinamis -->
    <template id="new-product-card-template">
        <div class="product-item-card p-4 sm:p-5 rounded-2xl border border-zinc-200/90 bg-zinc-50/60 space-y-4 transition hover:border-zinc-300 relative shadow-2xs" data-product-index="__INDEX__">
            <!-- Bar Header Produk -->
            <div class="flex items-center justify-between gap-3 pb-3 border-b border-zinc-200/70">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-xl bg-zinc-950 border border-zinc-800 p-1.5 flex items-center justify-center shrink-0 shadow-xs">
                        <img
                            src="{{ asset('images/products/pomade.svg') }}"
                            alt="Produk Baru"
                            class="w-full h-full object-contain card-cover-image"
                        />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs sm:text-sm font-black text-zinc-900 truncate card-header-title">
                                Produk Promosi Baru
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/15 text-emerald-700 border border-emerald-500/20 shrink-0">
                                Aktif
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/15 text-emerald-700 border border-emerald-500/20 shrink-0 card-stock-badge">
                                Ready Stock
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs font-black text-amber-600 num">Rp0</span>
                        </div>
                    </div>
                </div>

                <!-- Tombol Hapus Solid & Icon SVG Jelas -->
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onclick="removeProductCard(this)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition active:scale-95 cursor-pointer"
                        title="Hapus produk ini dari daftar promosi"
                    >
                        <svg class="w-3.5 h-3.5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Hapus</span>
                    </button>
                </div>
            </div>

            <!-- Hidden inputs -->
            <input type="hidden" name="promoted_products[__INDEX__][id]" value="prod-__ID__" />
            <input type="hidden" name="promoted_products[__INDEX__][image_existing]" value="{{ asset('images/products/pomade.svg') }}" />
            <input type="hidden" name="promoted_products[__INDEX__][images_existing]" value="[]" />

            <!-- Baris 1: Nama & Tagline -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">
                        Nama Produk <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <input
                        type="text"
                        name="promoted_products[__INDEX__][name]"
                        value="Produk Promosi Baru"
                        required
                        oninput="updateCardPreviewTitle(this)"
                        placeholder="Contoh: BarberOS Beard Oil"
                        class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Tagline Singkat</label>
                    <input
                        type="text"
                        name="promoted_products[__INDEX__][tagline]"
                        value=""
                        placeholder="Contoh: Formula Premium Khusus Pria"
                        class="w-full px-3 py-2 text-xs font-semibold rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                    />
                </div>
            </div>

            <!-- Baris 2: Harga Promo, Normal, Warna Dropdown x-ui.select, Status Dropdown x-ui.select, Status Stok -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-start">
                <div class="sm:col-span-6 lg:col-span-2">
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">
                        Harga Promo <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <input
                        type="text"
                        name="promoted_products[__INDEX__][price]"
                        value="Rp50.000"
                        required
                        placeholder="Rp50.000"
                        data-rupiah-price-input
                        class="w-full px-3 py-2 text-xs font-black rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                    />
                </div>

                <div class="sm:col-span-6 lg:col-span-2">
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Harga Normal (Coret)</label>
                    <input
                        type="text"
                        name="promoted_products[__INDEX__][original_price]"
                        value="Rp75.000"
                        placeholder="Rp75.000"
                        data-rupiah-price-input
                        class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-zinc-200 bg-white text-zinc-600 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                    />
                </div>

                <div class="sm:col-span-4 lg:col-span-3">
                    <x-ui.select
                        label="Warna Teks Harga *"
                        name="promoted_products[__INDEX__][price_color]"
                        :options="$colorOptions"
                        value="text-amber-400"
                        size="sm"
                    />
                </div>

                <div class="sm:col-span-4 lg:col-span-2">
                    <x-ui.select
                        label="Status Tampil *"
                        name="promoted_products[__INDEX__][is_active]"
                        :options="$statusOptions"
                        value="1"
                        size="sm"
                    />
                </div>

                <div class="sm:col-span-4 lg:col-span-3">
                    <x-ui.select
                        label="Status Stok *"
                        name="promoted_products[__INDEX__][stock_status]"
                        :options="$stockStatusOptions"
                        value="ready"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Baris Link Pembelian Produk (Marketplace / Toko Online) -->
            <div>
                <label class="block text-xs font-semibold text-zinc-700 mb-1">
                    Tautan / Link Pembelian Produk (Shopee, Tokopedia, Web Toko, dll)
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                        <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-400" />
                    </div>
                    <input
                        type="url"
                        name="promoted_products[__INDEX__][url]"
                        value="https://shopee.co.id"
                        placeholder="Contoh: https://shopee.co.id/nama-produk atau link toko lainnya"
                        class="w-full pl-9 pr-3 py-2 text-xs font-medium rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition"
                    />
                </div>
                <p class="text-[11px] text-zinc-400 mt-1">Tautan eksternal yang akan dibuka langsung oleh pelanggan saat menekan tombol "Beli Sekarang".</p>
            </div>

            <!-- Baris 3: Deskripsi & Poin Keunggulan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Deskripsi Lengkap Produk</label>
                    <textarea
                        name="promoted_products[__INDEX__][desc]"
                        rows="3"
                        placeholder="Jelaskan manfaat, kandungan, dan hasil penggunaan produk..."
                        class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition leading-relaxed resize-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Keunggulan Utama (1 baris per poin)</label>
                    <textarea
                        name="promoted_products[__INDEX__][features]"
                        rows="3"
                        placeholder="Kandungan alami premium&#10;Hasil instan dan tahan lama&#10;Aman untuk semua jenis kulit kepala"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 shadow-2xs transition leading-relaxed resize-none"
                    ></textarea>
                </div>
            </div>

            <!-- Baris 4: Dropzone Upload Foto -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/90 space-y-3.5 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2.5 border-b border-zinc-100">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="image" class="w-4 h-4 text-amber-600" />
                        </div>
                        <span class="text-xs font-black text-zinc-900">Foto & Galeri Produk</span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] font-medium text-zinc-500 flex-wrap">
                        <span>Format: <strong class="text-zinc-700 font-bold">PNG, JPG, JPEG, WEBP, SVG</strong></span>
                        <span class="text-zinc-300">·</span>
                        <span>Ukuran: <strong class="text-rose-600 font-bold">Maks. 2MB / File</strong></span>
                    </div>
                </div>

                <div class="relative rounded-2xl border-2 border-dashed border-zinc-300 hover:border-amber-500 bg-zinc-50/70 p-4 sm:p-5 text-center transition cursor-pointer group">
                    <input
                        type="file"
                        name="promoted_products[__INDEX__][image_files][]"
                        multiple
                        accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"
                        onchange="handleProductImageSelection(this)"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        title="Pilih file foto produk"
                    />
                    <div class="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center group-hover:scale-110 transition duration-200 shadow-2xs">
                            <x-ui.icon name="upload-cloud" class="w-5 h-5 text-amber-600" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs sm:text-sm font-black text-zinc-900 group-hover:text-amber-600 transition">
                                Pilih atau Seret Foto Produk ke Sini
                            </p>
                            <p class="text-[11px] text-zinc-500">
                                Dapat memilih beberapa file sekaligus untuk galeri sudut produk berbeda.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-semibold text-zinc-400 mt-1">
                            <span>Format: PNG, JPG, JPEG, WEBP, SVG</span>
                            <span>·</span>
                            <span class="text-rose-600 font-bold">Maks. 2MB per file (Ditolak jika melebihi)</span>
                        </div>
                    </div>

                    <div class="file-selection-preview hidden mt-3 pt-3 border-t border-zinc-200/80 text-left pointer-events-none">
                        <span class="text-[11px] font-bold text-zinc-700 block mb-1.5">File yang Siap Diunggah:</span>
                        <div class="file-selection-list flex flex-wrap gap-1.5"></div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        let currentNextIndex = {{ count($promotedProducts) }};
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
        const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

        function addNewPromotedProduct() {
            const template = document.getElementById('new-product-card-template');
            const container = document.getElementById('promoted-products-container');
            const emptyEl = document.getElementById('promoted-products-empty');
            if (emptyEl) emptyEl.remove();

            const idx = currentNextIndex++;
            const idNumber = Date.now().toString().slice(-4);

            let html = template.innerHTML
                .replaceAll('__INDEX__', idx)
                .replaceAll('__ID__', idNumber);

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html.trim();
            const newCard = tempDiv.firstElementChild;

            container.appendChild(newCard);

            newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const nameInput = newCard.querySelector('input[name*="[name]"]');
            if (nameInput) {
                nameInput.focus();
                nameInput.select();
            }
        }

        function removeProductCard(btn) {
            const card = btn.closest('.product-item-card');
            if (!card) return;

            if (confirm('Apakah Anda yakin ingin menghapus produk ini dari daftar promosi?')) {
                card.style.transition = 'all 0.25s ease-out';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.remove();
                }, 250);
            }
        }

        function updateCardPreviewTitle(input) {
            const card = input.closest('.product-item-card');
            if (!card) return;
            const headerTitle = card.querySelector('.card-header-title');
            if (headerTitle) {
                headerTitle.textContent = input.value.trim() || 'Produk Promosi Baru';
            }
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        // Validasi ketat format gambar dan ukuran maks 2MB
        function handleProductImageSelection(input) {
            const container = input.closest('.product-item-card') || input.parentElement;
            const previewBox = input.parentElement.querySelector('.file-selection-preview');
            const previewList = input.parentElement.querySelector('.file-selection-list');

            if (!input.files || input.files.length === 0) {
                if (previewBox) previewBox.classList.add('hidden');
                return;
            }

            let hasError = false;
            let errorMsg = '';

            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const ext = file.name.split('.').pop().toLowerCase();

                // 1. Cek format ekstensi
                if (!ALLOWED_EXTENSIONS.includes(ext)) {
                    hasError = true;
                    errorMsg = `File "${file.name}" ditolak!\nFormat file .${ext.toUpperCase()} tidak didukung. Format yang diizinkan hanya: PNG, JPG, JPEG, WEBP, atau SVG.`;
                    break;
                }

                // 2. Cek ukuran file <= 2MB
                if (file.size > MAX_FILE_SIZE) {
                    hasError = true;
                    errorMsg = `File "${file.name}" ditolak!\nUkuran file (${formatBytes(file.size)}) melebihi batas maksimal 2 MB (2048 KB). Silakan kompres atau pilih file lain.`;
                    break;
                }
            }

            if (hasError) {
                alert(errorMsg);
                input.value = ''; // Reset input agar tidak terkirim
                if (previewBox) previewBox.classList.add('hidden');
                if (previewList) previewList.innerHTML = '';
                return;
            }

            // Tampilkan list preview file yang lolos validasi
            if (previewBox && previewList) {
                previewList.innerHTML = '';
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200/90 text-[10px] font-bold shadow-2xs';
                    badge.innerHTML = `
                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="truncate max-w-[140px]">${file.name}</span>
                        <span class="text-emerald-600 num font-mono">(${formatBytes(file.size)})</span>
                    `;
                    previewList.appendChild(badge);
                }
                previewBox.classList.remove('hidden');
            }
        }

        // Listener format titik pemisah ribuan otomatis pada input harga produk
        document.addEventListener('input', function(e) {
            if (e.target.matches('[data-rupiah-price-input]')) {
                const rawDigits = e.target.value.replace(/\D/g, '');
                if (rawDigits) {
                    e.target.value = 'Rp' + parseInt(rawDigits, 10).toLocaleString('id-ID');
                } else {
                    e.target.value = '';
                }
            }
        });
    </script>
</x-layouts.app>
