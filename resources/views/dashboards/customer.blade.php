@php
$selectedBranch = null;
if (request()->filled('branch')) {
    $selectedBranch = $branches->firstWhere('code', request('branch'));
} elseif (request()->filled('branch_id')) {
    $selectedBranch = $branches->firstWhere('id', (int) request('branch_id'));
}
$selectedBranch = $selectedBranch ?? $branches->first();
$selectedBranchId = $selectedBranch?->id;

$customerBranchOptions = $branches->map(fn ($b) => [
    'value' => (string) $b->id,
    'label' => $b->name,
    'sublabel' => $b->address,
])->all();

$customerServiceOptions = $services->map(fn ($s) => [
    'value' => (string) $s->id,
    'label' => $s->name . ' — Rp' . number_format($s->price, 0, ',', '.'),
    'sublabel' => ($s->category ? $s->category->name . ' · ' : '') . $s->duration_minutes . ' menit',
])->all();

$customerBarberOptions = $barbers->map(fn ($b) => [
    'value' => (string) $b->id,
    'label' => $b->display_name . ' (' . number_format($b->rating_avg, 1) . ' / 5.0)',
    'sublabel' => $b->specialty,
])->all();

$customerColorOptions = isset($hairColors) ? $hairColors->map(fn ($c) => [
    'value' => (string) $c->id,
    'label' => $c->name,
    'color' => $c->hex_code,
])->all() : [];

$firstCustomerColor = isset($hairColors) ? $hairColors->first() : null;

$firstCustomerServiceIsColoring = $services->first()
    && (stripos($services->first()->name, 'color') !== false || stripos($services->first()->name, 'warna') !== false || ($services->first()->category && (stripos($services->first()->category->name, 'warna') !== false || stripos($services->first()->category->name, 'color') !== false)));

$defaultTab = old('scheduled_at') || $errors->has('scheduled_at') || $errors->has('booking') || request('tab') === 'booking' ? 'booking' : 'queue';
@endphp

