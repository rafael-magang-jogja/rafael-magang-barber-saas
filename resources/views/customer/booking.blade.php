<x-layouts.customer title="Booking Jadwal">
    @php
        $tenantOptions = $tenants->map(fn ($t) => [
            'value' => (string) $t->id,
            'label' => $t->name,
            'sublabel' => $t->branches->pluck('name')->join(', '),
        ])->all();

        $branchOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->address ?? $b->code,
        ])->all();

        $serviceOptions = $services->map(fn ($s) => [
            'value' => (string) $s->id,
            'label' => $s->name . ' — Rp' . number_format($s->price, 0, ',', '.'),
            'sublabel' => ($s->category ? $s->category->name . ' · ' : '') . $s->duration_minutes . ' menit',
        ])->all();

        $customerColorOptions = $hairColors->map(fn ($c) => [
            'value' => (string) $c->id,
            'label' => $c->name,
            'color' => $c->hex_code,
        ])->all();

        $firstColor = $hairColors->first();

        $barberOptions = $barbers->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->display_name ?? $b->user?->name ?? 'Barber',
            'sublabel' => $b->specialty ?? 'Professional Barber',
        ])->all();

        $firstServiceIsColoring = $services->first()
            && (stripos($services->first()->name, 'color') !== false
                || stripos($services->first()->name, 'warna') !== false
                || ($services->first()->category && stripos($services->first()->category->name, 'warna') !== false));
    @endphp

    <div class="space-y-4">
        <!-- Informasi Header Halaman Booking -->
        <div class="p-3.5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 grid place-items-center shrink-0">
                <x-ui.icon name="calendar" class="w-4 h-4" />
            </div>
            <div class="min-w-0">
                <h1 class="font-extrabold text-sm text-zinc-900 leading-tight">Reservasi Jadwal Potong</h1>
                <p class="text-[11px] text-zinc-500">Tentukan barbershop, layanan, dan jam kedatangan</p>
            </div>
        </div>

        <!-- Form Booking Terpisah -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-bold text-sm flex items-center gap-2">
                        <x-ui.icon name="sparkles" class="w-4 h-4 text-amber-600" />
                        <span>Formulir Booking</span>
                    </h2>
                    <span class="text-xs text-zinc-400">Atas Nama: <b class="text-zinc-800">{{ $user->name }}</b></span>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('customer.booking.store') }}" class="space-y-4">
                @csrf

                <!-- Pilih Barbershop & Cabang -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80">
                    <div>
                        <x-ui.select
                            label="Pilih Barbershop *"
                            name="tenant_id"
                            icon="store"
                            :options="$tenantOptions"
                            :value="$selectedTenant?->id"
                            data-booking-tenant-select="true"
                            search-placeholder="Cari barbershop..."
                        />
                    </div>
                    <div>
                        <x-ui.select
                            label="Pilih Cabang / Outlet *"
                            name="branch_id"
                            icon="store"
                            :options="$branchOptions"
                            :value="$selectedBranchId"
                            data-booking-branch-select="true"
                            search-placeholder="Cari cabang..."
                        />
                    </div>
                </div>

                <!-- Pilih Layanan -->
                <x-ui.select
                    label="Pilih Layanan (Bisa Lebih dari 1) *"
                    name="service_ids[]"
                    icon="scissors"
                    multiple
                    :options="$serviceOptions"
                    search-placeholder="Cari layanan..."
                />

                <!-- Pilihan Warna Coloring (Hanya Muncul Jika Layanan Coloring Dipilih) -->
                @if(!empty($customerColorOptions))
                    <div data-coloring-picker class="{{ $firstServiceIsColoring ? '' : 'hidden' }} space-y-2">
                        <x-ui.select
                            label="Pilih Warna Coloring"
                            name="hair_color_id"
                            :options="$customerColorOptions"
                            search-placeholder="Cari warna rambut..."
                        />

                        <!-- Live Preview Warna Customer (Hanya Nama & Swatch, Stok Disembunyikan) -->
                        <div data-color-live-preview class="flex items-center gap-3 p-3 rounded-2xl bg-zinc-50 border border-zinc-200/90 shadow-2xs">
                            <span
                                data-preview-swatch
                                class="w-9 h-9 rounded-xl border border-black/15 shadow-2xs shrink-0 transition"
                                style="background-color: {{ $firstColor?->hex_code ?? '#B45309' }}"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider block">Warna Pilihan Anda:</span>
                                <span data-preview-name class="text-xs font-extrabold text-zinc-900 block">{{ $firstColor?->name ?? 'Pilih Warna' }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Pilihan Barber (Dropdown membuka ke atas / drop-up sesuai request) -->
                <x-ui.select
                    label="Pilih Barber"
                    name="barber_membership_id"
                    placeholder="Siapa saja (Tercepat)"
                    icon="users"
                    direction="up"
                    :options="$barberOptions"
                    search-placeholder="Cari nama barber..."
                />

                <!-- Waktu Kedatangan -->
                <x-ui.input
                    label="Tanggal & Jam Kedatangan *"
                    name="scheduled_at"
                    type="datetime-local"
                    :value="old('scheduled_at', now()->addHours(2)->format('Y-m-d\TH:00'))"
                    required
                />

                <!-- Catatan Gaya Rambut -->
                <x-ui.input
                    label="Catatan Gaya Rambut (Opsional)"
                    name="notes"
                    :value="old('notes')"
                    placeholder="Contoh: Fade 0.5 samping, atas tekstur crop"
                />

                <!-- Tombol Submit -->
                <div class="pt-2">
                    <x-ui.button type="submit" size="lg" class="w-full">
                        <span>Konfirmasi Jadwal Booking</span>
                        <x-ui.icon name="arrow-right" class="w-4 h-4" />
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <script>
        (function () {
            // Switcher Barbershop: Muat ulang halaman via GET saat barbershop berganti (hindari 404 & POST premature)
            const tenantSelectEl = document.querySelector('[data-booking-tenant-select="true"]');
            if (tenantSelectEl) {
                tenantSelectEl.addEventListener('click', (e) => {
                    const opt = e.target.closest('[data-select-option]');
                    if (opt) {
                        const targetId = opt.getAttribute('data-value');
                        const currentId = '{{ (string) $selectedTenant?->id }}';
                        if (targetId && targetId !== currentId) {
                            window.location.href = "{{ route('customer.booking') }}?tenant_id=" + encodeURIComponent(targetId);
                        }
                    }
                });
            }

            // Switcher Cabang: Muat ulang halaman saat cabang berganti agar daftar barber disesuaikan dengan cabang tersebut
            const branchSelectEl = document.querySelector('[data-booking-branch-select="true"]');
            if (branchSelectEl) {
                branchSelectEl.addEventListener('click', (e) => {
                    const opt = e.target.closest('[data-select-option]');
                    if (opt) {
                        const targetBranchId = opt.getAttribute('data-value');
                        const currentBranchId = '{{ (string) $selectedBranchId }}';
                        if (targetBranchId && targetBranchId !== currentBranchId) {
                            window.location.href = "{{ route('customer.booking') }}?tenant_id={{ $selectedTenant?->id }}&branch_id=" + encodeURIComponent(targetBranchId);
                        }
                    }
                });
            }
            function syncCustomerBookingColorPicker() {
                const form = document.querySelector('form');
                if (!form) return;

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

                syncBookingLivePreview(form);
            }

            function syncBookingLivePreview(form) {
                const colorSelect = form.querySelector('[data-ui-select][data-input-name="hair_color_id"]');
                const previewBox = form.querySelector('[data-color-live-preview]');
                if (!colorSelect || !previewBox) return;

                const activeOpt = colorSelect.querySelector('[data-select-option][aria-selected="true"]')
                    || colorSelect.querySelector('[data-select-option]:not(.hidden)');
                if (!activeOpt) return;

                const color = activeOpt.getAttribute('data-color') || '';
                const label = activeOpt.getAttribute('data-label') || '';

                const swatch = previewBox.querySelector('[data-preview-swatch]');
                const name = previewBox.querySelector('[data-preview-name]');

                if (swatch && color) swatch.style.backgroundColor = color;
                if (name && label) name.textContent = label;
            }

            document.addEventListener('click', () => {
                setTimeout(syncCustomerBookingColorPicker, 50);
            });

            document.addEventListener('DOMContentLoaded', syncCustomerBookingColorPicker);
        })();
    </script>
</x-layouts.customer>
