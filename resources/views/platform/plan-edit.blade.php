<x-layouts.app title="Edit Paket Langganan" subtitle="Ubah tarif sewa bulanan, kuota cabang & limitasi staf paket" role="superadmin">
    <!-- Breadcrumb & Back Link -->
    <div class="mb-4 sm:mb-5">
        <a
            href="{{ route('platform.plans') }}"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition"
        >
            <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
            <span>Kembali ke Katalog Paket</span>
        </a>
    </div>

    <!-- Alert Validasi -->
    @if($errors->any())
        <div class="w-full mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-800 text-xs font-semibold space-y-1">
            <div class="flex items-center gap-2">
                <x-ui.icon name="info" class="w-4 h-4 text-rose-600 shrink-0" />
                <span>Terjadi kesalahan pada formulir:</span>
            </div>
            <ul class="list-disc pl-5 font-normal text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Edit Paket Full Width (Tablet & Desktop Full Kanan-Kiri) -->
    <div class="w-full">
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-7 shadow-2xs">
            <!-- Header Kartu: Responsif, Fleksibel & Bebas Bug Visual pada Mode Tablet Sidebar -->
            <div class="pb-4 mb-6 border-b border-zinc-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                        <span class="font-mono text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-700 border border-zinc-200/70 shrink-0">
                            {{ $plan->code }}
                        </span>
                        <h1 class="text-base sm:text-lg font-black text-zinc-900 tracking-tight">Edit Paket {{ $plan->name }}</h1>
                    </div>

                    <span class="text-xs font-bold shrink-0 self-start sm:self-auto {{ $plan->is_active ? 'text-emerald-600' : 'text-zinc-500' }}">
                        {{ $plan->is_active ? 'Status: Aktif' : 'Status: Nonaktif' }}
                    </span>
                </div>
                <p class="text-xs text-zinc-500 mt-2 leading-relaxed">
                    Saat ini paket ini aktif digunakan oleh <strong class="text-zinc-800">{{ $plan->subscriptions_count }}</strong> barbershop penyewa sistem.
                </p>
            </div>

            <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Baris 1: Nama Paket & Tarif Sewa Bulanan (2 Kolom di Desktop/Tablet) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="name" class="block text-xs font-bold text-zinc-700 mb-1.5">
                            Nama Paket <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $plan->name) }}"
                            required
                            maxlength="255"
                            placeholder="Contoh: Starter, Pro, Business"
                            class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 font-bold focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Nama tier paket yang tampil di katalog & pilihan pendaftaran.</p>
                    </div>

                    <div>
                        <label for="price_monthly_display" class="block text-xs font-bold text-zinc-700 mb-1.5">
                            Tarif Sewa Bulanan (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400">Rp</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                id="price_monthly_display"
                                value="{{ number_format(old('price_monthly', $plan->price_monthly), 0, ',', '.') }}"
                                required
                                placeholder="0"
                                autocomplete="off"
                                class="w-full pl-10 pr-3.5 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 font-black num focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                            />
                            <input
                                type="hidden"
                                id="price_monthly"
                                name="price_monthly"
                                value="{{ old('price_monthly', $plan->price_monthly) }}"
                            />
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-1">Biaya perpanjangan langganan sewa sistem setiap 30 hari.</p>
                    </div>
                </div>

                <!-- Baris 2: Kuota Cabang & Kuota Staf (2 Kolom di Desktop/Tablet) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="max_branches" class="block text-xs font-bold text-zinc-700 mb-1.5">
                            Batas Maksimal Cabang / Outlet
                        </label>
                        <input
                            type="number"
                            id="max_branches"
                            name="max_branches"
                            value="{{ old('max_branches', $plan->max_branches) }}"
                            min="1"
                            placeholder="Kosongkan untuk Unlimited (Tanpa Batas)"
                            class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 font-bold focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Contoh: 1, 3, 10 cabang (kosongkan jika tidak ada batas outlet).</p>
                    </div>

                    <div>
                        <label for="max_staff" class="block text-xs font-bold text-zinc-700 mb-1.5">
                            Batas Maksimal Staf / Karyawan
                        </label>
                        <input
                            type="number"
                            id="max_staff"
                            name="max_staff"
                            value="{{ old('max_staff', $plan->max_staff) }}"
                            min="1"
                            placeholder="Kosongkan untuk Unlimited (Tanpa Batas)"
                            class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 font-bold focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Total staf (barber & kasir) yang dapat didaftarkan di seluruh cabang.</p>
                    </div>
                </div>

                <!-- Baris 3: Deskripsi Paket (Full Width) -->
                <div>
                    <label for="description" class="block text-xs font-bold text-zinc-700 mb-1.5">
                        Deskripsi Singkat Paket
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="500"
                        placeholder="Tuliskan keterangan peruntukan paket ini..."
                        class="w-full p-3.5 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition leading-relaxed"
                    >{{ old('description', $plan->description) }}</textarea>
                    <p class="text-[11px] text-zinc-400 mt-1">Deskripsi singkat fungsi dan target skala barbershop untuk paket ini.</p>
                </div>

                <!-- Baris 4: Checkbox Status Aktif (Full Width Card) -->
                <div class="p-4 rounded-xl border border-zinc-200/80 bg-zinc-50/40 flex items-start gap-3">
                    <input
                        type="checkbox"
                        id="is_active"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $plan->is_active))
                        class="w-4 h-4 mt-0.5 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                    />
                    <label for="is_active" class="text-xs text-zinc-700 cursor-pointer">
                        <span class="font-bold text-zinc-900 block">Paket Aktif &amp; Terbuka untuk Langganan Baru</span>
                        <span class="text-[11px] text-zinc-500 leading-relaxed block mt-0.5">Jika dinonaktifkan, tenant lama yang sudah berlangganan paket ini tetap dapat menggunakannya hingga masa aktif berakhir, namun paket tidak akan muncul untuk pendaftaran baru.</span>
                    </label>
                </div>

                <!-- Baris 5: Tombol Aksi Simpan & Batal -->
                <div class="pt-5 border-t border-zinc-100 flex items-center justify-between">
                    <a
                        href="{{ route('platform.plans') }}"
                        class="px-4 py-2.5 rounded-xl border border-zinc-200 hover:bg-zinc-100 active:scale-95 text-zinc-700 font-bold text-xs transition"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs shadow-xs transition cursor-pointer inline-flex items-center gap-2"
                    >
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span>Simpan Perubahan Paket</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Format Pemisah Ribuan (Titik) Real-time -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const displayInput = document.getElementById('price_monthly_display');
            const hiddenInput = document.getElementById('price_monthly');

            function formatRupiahInput(value) {
                const cleanDigits = String(value).replace(/\D/g, '');
                if (!cleanDigits) return { formatted: '', raw: '0' };
                const formatted = new Intl.NumberFormat('id-ID').format(cleanDigits);
                return { formatted: formatted, raw: cleanDigits };
            }

            if (displayInput && hiddenInput) {
                displayInput.addEventListener('input', function() {
                    const result = formatRupiahInput(this.value);
                    this.value = result.formatted;
                    hiddenInput.value = result.raw;
                });

                // Sinkronisasi saat form disubmit
                displayInput.closest('form')?.addEventListener('submit', function() {
                    const cleanDigits = displayInput.value.replace(/\D/g, '');
                    hiddenInput.value = cleanDigits || '0';
                });
            }
        });
    </script>
</x-layouts.app>