<x-layouts.customer :title="$tenant->name" :subtitle="$selectedBranch?->name ?? 'Layanan Pelanggan'" :tenant="$tenant">
    <div class="space-y-4">
        @if($tenant->isSubscriptionLocked())
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-3">
                <x-ui.icon name="lock" class="w-5 h-5 text-amber-400 shrink-0" />
                <div>
                    <strong class="font-bold block text-sm text-white">Layanan Online Sementara Dinonaktifkan</strong>
                    <span class="text-zinc-300">Barbershop ini sedang dalam penyesuaian layanan operasional. Pengambilan antrean & booking online dinonaktifkan sementara.</span>
                </div>
            </div>
        @endif

        <!-- Info Outlet & Sambutan -->
        <div class="rounded-2xl bg-zinc-950 text-white p-5 shadow-lg border border-zinc-800">
            <div class="flex items-center justify-between gap-2">
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold">
                    @if($tenant->logo_url)
                        <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-4 h-4 rounded-md object-cover" />
                    @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                        <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ $tenant->name }}" class="w-4 h-4 rounded-md object-cover" />
                    @else
                        <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-3.5 h-3.5" />
                    @endif
                    <span>{{ $tenant->name }}</span>
                </div>
                @if(!empty($selectedBranch?->maps_url))
                <a
                    href="{{ $selectedBranch->maps_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-xs text-amber-400 hover:text-amber-300 font-bold inline-flex items-center gap-1 bg-white/10 hover:bg-white/15 px-2.5 py-1 rounded-xl transition">
                    <span>Google Maps</span>
                    <x-ui.icon name="external-link" class="w-3 h-3" />
                </a>
                @endif
            </div>

            <h1 class="text-xl font-extrabold tracking-tight mt-3">Mau tampil fresh hari ini?</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ $selectedBranch?->name }} · {{ $selectedBranch?->address ?? 'Pusat layanan pangkas rambut & perawatan pria terbaik' }}
            </p>
        </div>

        <!-- Banner Status Login Pelanggan -->
        @auth
            <div class="p-3 rounded-2xl bg-white border border-zinc-200/80 flex items-center justify-between gap-3 text-xs shadow-2xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-600 grid place-items-center shrink-0">
                        <x-ui.icon name="check" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="font-extrabold text-zinc-900 truncate">Halo, {{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-zinc-500 truncate">{{ auth()->user()->phone ?? auth()->user()->email }}</p>
                    </div>
                </div>
                <a
                    href="{{ route('customer.dashboard') }}"
                    class="text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:underline shrink-0 inline-flex items-center gap-1"
                >
                    <span>Dashboard</span>
                    <x-ui.icon name="arrow-right" class="w-3 h-3" />
                </a>
            </div>
        @else
            <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200/90 flex items-center justify-between gap-3 text-xs shadow-2xs">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 grid place-items-center shrink-0">
                        <x-ui.icon name="sparkles" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="font-bold text-amber-950 truncate">Punya akun pelanggan di {{ $tenant->name }}?</p>
                        <p class="text-[11px] text-amber-700 truncate">Masuk untuk simpan riwayat &amp; pantau antrean</p>
                    </div>
                </div>
                <a
                    href="{{ route('tenant.login', $tenant->slug) }}"
                    class="px-3.5 py-1.5 rounded-xl font-black text-xs text-zinc-950 shrink-0 shadow-2xs transition hover:brightness-105"
                    style="background-color: {{ $tenant->brand_color ?? '#F59E0B' }};"
                >
                    Masuk
                </a>
            </div>
        @endauth

        @if($activeTicket)
        <div class="rounded-3xl bg-white shadow-xl p-4 border border-zinc-100">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold tracking-wider text-zinc-400">ANTREAN AKTIF</span>
                <x-ui.badge :status="$activeTicket->status" />
            </div>
            <div class="flex items-end justify-between mt-2">
                <p class="text-5xl font-extrabold num">{{ $activeTicket->number }}</p>
                <div class="text-right">
                    <p class="text-xs text-zinc-400">Estimasi</p>
                    <p class="text-lg font-bold num">~{{ $activeTicket->estimated_duration_minutes }} mnt</p>
                </div>
            </div>
            <p class="text-xs text-zinc-500 mt-2">
                Barber: <b class="text-zinc-900">{{ $activeTicket->barber?->display_name ?? 'Siapa saja' }}</b>
                · {{ $activeTicket->items->pluck('service_name')->join(', ') }}
                @if($activeTicket->hairColor)
                · <span class="inline-flex items-center gap-1 font-bold text-zinc-900"><span class="w-2.5 h-2.5 rounded-full border border-black/15 inline-block" style="background-color: {{ $activeTicket->hairColor->hex_code }}"></span>{{ $activeTicket->hairColor->name }}</span>
                @endif
            </p>
        </div>
        @endif

        <div class="w-full space-y-3">
            <!-- Tab Switcher Antrean vs Booking -->
            <div class="grid grid-cols-2 gap-1.5 p-1 rounded-2xl bg-zinc-200/80 text-xs font-bold shadow-inner">
                <button
                    type="button"
                    data-portal-tab-btn="queue"
                    class="py-2.5 px-3 rounded-xl transition-all duration-200 flex items-center justify-center gap-1.5 cursor-pointer {{ $defaultTab === 'queue' ? 'bg-white text-zinc-950 shadow-sm font-extrabold' : 'text-zinc-600 hover:text-zinc-900' }}"
                >
                    <x-ui.icon name="ticket" class="w-4 h-4 text-amber-500" />
                    <span>Antrean Hari Ini</span>
                </button>
                <button
                    type="button"
                    data-portal-tab-btn="booking"
                    class="py-2.5 px-3 rounded-xl transition-all duration-200 flex items-center justify-center gap-1.5 cursor-pointer {{ $defaultTab === 'booking' ? 'bg-white text-zinc-950 shadow-sm font-extrabold' : 'text-zinc-600 hover:text-zinc-900' }}"
                >
                    <x-ui.icon name="calendar" class="w-4 h-4 text-blue-500" />
                    <span>Booking Jadwal</span>
                </button>
            </div>

            <!-- PANEL 1: Ambil Antrean Langsung (Walk-In) -->
            <div data-portal-panel="queue" class="{{ $defaultTab === 'queue' ? '' : 'hidden' }}">
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-bold text-sm flex items-center gap-2">
                            <x-ui.icon name="ticket" class="w-4 h-4 text-amber-500" />
                            <span>Ambil Nomor Antrean Sekarang</span>
                        </h2>
                    </x-slot:header>

                    <form method="POST" action="{{ route('customer.queue.store', $tenant->slug) }}" class="space-y-3">
                        @csrf
                        @if($branches->count() > 1)
                        <x-ui.select
                            label="Pilih Cabang / Outlet *"
                            name="branch_id"
                            icon="store"
                            :options="$customerBranchOptions"
                            :value="$selectedBranchId"
                            data-branch-switcher="true"
                            search-placeholder="Cari cabang..." />
                        @else
                        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}" />
                        @endif

                        <x-ui.input label="Nama Anda" name="customer_name" :value="auth()->user()?->name ?? old('customer_name')" placeholder="Rafael" required />
                        <x-ui.input label="Nomor WhatsApp / HP" name="customer_phone" :value="auth()->user()?->phone ?? old('customer_phone')" placeholder="0812..." required />

                        <x-ui.select
                            label="Pilih Layanan (Bisa Lebih dari 1)"
                            name="service_ids[]"
                            icon="scissors"
                            multiple
                            :options="$customerServiceOptions"
                            search-placeholder="Cari layanan..." />

                        @if(!empty($customerColorOptions))
                        <div data-coloring-picker class="{{ $firstCustomerServiceIsColoring ? '' : 'hidden' }} space-y-2">
                            <x-ui.select
                                label="Pilih Warna Coloring"
                                name="hair_color_id"
                                :options="$customerColorOptions"
                                search-placeholder="Cari warna rambut..." />

                            <!-- Live Preview Warna Customer -->
                            <div data-color-live-preview class="flex items-center gap-3 p-2.5 rounded-2xl bg-zinc-50 border border-zinc-200/90 shadow-2xs">
                                <span
                                    data-preview-swatch
                                    class="w-8 h-8 rounded-xl border border-black/15 shadow-2xs shrink-0 transition"
                                    style="background-color: {{ $firstCustomerColor?->hex_code ?? '#B45309' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Warna Pilihan:</span>
                                    <span data-preview-name class="text-xs font-extrabold text-zinc-900 block">{{ $firstCustomerColor?->name ?? 'Pilih Warna' }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        <x-ui.select
                            label="Pilih Barber"
                            name="barber_membership_id"
                            placeholder="Siapa saja (Tercepat)"
                            icon="users"
                            direction="up"
                            :options="$customerBarberOptions"
                            search-placeholder="Cari nama barber..." />

                        <x-ui.input label="Catatan Gaya Rambut (Opsional)" name="notes" placeholder="Tipis samping, rapi atas" />

                        <div class="space-y-2 pt-1">
                            @if($tenant->isSubscriptionLocked())
                                <button type="button" disabled class="w-full py-3 rounded-2xl bg-zinc-800 text-zinc-500 font-extrabold text-sm cursor-not-allowed">
                                    Layanan Antrean Sementara Ditutup
                                </button>
                            @else
                                <x-ui.button type="submit" class="w-full">
                                    <span>Ambil Nomor Antrean</span>
                                    <x-ui.icon name="arrow-right" class="w-4 h-4" />
                                </x-ui.button>
                            @endif

                            <x-ui.button
                                variant="outline"
                                size="md"
                                :href="auth()->check() ? route('customer.dashboard') : route('customer.portal', $tenant->slug)"
                                class="w-full">
                                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                                <span>Batal Memesan / Kembali ke Dashboard</span>
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            <!-- PANEL 2: Booking Jadwal (Reservasi) -->
            <div data-portal-panel="booking" class="{{ $defaultTab === 'booking' ? '' : 'hidden' }}">
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-bold text-sm flex items-center gap-2">
                            <x-ui.icon name="calendar" class="w-4 h-4 text-blue-500" />
                            <span>Reservasi Jadwal Potong Rambut</span>
                        </h2>
                    </x-slot:header>

                    <form method="POST" action="{{ route('customer.tenant-booking.store', $tenant->slug) }}" class="space-y-3">
                        @csrf
                        @if($branches->count() > 1)
                        <x-ui.select
                            label="Pilih Cabang / Outlet *"
                            name="branch_id"
                            icon="store"
                            :options="$customerBranchOptions"
                            :value="$selectedBranchId"
                            data-branch-switcher="true"
                            search-placeholder="Cari cabang..." />
                        @else
                        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}" />
                        @endif

                        <x-ui.input
                            type="datetime-local"
                            label="Tanggal & Jam Kedatangan *"
                            name="scheduled_at"
                            :value="old('scheduled_at')"
                            min="{{ now()->format('Y-m-d\TH:i') }}"
                            required />

                        <x-ui.input label="Nama Lengkap Anda *" name="customer_name" :value="auth()->user()?->name ?? old('customer_name')" placeholder="Rafael" required />
                        <x-ui.input label="Nomor WhatsApp / HP *" name="customer_phone" :value="auth()->user()?->phone ?? old('customer_phone')" placeholder="0812..." required />

                        <x-ui.select
                            label="Pilih Layanan (Bisa Lebih dari 1)"
                            name="service_ids[]"
                            icon="scissors"
                            multiple
                            :options="$customerServiceOptions"
                            search-placeholder="Cari layanan..." />

                        @if(!empty($customerColorOptions))
                        <div data-coloring-picker class="{{ $firstCustomerServiceIsColoring ? '' : 'hidden' }} space-y-2">
                            <x-ui.select
                                label="Pilih Warna Coloring"
                                name="hair_color_id"
                                :options="$customerColorOptions"
                                search-placeholder="Cari warna rambut..." />

                            <!-- Live Preview Warna Customer -->
                            <div data-color-live-preview class="flex items-center gap-3 p-2.5 rounded-2xl bg-zinc-50 border border-zinc-200/90 shadow-2xs">
                                <span
                                    data-preview-swatch
                                    class="w-8 h-8 rounded-xl border border-black/15 shadow-2xs shrink-0 transition"
                                    style="background-color: {{ $firstCustomerColor?->hex_code ?? '#B45309' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Warna Pilihan:</span>
                                    <span data-preview-name class="text-xs font-extrabold text-zinc-900 block">{{ $firstCustomerColor?->name ?? 'Pilih Warna' }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        <x-ui.select
                            label="Pilih Barber (Opsional)"
                            name="barber_membership_id"
                            placeholder="Siapa saja yang bertugas"
                            icon="users"
                            direction="up"
                            :options="$customerBarberOptions"
                            search-placeholder="Cari nama barber..." />

                        <x-ui.input label="Catatan Tambahan (Opsional)" name="notes" placeholder="Contoh: Booking untuk 2 orang / request barber tertentu" />

                        <div class="space-y-2 pt-1">
                            @if($tenant->isSubscriptionLocked())
                                <button type="button" disabled class="w-full py-3 rounded-2xl bg-zinc-800 text-zinc-500 font-extrabold text-sm cursor-not-allowed">
                                    Layanan Booking Sementara Ditutup
                                </button>
                            @else
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl text-white font-extrabold text-sm shadow-md transition hover:brightness-110 active:scale-[0.98] cursor-pointer"
                                    style="background-color: {{ $tenant->brand_color ?? '#2563EB' }};"
                                >
                                    <x-ui.icon name="calendar" class="w-4 h-4" />
                                    <span>Konfirmasi Jadwal Booking</span>
                                </button>
                            @endif

                            <x-ui.button
                                variant="outline"
                                size="md"
                                :href="auth()->check() ? route('customer.dashboard') : route('customer.portal', $tenant->slug)"
                                class="w-full">
                                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                                <span>Batal / Kembali ke Halaman Utama</span>
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>
        </div>
    </div>

    <script>
        (function() {
            // Tab Switcher Controller
            const tabBtns = document.querySelectorAll('[data-portal-tab-btn]');
            const panels = document.querySelectorAll('[data-portal-panel]');

            tabBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = btn.getAttribute('data-portal-tab-btn');
                    panels.forEach((p) => {
                        p.classList.toggle('hidden', p.getAttribute('data-portal-panel') !== target);
                    });
                    tabBtns.forEach((b) => {
                        const isActive = b.getAttribute('data-portal-tab-btn') === target;
                        b.classList.toggle('bg-white', isActive);
                        b.classList.toggle('text-zinc-950', isActive);
                        b.classList.toggle('shadow-sm', isActive);
                        b.classList.toggle('font-extrabold', isActive);
                        b.classList.toggle('text-zinc-600', !isActive);
                    });
                    syncCustomerColorPickers();
                });
            });

            function syncCustomerColorPickers() {
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

                    syncCustomerLivePreview(form);
                });
            }

            function syncCustomerLivePreview(form) {
                const colorSelect = form.querySelector('[data-ui-select][data-input-name="hair_color_id"]');
                const previewBox = form.querySelector('[data-color-live-preview]');
                if (!colorSelect || !previewBox) return;

                const activeOpt = colorSelect.querySelector('[data-select-option][aria-selected="true"]') ||
                    colorSelect.querySelector('[data-select-option]:not(.hidden)');
                if (!activeOpt) return;

                const color = activeOpt.getAttribute('data-color') || '';
                const label = activeOpt.getAttribute('data-label') || '';

                const swatch = previewBox.querySelector('[data-preview-swatch]');
                const name = previewBox.querySelector('[data-preview-name]');

                if (swatch && color) {
                    swatch.style.backgroundColor = color;
                }
                if (name) {
                    name.textContent = label;
                }
            }

            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-select-option]')) {
                    requestAnimationFrame(syncCustomerColorPickers);
                }

                // Switcher Cabang Outlet di Portal Publik (Reload untuk update daftar barber & info cabang)
                const branchOption = e.target.closest('[data-branch-switcher="true"] [data-select-option]');
                if (branchOption) {
                    const targetBranchId = branchOption.getAttribute('data-value');
                    const currentBranchId = '{{ (string) $selectedBranchId }}';
                    if (targetBranchId && targetBranchId !== currentBranchId) {
                        window.location.href = "{{ route('customer.portal', $tenant->slug) }}?branch_id=" + encodeURIComponent(targetBranchId);
                    }
                }
            });
            syncCustomerColorPickers();
        })();
    </script>
</x-layouts.customer>
