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
    'sublabel' => $s->category?->name,
])->all();

$customerBookingBarberOptions = $barbers->map(fn ($b) => [
    'value' => (string) $b->id,
    'label' => $b->display_name . ' (' . number_format($b->rating_avg, 1) . ' / 5.0)',
    'sublabel' => $b->specialty,
])->all();

$customerWalkinBarbers = isset($walkinBarbers) ? $walkinBarbers : $barbers;
$customerWalkinBarberOptions = $customerWalkinBarbers->map(fn ($b) => [
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
                    class="text-xs text-sky-300 hover:text-sky-200 font-bold inline-flex items-center gap-1 bg-sky-500/15 hover:bg-sky-500/25 border border-sky-400/30 px-2.5 py-1 rounded-xl transition">
                    <span>Google Maps</span>
                    <x-ui.icon name="external-link" class="w-3 h-3 text-sky-300" />
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
                        <p class="text-[11px] text-amber-700 truncate">Masuk untuk booking jadwal &amp; simpan riwayat</p>
                    </div>
                </div>
                <a
                    href="{{ $tenant->loginUrl() }}"
                    class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 font-black text-xs text-zinc-950 shrink-0 shadow-2xs transition"
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
            <div class="mt-2">
                <p class="text-5xl font-extrabold num">{{ $activeTicket->number }}</p>
            </div>
            <p class="text-xs text-zinc-500 mt-2">
                Barber: <b class="text-zinc-900">{{ $activeTicket->barber?->display_name ?? 'Siapa saja' }}</b>
                · {{ $activeTicket->items->pluck('service_name')->join(', ') }}
                @if($activeTicket->hairColor)
                · <span class="inline-flex items-center gap-1 font-bold text-zinc-900"><span class="w-2.5 h-2.5 rounded-full border border-black/15 inline-block" @style(['background-color: ' . $activeTicket->hairColor->hex_code])></span>{{ $activeTicket->hairColor->name }}</span>
                @endif
            </p>
        </div>
        @endif

        <div class="w-full space-y-3">
            <!-- Live Monitor Antrean Toko -->
            @if(!empty($branchQueueStats))
                <div class="p-3.5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs space-y-2">
                    <div class="flex items-center text-xs">
                        <span class="font-extrabold text-zinc-900 flex items-center gap-1.5">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span>Live Antrean {{ \Illuminate\Support\Str::startsWith(strtolower($selectedBranch?->name ?? ''), 'cabang') ? $selectedBranch?->name : 'Cabang ' . $selectedBranch?->name }}</span>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-center pt-1 border-t border-zinc-100">
                        <div class="p-2 rounded-xl bg-zinc-50 border border-zinc-100">
                            <p class="text-[10px] text-zinc-400 font-semibold uppercase">Sedang Cukur</p>
                            <p class="text-sm font-black text-zinc-900 num mt-0.5">
                                {{ $branchQueueStats['servingNumber'] ?? '—' }}
                            </p>
                        </div>
                        <div class="p-2 rounded-xl bg-zinc-50 border border-zinc-100">
                            <p class="text-[10px] text-zinc-400 font-semibold uppercase">Menunggu</p>
                            <p class="text-sm font-black text-amber-600 num mt-0.5">
                                {{ $branchQueueStats['waitingCount'] ?? 0 }} orang
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab Switcher Antrean vs Booking -->
            <div class="grid grid-cols-2 gap-1 p-1 rounded-2xl bg-zinc-100/90 border border-zinc-200/80 text-xs font-bold shadow-2xs">
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
                    @guest
                        <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-900 font-extrabold shrink-0">Wajib Login</span>
                    @endguest
                </button>
            </div>

            <!-- PANEL 1: Ambil Antrean Langsung (Walk-In) -->
            @php
                $isBranchCurrentlyOpen = !isset($branchOperationalStatus) || ($branchOperationalStatus['is_open'] ?? true);
                $branchClosedReason = $branchOperationalStatus['reason'] ?? null;
            @endphp
            <div data-portal-panel="queue" class="{{ $defaultTab === 'queue' ? '' : 'hidden' }}">
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-bold text-sm flex items-center gap-2">
                            <x-ui.icon name="ticket" class="w-4 h-4 text-amber-500" />
                            <span>Ambil Nomor Antrean Sekarang</span>
                        </h2>
                    </x-slot:header>

                    @if(! $isBranchCurrentlyOpen && !empty($branchClosedReason))
                        <div class="mb-3 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-950 space-y-1">
                            <div class="flex items-center gap-2 font-extrabold text-xs text-rose-800">
                                <x-ui.icon name="clock" class="w-4 h-4 text-rose-600 shrink-0" />
                                <span>Di Luar Jam Operasional Cabang</span>
                            </div>
                            <p class="text-xs text-rose-700 leading-relaxed">
                                {{ $branchClosedReason }}
                            </p>
                            @if(!empty($branchOperationalStatus['hours']['open']) && !empty($branchOperationalStatus['hours']['close']))
                                <div class="mt-2 pt-2 border-t border-rose-200/70 text-[11px] text-rose-800 flex items-center justify-between font-semibold">
                                    <span>Jam Buka Hari Ini:</span>
                                    <span>{{ $branchOperationalStatus['hours']['open'] }} - {{ $branchOperationalStatus['hours']['close'] }} WIB</span>
                                </div>
                            @endif
                        </div>
                    @endif

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

                        <x-ui.input label="Nama Anda" name="customer_name" :value="auth()->user()?->name ?? old('customer_name')" placeholder="Contoh: Budi Santoso" required />
                        <x-ui.input label="Nomor WhatsApp / HP" name="customer_phone" :value="auth()->user()?->phone ?? old('customer_phone')" placeholder="0812..." required />

                        <x-ui.select
                            label="Pilih Layanan (Bisa Lebih dari 1) *"
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
                                    @style(['background-color: ' . ($firstCustomerColor?->hex_code ?? '#B45309')])></span>
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
                            :options="$customerWalkinBarberOptions"
                            search-placeholder="Cari nama barber..." />

                        <x-ui.input label="Catatan Gaya Rambut (Opsional)" name="notes" placeholder="Tipis samping, rapi atas" />

                        <div class="space-y-2 pt-1">
                            @if($tenant->isSubscriptionLocked())
                                <button type="button" disabled class="w-full py-3 rounded-2xl bg-zinc-800 text-zinc-500 font-extrabold text-sm cursor-not-allowed">
                                    Layanan Antrean Sementara Ditutup
                                </button>
                            @elseif(! $isBranchCurrentlyOpen)
                                <button type="button" disabled class="w-full py-3 rounded-2xl bg-zinc-200 text-zinc-500 font-extrabold text-xs cursor-not-allowed border border-zinc-300">
                                    Cabang Tutup / Di Luar Jam Operasional
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
                                :href="route('customer.dashboard')"
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

                    @guest
                        <!-- Khusus Tamu / Guest: Tampilkan prompt login ramah -->
                        <div class="py-5 px-1 text-center space-y-4">
                            <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center mx-auto border border-amber-500/20 shadow-inner">
                                <x-ui.icon name="lock" class="w-7 h-7" />
                            </div>

                            <div class="space-y-1.5 max-w-sm mx-auto">
                                <h3 class="font-black text-zinc-900 text-base">Booking Jadwal Memerlukan Akun</h3>
                                <p class="text-xs text-zinc-600 leading-relaxed">
                                    Reservasi booking jadwal dan pilihan barber hanya dapat dilakukan oleh pelanggan yang telah masuk ke akun.
                                </p>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-left space-y-2.5 max-w-sm mx-auto">
                                <div class="flex items-start gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <x-ui.icon name="ticket" class="w-3 h-3" />
                                    </div>
                                    <span class="text-xs text-zinc-700"><strong>Antrean Walk-In (Hari Ini):</strong> Tetap bisa diambil langsung tanpa harus login.</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <x-ui.icon name="calendar" class="w-3 h-3" />
                                    </div>
                                    <span class="text-xs text-zinc-700"><strong>Booking Reservasi:</strong> Wajib masuk agar jadwal, nomor kontak, &amp; pembayaran tersimpan rapi.</span>
                                </div>
                            </div>

                            <div class="space-y-2 max-w-sm mx-auto pt-2">
                                <a
                                    href="{{ $tenant->loginUrl() }}"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-md transition active:scale-[0.98]"
                                >
                                    <x-ui.icon name="users" class="w-4 h-4" />
                                    <span>Masuk ke Akun Anda</span>
                                </a>

                                <a
                                    href="{{ $tenant->registerUrl() }}"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-white hover:bg-zinc-50 border border-zinc-300 text-zinc-800 font-extrabold text-xs transition"
                                >
                                    <span>Belum Punya Akun? Daftar Akun Baru</span>
                                </a>

                                <button
                                    type="button"
                                    data-switch-to-queue-btn
                                    class="w-full inline-flex items-center justify-center gap-1.5 py-2 text-xs font-bold text-amber-700 hover:text-amber-800 cursor-pointer transition"
                                >
                                    <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                                    <span>Ambil Antrean Hari Ini (Tanpa Login)</span>
                                </button>
                            </div>
                        </div>
                    @else
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

                        <!-- Pemilihan Tanggal & Jam Kedatangan Terpisah -->
                        <div class="space-y-3 p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80">
                            <div class="flex items-center justify-between gap-2">
                                <label for="tenant_scheduled_date" class="block text-xs font-bold text-zinc-700">
                                    Pilih Tanggal Kedatangan <span class="text-rose-500 font-bold ml-0.5">*</span>
                                </label>
                                <span id="tenant-day-badge" class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-800 text-[11px] font-extrabold border border-amber-500/30">
                                    {{ $initialSlots['day_name'] ?? now()->addDay()->translatedFormat('l') }}
                                </span>
                            </div>

                            <div class="relative">
                                <input
                                    type="date"
                                    id="tenant_scheduled_date"
                                    name="scheduled_date"
                                    min="{{ date('Y-m-d') }}"
                                    max="{{ now()->addDays(60)->format('Y-m-d') }}"
                                    value="{{ old('scheduled_date', $initialDate ?? now()->addDay()->format('Y-m-d')) }}"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-300 text-xs font-bold text-zinc-900 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition shadow-2xs cursor-pointer"
                                    required
                                />
                            </div>

                            <!-- Status Info Operasional Cabang -->
                            <div id="tenant-schedule-status-alert" class="{{ empty($initialSlots['is_open']) ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-white border-zinc-200 text-zinc-700' }} p-3 rounded-xl border text-xs space-y-1">
                                <div class="flex items-center gap-2 font-extrabold text-[11px]">
                                    <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                    <span id="tenant-schedule-message">{{ $initialSlots['message'] ?? 'Memuat jam operasional...' }}</span>
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
                                    <span id="tenant-selected-time-display" class="text-[11px] font-extrabold text-amber-600">
                                        {{ old('scheduled_time') ? 'Pilihan: ' . old('scheduled_time') : (old('scheduled_at') ? 'Pilihan: ' . substr(str_replace('T', ' ', old('scheduled_at')), 11, 5) : 'Belum memilih jam') }}
                                    </span>
                                </div>

                                <!-- Grid Slot Jam -->
                                <div id="tenant-slots-container" class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-48 overflow-y-auto p-1 rounded-xl">
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
                                                data-tenant-slot-btn="{{ $slot['time'] }}"
                                                data-available="{{ $slot['is_available'] ? '1' : '0' }}"
                                                @disabled(!$slot['is_available'])
                                                class="tenant-slot-btn py-2.5 px-2 rounded-xl text-xs text-center transition flex items-center justify-center border {{ $slot['is_available'] ? ($isSelected ? 'bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer' : 'bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer') : 'bg-zinc-100 text-zinc-400 border-zinc-200/60 opacity-50 cursor-not-allowed' }}"
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
                                    id="tenant_scheduled_time"
                                    name="scheduled_time"
                                    value="{{ old('scheduled_time') ?? (old('scheduled_at') ? substr(str_replace('T', ' ', old('scheduled_at')), 11, 5) : '') }}"
                                    required
                                />
                                <input
                                    type="hidden"
                                    id="tenant_scheduled_at"
                                    name="scheduled_at"
                                    value="{{ old('scheduled_at') }}"
                                    required
                                />

                                @error('scheduled_at')
                                    <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <x-ui.input label="Nama Lengkap Anda *" name="customer_name" :value="auth()->user()?->name ?? old('customer_name')" placeholder="Contoh: Budi Santoso" required />
                        <x-ui.input label="Nomor WhatsApp / HP *" name="customer_phone" :value="auth()->user()?->phone ?? old('customer_phone')" placeholder="0812..." required />

                        <x-ui.select
                            label="Pilih Layanan (Bisa Lebih dari 1) *"
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
                                    @style(['background-color: ' . ($firstCustomerColor?->hex_code ?? '#B45309')])></span>
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
                            :options="$customerBookingBarberOptions"
                            search-placeholder="Cari nama barber..." />

                        <x-ui.input label="Catatan Tambahan (Opsional)" name="notes" placeholder="Contoh: Booking untuk 2 orang / request barber tertentu" />

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

                        <div class="space-y-2 pt-1">
                            @if($tenant->isSubscriptionLocked())
                                <button type="button" disabled class="w-full py-3 rounded-2xl bg-zinc-800 text-zinc-500 font-extrabold text-sm cursor-not-allowed">
                                    Layanan Booking Sementara Ditutup
                                </button>
                            @else
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black text-xs shadow-md transition active:scale-[0.98] cursor-pointer"
                                >
                                    <span>Konfirmasi &amp; Lanjut Bayar</span>
                                    <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
                                </button>
                            @endif

                            <x-ui.button
                                variant="outline"
                                size="md"
                                :href="route('customer.dashboard')"
                                class="w-full text-xs font-bold">
                                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                                <span>Batal Memesan / Kembali ke Dashboard</span>
                            </x-ui.button>
                        </div>
                    </form>
                    @endguest
                </x-ui.card>
            </div>
        </div>
    </div>

    @php
        $portalBranchIdStr = (string) $selectedBranchId;
        $portalBaseUrl = $tenant->customerPortalUrl();
        $portalSlotsUrl = route('customer.tenant-booking.slots', $tenant->slug);
    @endphp
    <script
        id="customer-portal-script"
        data-branch-id="{{ $portalBranchIdStr }}"
        data-portal-url="{{ $portalBaseUrl }}"
        data-slots-url="{{ $portalSlotsUrl }}"
    >
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

            const switchToQueueBtn = document.querySelector('[data-switch-to-queue-btn]');
            if (switchToQueueBtn) {
                switchToQueueBtn.addEventListener('click', () => {
                    const queueTabBtn = document.querySelector('[data-portal-tab-btn="queue"]');
                    if (queueTabBtn) {
                        queueTabBtn.click();
                    }
                });
            }

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
                    const portalCfg = document.getElementById('customer-portal-script')?.dataset || {};
                    const currentBranchId = portalCfg.branchId || '';
                    if (targetBranchId && targetBranchId !== currentBranchId) {
                        const baseUrl = portalCfg.portalUrl || window.location.pathname;
                        window.location.href = baseUrl + (baseUrl.includes('?') ? '&' : '?') + "branch_id=" + encodeURIComponent(targetBranchId);
                    }
                }
            });
            syncCustomerColorPickers();

            // Tenant Slot Time Picker Logic
            const tenantDateInput = document.getElementById('tenant_scheduled_date');
            const tenantTimeInput = document.getElementById('tenant_scheduled_time');
            const tenantAtInput = document.getElementById('tenant_scheduled_at');
            const tenantSlotsContainer = document.getElementById('tenant-slots-container');
            const tenantDayBadge = document.getElementById('tenant-day-badge');
            const tenantStatusAlert = document.getElementById('tenant-schedule-status-alert');
            const tenantStatusMsg = document.getElementById('tenant-schedule-message');
            const tenantSelectedDisplay = document.getElementById('tenant-selected-time-display');

            function syncTenantCombinedDatetime() {
                const dateVal = tenantDateInput?.value || '';
                const timeVal = tenantTimeInput?.value || '';
                if (dateVal && timeVal) {
                    if (tenantAtInput) tenantAtInput.value = `${dateVal} ${timeVal}:00`;
                    if (tenantSelectedDisplay) tenantSelectedDisplay.textContent = `Pilihan: ${timeVal}`;
                } else {
                    if (tenantAtInput) tenantAtInput.value = '';
                    if (tenantSelectedDisplay) tenantSelectedDisplay.textContent = 'Belum memilih jam';
                }
            }

            function selectTenantSlot(timeStr, btn) {
                if (!btn || btn.disabled || btn.getAttribute('data-available') !== '1') return;

                document.querySelectorAll('#tenant-slots-container .tenant-slot-btn').forEach((b) => {
                    if (b.getAttribute('data-available') === '1') {
                        b.className = 'tenant-slot-btn py-2 px-1.5 rounded-xl text-xs text-center transition flex flex-col items-center justify-center border bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer';
                    }
                });

                btn.className = 'tenant-slot-btn py-2 px-1.5 rounded-xl text-xs text-center transition flex flex-col items-center justify-center border bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer';

                if (tenantTimeInput) tenantTimeInput.value = timeStr;
                syncTenantCombinedDatetime();
            }

            if (tenantSlotsContainer) {
                tenantSlotsContainer.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-tenant-slot-btn]');
                    if (btn) {
                        const time = btn.getAttribute('data-tenant-slot-btn');
                        selectTenantSlot(time, btn);
                    }
                });
            }

            async function fetchTenantAvailableSlots() {
                const portalCfg = document.getElementById('customer-portal-script')?.dataset || {};
                const branchId = portalCfg.branchId || '';
                const dateVal = tenantDateInput?.value;
                if (!branchId || !dateVal) return;

                const barberSelect = document.querySelector('form[action*="booking"] input[name="barber_membership_id"]');
                const barberId = barberSelect?.value || '';

                const serviceSelect = document.querySelectorAll('form[action*="booking"] input[name="service_ids[]"]:checked, form[action*="booking"] input[name="service_ids[]"][type="hidden"]');
                const serviceParams = Array.from(serviceSelect).map(s => `service_ids[]=${encodeURIComponent(s.value)}`).join('&');

                const url = `${portalCfg.slotsUrl || ''}?branch_id=${encodeURIComponent(branchId)}&date=${encodeURIComponent(dateVal)}&barber_id=${encodeURIComponent(barberId)}&${serviceParams}`;

                try {
                    if (tenantSlotsContainer) {
                        tenantSlotsContainer.innerHTML = '<div class="col-span-full py-4 text-center text-xs text-zinc-400 font-medium animate-pulse">Memeriksa ketersediaan jam...</div>';
                    }

                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('Gagal memuat slot jam');

                    const data = await res.json();

                    if (tenantDayBadge && data.day_name) {
                        tenantDayBadge.textContent = data.day_name;
                    }

                    if (tenantStatusMsg) {
                        tenantStatusMsg.textContent = data.message || (data.is_open ? 'Cabang buka normal' : 'Cabang tutup');
                    }

                    if (tenantStatusAlert) {
                        if (!data.is_open) {
                            tenantStatusAlert.className = 'p-3 rounded-xl border text-xs space-y-1 bg-rose-50 border-rose-200 text-rose-800';
                        } else {
                            tenantStatusAlert.className = 'p-3 rounded-xl border text-xs space-y-1 bg-white border-zinc-200 text-zinc-700';
                        }
                    }

                    if (tenantSlotsContainer) {
                        tenantSlotsContainer.innerHTML = '';
                        if (!data.slots || data.slots.length === 0) {
                            tenantSlotsContainer.innerHTML = `<div class="col-span-full py-4 text-center text-xs text-zinc-400 font-medium">${escapeHtml(data.message || 'Tidak ada slot jam yang tersedia.')}</div>`;
                            if (tenantTimeInput) tenantTimeInput.value = '';
                            syncTenantCombinedDatetime();
                            return;
                        }

                        const currentVal = tenantTimeInput?.value;
                        let matched = false;

                        data.slots.forEach(slot => {
                            const isSelected = currentVal === slot.time && slot.is_available;
                            if (isSelected) matched = true;

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.setAttribute('data-tenant-slot-btn', slot.time);
                            btn.setAttribute('data-available', slot.is_available ? '1' : '0');
                            btn.disabled = !slot.is_available;
                            btn.className = `tenant-slot-btn py-2.5 px-2 rounded-xl text-xs text-center transition flex items-center justify-center border ${
                                slot.is_available
                                    ? (isSelected ? 'bg-amber-500 text-zinc-950 font-black border-amber-600 shadow-sm cursor-pointer' : 'bg-white hover:bg-amber-50 text-zinc-800 font-bold border-zinc-200 hover:border-amber-400 cursor-pointer')
                                    : 'bg-zinc-100 text-zinc-400 border-zinc-200/60 opacity-50 cursor-not-allowed'
                            }`;
                            btn.title = slot.is_available ? `Klik untuk memilih jam ${slot.time}` : (slot.reason || 'Slot tidak tersedia');

                            btn.innerHTML = `<span class="num font-extrabold text-xs">${escapeHtml(slot.time)}</span>`;

                            tenantSlotsContainer.appendChild(btn);
                        });

                        if (!matched) {
                            if (tenantTimeInput) tenantTimeInput.value = '';
                        }
                        syncTenantCombinedDatetime();
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            if (tenantDateInput) {
                tenantDateInput.addEventListener('change', fetchTenantAvailableSlots);
            }

            function escapeHtml(str) {
                if (!str) return '';
                const p = document.createElement('p');
                p.textContent = str;
                return p.innerHTML;
            }

            syncTenantCombinedDatetime();
        })();
    </script>
</x-layouts.customer>
