<x-layouts.app title="Karyawan & Barber" subtitle="Kelola admin/kasir dan barber per cabang" role="owner">
    @php
        $editingStaffId = request()->filled('edit') ? request()->integer('edit') : null;
        $editingStaff = $editingStaffId ? $memberships->firstWhere('id', $editingStaffId) : null;

        $roleOptions = [
            ['value' => 'barber', 'label' => 'Staff Barber'],
            ['value' => 'admin', 'label' => 'Admin / Kasir'],
        ];

        $filterRoleOptions = [
            ['value' => 'barber', 'label' => 'Staff Barber'],
            ['value' => 'admin', 'label' => 'Admin / Kasir'],
        ];

        $branchOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name . ' (' . $b->code . ')',
            'sublabel' => $b->address,
        ])->all();

        $schemeOptions = $schemes->map(fn ($s) => [
            'value' => (string) $s->id,
            'label' => $s->name,
        ])->all();

        $workStatusOptions = [
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'break', 'label' => 'Istirahat (Break)'],
            ['value' => 'off', 'label' => 'Libur (Off)'],
        ];

        $filterBranchId = request()->filled('branch_id') ? request()->integer('branch_id') : null;
        $filterRole = request()->filled('role') ? (string) request('role') : null;

        $filteredMemberships = $memberships
            ->when($filterBranchId, fn ($c) => $c->where('branch_id', $filterBranchId))
            ->when($filterRole, fn ($c) => $c->filter(fn ($m) => $m->role->value === $filterRole))
            ->values();

        $perPage = 10;
        $totalItems = $filteredMemberships->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($totalPages, max(1, request()->integer('page', 1)));
        $paginatedMemberships = $filteredMemberships->forPage($currentPage, $perPage);

        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $plan = $tenant?->plan();
        $planName = $plan?->name ?? 'Starter';
        $maxStaff = $tenant?->maxStaff();
        $staffCount = $memberships->count();
        $canAddStaff = $tenant?->canAddStaff() ?? true;
    @endphp

    <!-- Flash Alert Status / Warning -->
    @if(session('status'))
        <div class="mb-4 p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-2xs">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
            </div>
            <span class="text-xs sm:text-sm font-bold">{{ session('status') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 p-3.5 sm:p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 flex items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0">
                    <x-ui.icon name="alert-triangle" class="w-4 h-4" />
                </div>
                <span class="text-xs sm:text-sm font-bold">{{ session('warning') }}</span>
            </div>
            <a href="{{ route('owner.subscription') }}" class="text-xs font-bold text-amber-800 hover:underline shrink-0">
                Upgrade Paket →
            </a>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
        <x-ui.card class="{{ $editingStaff ? 'ring-2 ring-amber-500/40 border-amber-500' : '' }}">
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-bold flex items-center gap-1.5">
                        <x-ui.icon :name="$editingStaff ? 'edit' : 'plus'" class="w-4 h-4 text-amber-600" />
                        <span>{{ $editingStaff ? 'Edit Karyawan' : 'Tambah Karyawan' }}</span>
                    </h2>
                    @if($editingStaff)
                        <span class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-700 text-[11px] font-extrabold">
                            Mode Edit
                        </span>
                    @else
                        <span class="text-xs font-semibold {{ $canAddStaff ? 'text-zinc-500' : 'text-rose-600 font-bold' }}">
                            {{ $maxStaff === null ? 'Unlimited' : $staffCount . '/' . $maxStaff }}
                        </span>
                    @endif
                </div>
            </x-slot:header>

            @if(! $editingStaff && ! $canAddStaff)
                <!-- Tampilan Jika Kuota Karyawan Sudah Penuh -->
                <div class="p-3.5 sm:p-4 rounded-xl bg-amber-50/70 border border-amber-200/80 text-center space-y-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center mx-auto">
                        <x-ui.icon name="lock" class="w-4 h-4" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-xs sm:text-sm font-extrabold text-zinc-900">Kuota Karyawan Penuh ({{ $maxStaff }}/{{ $maxStaff }})</h3>
                        <p class="text-xs text-zinc-600 leading-relaxed">
                            Batas staf paket <span class="font-bold text-zinc-900">{{ $planName }}</span> tercapai. Upgrade paket untuk menambah karyawan baru.
                        </p>
                    </div>
                    <div class="pt-0.5">
                        <a
                            href="{{ route('owner.subscription') }}"
                            class="inline-flex items-center justify-center gap-1.5 w-full py-2 px-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold transition shadow-2xs"
                        >
                            <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                            <span>Upgrade Paket</span>
                        </a>
                    </div>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ $editingStaff ? route('owner.staff.update', $editingStaff) : route('owner.staff.store') }}"
                    class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-3"
                >
                    @csrf
                    @if($editingStaff)
                        @method('PUT')
                    @endif

                <x-ui.input
                    label="Nama Lengkap *"
                    name="name"
                    :value="$editingStaff?->user?->name"
                    placeholder="Reza Pratama"
                    required
                />
                <x-ui.input
                    label="Nama Panggilan (Tampil di Antrean) *"
                    name="display_name"
                    :value="$editingStaff?->display_name"
                    placeholder="Reza"
                    required
                />
                <x-ui.input
                    label="Email Login *"
                    name="email"
                    type="email"
                    :value="$editingStaff?->user?->email"
                    placeholder="reza@barberking.id"
                    required
                />
                <x-ui.input
                    label="Nomor HP *"
                    name="phone"
                    :value="$editingStaff?->user?->phone"
                    placeholder="0813..."
                    required
                />

                @if($editingStaff)
                    <div class="sm:col-span-2 xl:col-span-1 p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-3">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-zinc-700">
                            <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span>Ubah Password Akun (Opsional)</span>
                        </div>
                        <p class="text-[11px] text-zinc-500">Kosongkan kolom di bawah jika tidak ingin mengganti password login karyawan ini.</p>
                        <x-ui.input
                            label="Password Baru"
                            name="password"
                            type="password"
                            placeholder="Minimal 8 karakter"
                            autocomplete="new-password"
                        />
                        <x-ui.input
                            label="Konfirmasi Password Baru"
                            name="password_confirmation"
                            type="password"
                            placeholder="Ulangi password baru"
                            autocomplete="new-password"
                        />
                    </div>
                @else
                    <x-ui.input
                        label="Password *"
                        name="password"
                        type="password"
                        placeholder="Minimal 8 karakter"
                        hint="Kata sandi untuk login karyawan (minimal 8 karakter)"
                        required
                        autocomplete="new-password"
                    />
                    <x-ui.input
                        label="Konfirmasi Password *"
                        name="password_confirmation"
                        type="password"
                        placeholder="Ulangi password di atas"
                        required
                        autocomplete="new-password"
                    />
                @endif

                <x-ui.select
                    label="Role *"
                    name="role"
                    icon="users"
                    :options="$roleOptions"
                    :value="$editingStaff?->role?->value"
                />

                <x-ui.select
                    label="Cabang Penugasan *"
                    name="branch_id"
                    icon="store"
                    :options="$branchOptions"
                    :value="$editingStaff?->branch_id"
                    search-placeholder="Cari cabang..."
                />

                <x-ui.input
                    label="Spesialisasi Barber (Opsional)"
                    name="specialty"
                    :value="$editingStaff?->specialty"
                    placeholder="Fade · Undercut"
                />

                <x-ui.select
                    label="Skema Gaji *"
                    name="salary_scheme_id"
                    icon="banknote"
                    direction="up"
                    :options="$schemeOptions"
                    :value="$editingStaff?->salary_scheme_id"
                    search-placeholder="Cari skema gaji..."
                />

                @if($editingStaff)
                    <div class="sm:col-span-2 xl:col-span-1">
                        <x-ui.select
                            label="Status Kerja"
                            name="work_status"
                            icon="clock"
                            direction="up"
                            :options="$workStatusOptions"
                            :value="$editingStaff->work_status->value"
                        />
                    </div>

                    <div class="sm:col-span-2 xl:col-span-1 pt-2 flex items-center gap-2">
                        <x-ui.button :href="route('owner.staff')" variant="outline" class="flex-1">
                            <span>Batal</span>
                        </x-ui.button>
                        <x-ui.button type="submit" class="flex-1">
                            <x-ui.icon name="check" class="w-4 h-4" />
                            <span>Simpan</span>
                        </x-ui.button>
                    </div>
                @else
                    <div class="sm:col-span-2 xl:col-span-1 pt-1">
                        <x-ui.button type="submit" class="w-full">
                            <x-ui.icon name="plus" class="w-4 h-4" />
                            <span>Tambah Karyawan</span>
                        </x-ui.button>
                    </div>
                @endif
                </form>
            @endif
        </x-ui.card>

        <x-ui.card class="xl:col-span-2">
            <x-slot:header>
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full">
                    <div class="flex items-center gap-2">
                        <h2 class="font-extrabold text-sm sm:text-base text-zinc-900 whitespace-nowrap">Daftar Tim Aktif</h2>
                        <span class="text-xs font-semibold {{ $canAddStaff ? 'text-zinc-500' : 'text-rose-600 font-bold' }} whitespace-nowrap">
                            &bull;&nbsp; {{ $maxStaff === null ? 'Kuota Unlimited' : 'Kuota: ' . $staffCount . ' / ' . $maxStaff . ' Karyawan' }}
                        </span>
                    </div>
                    <form method="GET" action="{{ route('owner.staff') }}" class="grid grid-cols-2 sm:flex sm:items-center gap-2 w-full lg:w-auto">
                        <div class="w-full sm:w-44">
                            <x-ui.select
                                name="branch_id"
                                size="sm"
                                align="right"
                                placeholder="Semua Cabang"
                                icon="store"
                                :options="$branchOptions"
                                :value="request('branch_id')"
                                search-placeholder="Cari cabang..."
                                class="w-full"
                                auto-submit
                            />
                        </div>
                        <div class="w-full sm:w-40">
                            <x-ui.select
                                name="role"
                                size="sm"
                                align="right"
                                placeholder="Semua Role"
                                icon="users"
                                :options="$filterRoleOptions"
                                :value="request('role')"
                                class="w-full"
                                auto-submit
                            />
                        </div>
                    </form>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[540px]">
                    <thead class="text-xs text-zinc-400 text-left border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 pr-3 font-bold text-zinc-500">Nama &amp; Cabang</th>
                            <th class="px-3 py-2.5 font-bold text-zinc-500 whitespace-nowrap">Role</th>
                            <th class="px-3 py-2.5 font-bold text-zinc-500 whitespace-nowrap">Skema Gaji</th>
                            <th class="px-3 py-2.5 font-bold text-zinc-500 whitespace-nowrap">Status</th>
                            <th class="py-2.5 pl-2 text-right font-bold text-zinc-500 whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($paginatedMemberships as $m)
                            @php
                                $isBeingEdited = $editingStaff && $editingStaff->id === $m->id;
                                $editUrl = route('owner.staff', array_filter([
                                    'edit' => $m->id,
                                    'branch_id' => request('branch_id'),
                                    'role' => request('role'),
                                    'page' => $currentPage > 1 ? $currentPage : null,
                                ]));
                                $schemeName = $m->salaryScheme?->name;
                                $hasDetail = $schemeName && preg_match('/^(.*?)\s*\((.*?)\)$/', $schemeName, $matches);
                            @endphp
                            <tr class="hover:bg-zinc-50/50 transition">
                                <td class="py-3 pr-3 min-w-[170px]">
                                    <div class="font-bold text-zinc-900 leading-snug">
                                        {{ $m->display_name ?? $m->user->name }}
                                    </div>
                                    <div class="text-xs text-zinc-400 leading-relaxed truncate max-w-xs">
                                        {{ $m->user->email }}{{ $m->specialty ? ' · ' . $m->specialty : '' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-zinc-500 mt-1">
                                        <x-ui.icon name="store" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <span class="font-medium text-zinc-600">{{ $m->branch?->name ?? 'Semua Cabang' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs font-bold text-zinc-700">
                                    @if($m->role->value === 'admin')
                                        <span>Admin / Kasir</span>
                                    @elseif($m->role->value === 'barber')
                                        <span>Barber</span>
                                    @else
                                        <span class="capitalize">{{ $m->role->value }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    @if($hasDetail)
                                        <div class="font-semibold text-zinc-800 leading-snug truncate max-w-[140px] sm:max-w-[170px] lg:max-w-[200px]" title="{{ $schemeName }}">
                                            {{ $matches[1] }}
                                        </div>
                                        <div class="text-[11px] text-zinc-400 font-normal truncate max-w-[140px] sm:max-w-[170px] lg:max-w-[200px]" title="{{ $matches[2] }}">
                                            {{ $matches[2] }}
                                        </div>
                                    @else
                                        <div class="font-medium text-zinc-800 truncate max-w-[140px] sm:max-w-[170px] lg:max-w-[200px]" title="{{ $schemeName ?? '-' }}">
                                            {{ $schemeName ?? '-' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <x-ui.badge :status="$m->work_status" />
                                </td>
                                <td class="py-3 pl-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <!-- Tombol Edit (Hanya Icon SVG) -->
                                        <a
                                            href="{{ $editUrl }}"
                                            title="Edit Karyawan"
                                            class="p-2 rounded-xl border transition cursor-pointer shrink-0 {{ $isBeingEdited ? 'bg-amber-500 text-zinc-950 border-amber-600 shadow-sm' : 'bg-white hover:bg-zinc-100 text-zinc-600 hover:text-zinc-900 border-zinc-200 shadow-2xs' }}"
                                        >
                                            <x-ui.icon name="edit" class="w-3.5 h-3.5" />
                                        </a>

                                        <!-- Tombol Hapus (Hanya Icon SVG) -->
                                        @if($m->role !== \App\Enums\Role::Owner)
                                            <form
                                                method="POST"
                                                action="{{ route('owner.staff.destroy', $m) }}"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menghapus karyawan {{ addslashes($m->display_name ?? $m->user?->name) }}?');"
                                                class="inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    title="Hapus Karyawan"
                                                    class="p-2 rounded-xl bg-white hover:bg-rose-600 text-zinc-400 hover:text-white border border-zinc-200 hover:border-rose-600 transition cursor-pointer shrink-0 shadow-2xs"
                                                >
                                                    <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-xs text-zinc-400">
                                    Tidak ada karyawan yang sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($totalPages > 1)
                @php
                    $prevUrl = $currentPage > 1
                        ? route('owner.staff', array_filter([
                            'branch_id' => request('branch_id'),
                            'role' => request('role'),
                            'edit' => request('edit'),
                            'page' => $currentPage - 1 > 1 ? $currentPage - 1 : null,
                        ]))
                        : null;

                    $nextUrl = $currentPage < $totalPages
                        ? route('owner.staff', array_filter([
                            'branch_id' => request('branch_id'),
                            'role' => request('role'),
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
</x-layouts.app>
