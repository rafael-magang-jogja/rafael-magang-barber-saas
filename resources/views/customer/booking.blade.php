<x-layouts.customer title="Booking Jadwal" subtitle="BarberOS Pelanggan" :tenant="$tenant">
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
            'sublabel' => $s->category?->name,
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
                                @style(['background-color: ' . ($firstColor?->hex_code ?? '#B45309')])
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

                <!-- Pemilihan Tanggal & Jam Kedatangan Terpisah -->
                <div class="space-y-3 p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80">
                    <div class="flex items-center justify-between gap-2">
                        <label for="booking_scheduled_date" class="block text-xs font-bold text-zinc-700">
                            Pilih Tanggal Kedatangan <span class="text-rose-500 font-bold ml-0.5">*</span>
                        </label>
                        <span id="booking-day-badge" class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-800 text-[11px] font-extrabold border border-amber-500/30">
                            {{ $initialSlots['day_name'] ?? now()->addDay()->translatedFormat('l') }}
                        </span>
                    </div>

                    <div class="relative">
                        <input
                            type="date"
                            id="booking_scheduled_date"
                            name="scheduled_date"
                            min="{{ date('Y-m-d') }}"
                            max="{{ now()->addDays(60)->format('Y-m-d') }}"
                            value="{{ old('scheduled_date', $initialDate ?? now()->addDay()->format('Y-m-d')) }}"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-300 text-xs font-bold text-zinc-900 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition shadow-2xs cursor-pointer"
                            required
                        />
                    </div>

                    <!-- Status Info Operasional Cabang / Barber -->
                    <div id="booking-schedule-status-alert" class="{{ empty($initialSlots['is_open']) ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-white border-zinc-200 text-zinc-700' }} p-3 rounded-xl border text-xs space-y-1">
                        <div class="flex items-center gap-2 font-extrabold text-[11px]">
                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span id="booking-schedule-message">{{ $initialSlots['message'] ?? 'Memuat jam operasional...' }}</span>
                        </div>
                        <p class="text-[10px] text-zinc-500 leading-relaxed">
                            Aturan booking: Minimal 15 menit setelah buka &amp; sebelum tutup, tidak bisa booking di jam istirahat atau 1 jam sebelum istirahat &amp; tutup shift.
                        </p>
                    </div>

                    <!-- Pemilihan Jam Kedatangan (Slot Jam Interaktif) -->
                    <div class="space-y-2 pt-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-zinc-700">
                                Pilih Jam Kedatangan <span class="text-rose-500 font-bold ml-0.5">*</span>
                            </label>
                            <span id="selected-time-display" class="text-[11px] font-extrabold text-amber-600">
                                {{ old('scheduled_time') ? 'Pilihan: ' . old('scheduled_time') : (old('scheduled_at') ? 'Pilihan: ' . substr(str_replace('T', ' ', old('scheduled_at')), 11, 5) : 'Belum memilih jam') }}
                            </span>
                        </div>

                        <!-- Grid Slot Jam -->
                        <div id="booking-slots-container" class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-52 overflow-y-auto p-1 rounded-xl">
                            @if(!empty($initialSlots['slots']))
                                @php
                                    $currentSelectedTime = old('scheduled_time') ?? (old('scheduled_at') ? substr(str_replace('T', ' ', old('scheduled_at')), 11, 5) : null);
                                @endphp
                                @foreach($initialSlots['slots'] as $slot)
                                    @php
                                        $isSelected = $currentSelectedTime === $slot['time'];
                                    @endphp
                                    <button
                                        type="button"
                                        data-slot-btn="{{ $slot['time'] }}"
                                        data-available="{{ $slot['is_available'] ? '1' : '0' }}"
                                        @disabled(!$slot['is_available'])
                                        class="slot-btn py-2.5 px-2 rounded-xl text-xs text-center transition flex items-center justify-center border {{ $slot['is_available'] ? ($isSelected ? 'bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer' : 'bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer') : 'bg-zinc-100 text-zinc-400 border-zinc-200/60 opacity-50 cursor-not-allowed' }}"
                                        title="{{ $slot['is_available'] ? 'Klik untuk memilih jam ' . $slot['time'] : ($slot['reason'] ?? 'Slot tidak tersedia') }}"
                                    >
                                        <span class="num font-extrabold text-xs">{{ $slot['time'] }}</span>
                                    </button>
                                @endforeach
                            @else
                                <div class="col-span-full py-4 text-center text-xs text-zinc-400 font-medium">
                                    Tidak ada slot jam yang tersedia pada tanggal ini.
                                </div>
                            @endif
                        </div>

                        <!-- Input Hidden untuk Jam & Gabungan Datetime -->
                        <input
                            type="hidden"
                            id="booking_scheduled_time"
                            name="scheduled_time"
                            value="{{ old('scheduled_time') ?? (old('scheduled_at') ? substr(str_replace('T', ' ', old('scheduled_at')), 11, 5) : '') }}"
                            required
                        />
                        <input
                            type="hidden"
                            id="booking_scheduled_at"
                            name="scheduled_at"
                            value="{{ old('scheduled_at') }}"
                            required
                        />

                        @error('scheduled_at')
                            <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Catatan Gaya Rambut -->
                <x-ui.input
                    label="Catatan Gaya Rambut (Opsional)"
                    name="notes"
                    :value="old('notes')"
                    placeholder="Contoh: Fade 0.5 samping, atas tekstur crop"
                />

                <!-- Opsi Penggunaan Saldo Dompet Customer (Jika Memiliki Saldo) -->
                @if(auth()->check() && auth()->user()->wallet_balance > 0)
                    <div class="p-3.5 rounded-2xl bg-zinc-950 text-white border border-amber-500/50 shadow-md space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-amber-500 text-zinc-950 grid place-items-center font-black shrink-0">
                                    <x-ui.icon name="wallet" class="w-3.5 h-3.5" />
                                </div>
                                <span class="text-xs font-black text-amber-400">Gunakan Saldo Dompet</span>
                            </div>
                            <span class="text-xs font-black text-white num">Tersedia: Rp{{ number_format(auth()->user()->wallet_balance, 0, ',', '.') }}</span>
                        </div>
                        <label class="flex items-center gap-2.5 cursor-pointer bg-zinc-900/90 hover:bg-zinc-800 p-2.5 rounded-xl border border-zinc-700/80 transition">
                            <input
                                type="checkbox"
                                name="use_wallet"
                                value="1"
                                checked
                                class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500 border-zinc-600 bg-zinc-900"
                            />
                            <span class="text-xs text-zinc-300 font-medium">Potong langsung dari Saldo Dompet (Jika saldo mencukupi, booking langsung LUNAS instan tanpa scan QRIS).</span>
                        </label>
                    </div>
                @endif

                <!-- Info Pembayaran Langsung Booking -->
                <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200/80 text-xs text-amber-950 flex items-start gap-2.5 shadow-2xs">
                    <x-ui.icon name="qr-code" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        <p class="font-extrabold text-amber-950">Pembayaran Reservasi via QRIS</p>
                        <p class="text-[11px] text-amber-900 leading-relaxed">
                            Setelah konfirmasi, Anda akan langsung diarahkan ke pembayaran <b>QRIS resmi</b> (m-Banking &amp; E-Wallet) untuk menyelesaikan reservasi.
                        </p>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="pt-1 space-y-2">
                    <x-ui.button type="submit" size="lg" class="w-full text-xs font-black shadow-md">
                        <span>Konfirmasi &amp; Lanjut Bayar</span>
                        <x-ui.icon name="arrow-right" class="w-4 h-4" />
                    </x-ui.button>

                    <x-ui.button
                        variant="outline"
                        size="md"
                        :href="route('customer.dashboard')"
                        class="w-full text-xs font-bold">
                        <x-ui.icon name="arrow-left" class="w-4 h-4" />
                        <span>Batal / Kembali ke Dashboard</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    @php
        $selectedTenantIdStr = (string) $selectedTenant?->id;
        $selectedBranchIdStr = (string) $selectedBranchId;
    @endphp
    <script
        id="customer-booking-script"
        data-tenant-id="{{ $selectedTenantIdStr }}"
        data-branch-id="{{ $selectedBranchIdStr }}"
        data-booking-url="{{ route('customer.booking') }}"
        data-slots-url="{{ route('customer.booking.slots') }}"
    >
        (function () {
            const bookingCfg = document.getElementById('customer-booking-script')?.dataset || {};
            // Switcher Barbershop: Muat ulang halaman via GET saat barbershop berganti (hindari 404 & POST premature)
            const tenantSelectEl = document.querySelector('[data-booking-tenant-select="true"]');
            if (tenantSelectEl) {
                tenantSelectEl.addEventListener('click', (e) => {
                    const opt = e.target.closest('[data-select-option]');
                    if (opt) {
                        const targetId = opt.getAttribute('data-value');
                        const currentId = bookingCfg.tenantId || '';
                        if (targetId && targetId !== currentId) {
                            window.location.href = (bookingCfg.bookingUrl || '/customer/booking') + "?tenant_id=" + encodeURIComponent(targetId);
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
                        const currentBranchId = bookingCfg.branchId || '';
                        if (targetBranchId && targetBranchId !== currentBranchId) {
                            window.location.href = (bookingCfg.bookingUrl || '/customer/booking') + "?tenant_id=" + encodeURIComponent(bookingCfg.tenantId || '') + "&branch_id=" + encodeURIComponent(targetBranchId);
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

            // Slot Time Picker Logic
            const dateInput = document.getElementById('booking_scheduled_date');
            const timeInput = document.getElementById('booking_scheduled_time');
            const atInput = document.getElementById('booking_scheduled_at');
            const slotsContainer = document.getElementById('booking-slots-container');
            const dayBadge = document.getElementById('booking-day-badge');
            const statusAlert = document.getElementById('booking-schedule-status-alert');
            const statusMsg = document.getElementById('booking-schedule-message');
            const selectedDisplay = document.getElementById('selected-time-display');

            function syncCombinedDatetime() {
                const dateVal = dateInput?.value || '';
                const timeVal = timeInput?.value || '';
                if (dateVal && timeVal) {
                    if (atInput) atInput.value = `${dateVal} ${timeVal}:00`;
                    if (selectedDisplay) selectedDisplay.textContent = `Pilihan: ${timeVal}`;
                } else {
                    if (atInput) atInput.value = '';
                    if (selectedDisplay) selectedDisplay.textContent = 'Belum memilih jam';
                }
            }

            function selectSlot(timeStr, btn) {
                if (!btn || btn.disabled || btn.getAttribute('data-available') !== '1') return;

                document.querySelectorAll('#booking-slots-container .slot-btn').forEach((b) => {
                    if (b.getAttribute('data-available') === '1') {
                        b.className = 'slot-btn py-2 px-1.5 rounded-xl text-xs text-center transition flex flex-col items-center justify-center border bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer';
                    }
                });

                btn.className = 'slot-btn py-2 px-1.5 rounded-xl text-xs text-center transition flex flex-col items-center justify-center border bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer';

                if (timeInput) timeInput.value = timeStr;
                syncCombinedDatetime();
            }

            if (slotsContainer) {
                slotsContainer.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-slot-btn]');
                    if (btn) {
                        const time = btn.getAttribute('data-slot-btn');
                        selectSlot(time, btn);
                    }
                });
            }

            async function fetchAvailableSlots() {
                const branchId = bookingCfg.branchId || '';
                const dateVal = dateInput?.value;
                if (!branchId || !dateVal) return;

                const barberSelect = document.querySelector('input[name="barber_membership_id"]');
                const barberId = barberSelect?.value || '';

                const serviceSelect = document.querySelectorAll('input[name="service_ids[]"]:checked, input[name="service_ids[]"][type="hidden"]');
                const serviceParams = Array.from(serviceSelect).map(s => `service_ids[]=${encodeURIComponent(s.value)}`).join('&');

                const url = `${bookingCfg.slotsUrl || '/customer/booking/slots'}?branch_id=${encodeURIComponent(branchId)}&date=${encodeURIComponent(dateVal)}&barber_id=${encodeURIComponent(barberId)}&${serviceParams}`;

                try {
                    if (slotsContainer) {
                        slotsContainer.innerHTML = '<div class="col-span-full py-4 text-center text-xs text-zinc-400 font-medium animate-pulse">Memeriksa ketersediaan jam...</div>';
                    }

                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('Gagal memuat slot jam');

                    const data = await res.json();

                    if (dayBadge && data.day_name) {
                        dayBadge.textContent = data.day_name;
                    }

                    if (statusMsg) {
                        statusMsg.textContent = data.message || (data.is_open ? 'Cabang buka normal' : 'Cabang tutup');
                    }

                    if (statusAlert) {
                        if (!data.is_open) {
                            statusAlert.className = 'p-3 rounded-xl border text-xs space-y-1 bg-rose-50 border-rose-200 text-rose-800';
                        } else {
                            statusAlert.className = 'p-3 rounded-xl border text-xs space-y-1 bg-white border-zinc-200 text-zinc-700';
                        }
                    }

                    if (slotsContainer) {
                        slotsContainer.innerHTML = '';
                        if (!data.slots || data.slots.length === 0) {
                            slotsContainer.innerHTML = `<div class="col-span-full py-4 text-center text-xs text-zinc-400 font-medium">${escapeHtml(data.message || 'Tidak ada slot jam yang tersedia.')}</div>`;
                            if (timeInput) timeInput.value = '';
                            syncCombinedDatetime();
                            return;
                        }

                        const currentVal = timeInput?.value;
                        let matched = false;

                        data.slots.forEach(slot => {
                            const isSelected = currentVal === slot.time && slot.is_available;
                            if (isSelected) matched = true;

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.setAttribute('data-slot-btn', slot.time);
                            btn.setAttribute('data-available', slot.is_available ? '1' : '0');
                            btn.disabled = !slot.is_available;
                            btn.className = `slot-btn py-2.5 px-2 rounded-xl text-xs text-center transition flex items-center justify-center border ${
                                slot.is_available
                                    ? (isSelected ? 'bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer' : 'bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer')
                                    : 'bg-zinc-100 text-zinc-400 border-zinc-200/60 opacity-50 cursor-not-allowed'
                            }`;
                            btn.title = slot.is_available ? `Klik untuk memilih jam ${slot.time}` : (slot.reason || 'Slot tidak tersedia');

                            btn.innerHTML = `<span class="num font-extrabold text-xs">${escapeHtml(slot.time)}</span>`;

                            slotsContainer.appendChild(btn);
                        });

                        if (!matched) {
                            if (timeInput) timeInput.value = '';
                        }
                        syncCombinedDatetime();
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            if (dateInput) {
                dateInput.addEventListener('change', fetchAvailableSlots);
            }

            // Sync saat barber diganti
            const barberSelectRoot = document.querySelector('[data-ui-select][data-input-name="barber_membership_id"]');
            if (barberSelectRoot) {
                barberSelectRoot.addEventListener('click', (e) => {
                    if (e.target.closest('[data-select-option]')) {
                        setTimeout(fetchAvailableSlots, 100);
                    }
                });
            }

            function escapeHtml(str) {
                if (!str) return '';
                const p = document.createElement('p');
                p.textContent = str;
                return p.innerHTML;
            }

            // Inisialisasi awal
            syncCombinedDatetime();
        })();
    </script>
</x-layouts.customer>
