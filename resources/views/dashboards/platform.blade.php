<x-layouts.app title="Platform Superadmin" subtitle="Monitoring seluruh barbershop, paket langganan & performa SaaS" role="superadmin">
    <!-- Stat Cards Ringkasan Platform SaaS -->
    <div class="stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 transition-all duration-500 ease-in-out">
        <x-ui.stat
            label="Total Barbershop"
            :value="$tenants->count() . ' Tenant'"
            trend="{{ $tenants->where('status', \App\Enums\TenantStatus::Active)->count() }} Aktif Berlangganan"
        />
        <x-ui.stat
            label="Total Outlet / Cabang"
            :value="($totalBranches ?? $tenants->sum(fn($t) => $t->branches_count ?? $t->branches->count())) . ' Outlet'"
        />
        <x-ui.stat
            label="Total Transaksi Masuk"
            :value="($totalOrders ?? 0) . ' Invoice'"
        />
        <x-ui.stat
            label="Total GMV Transaksi"
            :value="'Rp' . number_format($totalGmv ?? 0, 0, ',', '.')"
            trend="Seluruh Barbershop"
            highlight
        />
    </div>

    @php
        $tenantStatusOptions = [
            ['value' => 'active', 'label' => 'Aktif (Active)'],
            ['value' => 'trial', 'label' => 'Masa Trial (Trial)'],
            ['value' => 'suspended', 'label' => 'Disuspend (Suspended)'],
        ];

        $filterStatus = request()->filled('status') ? (string) request('status') : null;
        $search = request()->filled('search') ? strtolower(trim((string) request('search'))) : null;

        $filteredTenants = $tenants
            ->when($filterStatus, fn ($c) => $c->filter(fn ($t) => $t->status->value === $filterStatus))
            ->when($search, fn ($c) => $c->filter(fn ($t) =>
                str_contains(strtolower($t->name), $search) ||
                str_contains(strtolower($t->slug), $search) ||
                str_contains(strtolower((string) $t->owner?->name), $search) ||
                str_contains(strtolower((string) $t->owner?->email), $search)
            ));
    @endphp

    <!-- Daftar Tenant Barbershop Terdaftar -->
    <x-ui.card class="overflow-hidden">
        <x-slot:header>
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full">
                <div>
                    <h2 class="font-bold text-sm sm:text-base flex items-center gap-2 text-zinc-900">
                        <x-ui.icon name="store" class="w-4 h-4 text-amber-600" />
                        <span>Daftar Barbershop / Tenant</span>
                    </h2>
                    <span class="text-xs text-zinc-400">Total {{ $filteredTenants->count() }} barbershop terdata</span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Filter & Pencarian Tenant -->
                    <form method="GET" action="{{ route('platform.dashboard') }}" class="flex items-center gap-2">
                        <div class="relative w-36 sm:w-44">
                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Cari barbershop..."
                                class="w-full text-xs font-semibold pl-8 pr-2.5 py-1.5 rounded-xl bg-zinc-50 border border-zinc-200 text-zinc-900 placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-amber-500 shadow-2xs"
                            />
                            <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                                <x-ui.icon name="search" class="w-3.5 h-3.5" />
                            </div>
                        </div>

                        <x-ui.select
                            name="status"
                            size="sm"
                            align="right"
                            placeholder="Semua Status"
                            icon="filter"
                            :options="$tenantStatusOptions"
                            :value="request('status')"
                            class="w-32 sm:w-40"
                            auto-submit
                        />

                        @if(request()->hasAny(['search', 'status']))
                            <a
                                href="{{ route('platform.dashboard') }}"
                                class="p-1.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 transition"
                                title="Reset filter"
                            >
                                <x-ui.icon name="x" class="w-3.5 h-3.5" />
                            </a>
                        @endif
                    </form>

                    <!-- Tombol Tambah Barbershop Baru (Halaman Terpisah) -->
                    <x-ui.button href="{{ route('platform.tenants.create') }}" variant="primary" size="sm" class="shadow-xs font-bold whitespace-nowrap">
                        <x-ui.icon name="plus" class="w-4 h-4" />
                        <span>Daftarkan Barbershop</span>
                    </x-ui.button>
                </div>
            </div>
        </x-slot:header>

        @if($filteredTenants->isEmpty())
            <x-ui.empty-state title="Belum ada barbershop yang cocok dengan filter pencarian" />
        @else
            <!-- Desktop Table View (>= 1024px) -->
            <div class="hidden lg:block overflow-x-auto rounded-xl border border-zinc-200/80">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500">
                            <th class="py-2.5 px-3.5">Barbershop &amp; Slug</th>
                            <th class="py-2.5 px-3.5">Owner &amp; Kontak</th>
                            <th class="py-2.5 px-3.5 text-center">Outlet</th>
                            <th class="py-2.5 px-3.5">Paket Langganan</th>
                            <th class="py-2.5 px-3.5">Status</th>
                            <th class="py-2.5 px-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($filteredTenants as $tenant)
                            @php
                                $sub = $tenant->subscriptions->first();
                                $plan = $sub?->plan;
                            @endphp
                            <tr class="hover:bg-zinc-50/70 transition">
                                <td class="py-3 px-3.5">
                                    <a href="{{ route('platform.tenants.show', $tenant) }}" class="font-bold text-zinc-900 hover:text-amber-600 transition flex items-center gap-1.5" title="Buka Detail Tenant">
                                        <span>{{ $tenant->name }}</span>
                                        <x-ui.icon name="external-link" class="w-3 h-3 text-zinc-400" />
                                    </a>
                                    <span class="font-mono text-[11px] text-zinc-400 block mt-0.5">/b/{{ $tenant->slug }}</span>
                                </td>
                                <td class="py-3 px-3.5">
                                    <p class="font-bold text-zinc-900">{{ $tenant->owner?->name ?? '-' }}</p>
                                    <p class="text-[11px] text-zinc-400 font-mono">{{ $tenant->owner?->phone ?? $tenant->owner?->email ?? '-' }}</p>
                                </td>
                                <td class="py-3 px-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full bg-zinc-100 font-bold text-zinc-700 num text-[11px]">
                                        {{ $tenant->branches_count ?? $tenant->branches->count() }} Cabang
                                    </span>
                                </td>
                                <td class="py-3 px-3.5">
                                    @if($plan)
                                        <span class="font-bold text-zinc-800">{{ $plan->name }}</span>
                                        <p class="text-[10px] text-zinc-400">s/d {{ $sub?->ends_at?->translatedFormat('d M Y') ?? 'Trial' }}</p>
                                    @else
                                        <span class="text-zinc-400 italic text-[11px]">Belum Ada Paket</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3.5">
                                    <x-ui.badge :status="$tenant->status" plain />
                                </td>
                                <td class="py-3 px-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('platform.tenants.show', $tenant) }}"
                                            class="w-8 h-8 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 hover:text-zinc-950 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                            title="Kelola &amp; Edit {{ $tenant->name }}"
                                        >
                                            <x-ui.icon name="pencil" class="w-3.5 h-3.5" />
                                        </a>

                                        <button
                                            type="button"
                                            onclick="openSuspendModal('{{ route('platform.tenants.toggle', $tenant) }}', '{{ addslashes($tenant->name) }}', {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'true' : 'false' }})"
                                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer text-white shadow-2xs {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-rose-600 hover:bg-rose-500' }}"
                                            title="Ubah Status Barbershop"
                                        >
                                            {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'Aktifkan' : 'Suspend' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card List View (< 1024px) -->
            <div class="lg:hidden divide-y divide-zinc-100 rounded-xl border border-zinc-200/80 bg-white overflow-hidden">
                @foreach($filteredTenants as $tenant)
                    @php
                        $sub = $tenant->subscriptions->first();
                        $plan = $sub?->plan;
                    @endphp
                    <div class="p-3.5 sm:p-4 hover:bg-zinc-50/60 transition">
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('platform.tenants.show', $tenant) }}" class="font-extrabold text-sm text-zinc-900 hover:text-amber-600 transition flex items-center gap-1">
                                        <span>{{ $tenant->name }}</span>
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-400" />
                                    </a>
                                    <x-ui.badge :status="$tenant->status" plain />
                                </div>
                                <span class="font-mono text-[11px] text-zinc-400 block mt-0.5">/b/{{ $tenant->slug }}</span>
                            </div>

                            <span class="px-2 py-0.5 rounded-full bg-zinc-100 font-bold text-zinc-700 text-[11px] shrink-0">
                                {{ $tenant->branches_count ?? $tenant->branches->count() }} Cabang
                            </span>
                        </div>

                        <div class="mt-2.5 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Owner</span>
                                <p class="font-bold text-zinc-800 truncate mt-0.5">{{ $tenant->owner?->name ?? '-' }}</p>
                                <p class="text-[10px] text-zinc-400 font-mono truncate">{{ $tenant->owner?->phone ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Langganan</span>
                                <p class="font-bold text-amber-700 truncate mt-0.5">{{ $plan?->name ?? 'Trial' }}</p>
                                <p class="text-[10px] text-zinc-400 truncate">s/d {{ $sub?->ends_at?->translatedFormat('d M Y') ?? '14 hari' }}</p>
                            </div>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-zinc-100 flex items-center justify-end gap-2">
                            <a
                                href="{{ route('platform.tenants.show', $tenant) }}"
                                class="w-8 h-8 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 hover:text-zinc-950 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                title="Kelola &amp; Edit {{ $tenant->name }}"
                            >
                                <x-ui.icon name="pencil" class="w-3.5 h-3.5" />
                            </a>

                            <button
                                type="button"
                                onclick="openSuspendModal('{{ route('platform.tenants.toggle', $tenant) }}', '{{ addslashes($tenant->name) }}', {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'true' : 'false' }})"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer text-white shadow-2xs {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-rose-600 hover:bg-rose-500' }}"
                            >
                                {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'Aktifkan' : 'Suspend' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <!-- Modal Dialog Konfirmasi Suspend / Aktifkan (Popup Kecil) -->
    <dialog id="modal-confirm-suspend" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-sm mx-auto my-auto border border-zinc-200 bg-white">
        <div class="p-5 text-center">
            <div id="modal-suspend-icon-container" class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-600 flex items-center justify-center mx-auto mb-3.5 shadow-2xs">
                <x-ui.icon name="alert-triangle" class="w-6 h-6 text-rose-600" />
            </div>
            <h3 class="font-extrabold text-base text-zinc-900" id="modal-suspend-title">Konfirmasi Suspend</h3>
            <p class="text-xs text-zinc-500 mt-1 leading-relaxed" id="modal-suspend-message">
                Apakah Anda yakin ingin menangguhkan (suspend) barbershop ini?
            </p>
        </div>
        <div class="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2 rounded-b-3xl">
            <button
                type="button"
                onclick="document.getElementById('modal-confirm-suspend').close()"
                class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
            >
                Tidak
            </button>
            <form id="form-confirm-suspend" method="POST" action="">
                @csrf
                @method('PATCH')
                <button
                    type="submit"
                    id="btn-confirm-suspend"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer"
                >
                    Ya, Lanjutkan
                </button>
            </form>
        </div>
    </dialog>

    <script>
        function openSuspendModal(actionUrl, tenantName, isCurrentlySuspended) {
            const form = document.getElementById('form-confirm-suspend');
            const title = document.getElementById('modal-suspend-title');
            const message = document.getElementById('modal-suspend-message');
            const submitBtn = document.getElementById('btn-confirm-suspend');
            const iconContainer = document.getElementById('modal-suspend-icon-container');
            const modal = document.getElementById('modal-confirm-suspend');

            form.action = actionUrl;

            if (isCurrentlySuspended) {
                title.textContent = 'Aktifkan Barbershop?';
                message.innerHTML = `Apakah Anda yakin ingin mengaktifkan kembali barbershop <strong class="text-zinc-900">"${tenantName}"</strong>?`;
                submitBtn.textContent = 'Ya, Aktifkan';
                submitBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer';
                iconContainer.className = 'w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center mx-auto mb-3.5 shadow-2xs';
            } else {
                title.textContent = 'Suspend Barbershop?';
                message.innerHTML = `Apakah Anda yakin ingin menangguhkan (suspend) barbershop <strong class="text-zinc-900">"${tenantName}"</strong>? Akses owner dan pelanggan akan dinonaktifkan sementara.`;
                submitBtn.textContent = 'Ya, Suspend';
                submitBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer';
                iconContainer.className = 'w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-600 flex items-center justify-center mx-auto mb-3.5 shadow-2xs';
            }

            modal.showModal();
        }
    </script>
</x-layouts.app>
