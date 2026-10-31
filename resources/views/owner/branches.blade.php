<x-layouts.app title="Cabang / Outlet" subtitle="Kelola seluruh cabang barbershop Anda" role="owner">
    @php
        $tenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
        $tenantSlug = $tenant?->slug;
        $maxBranches = $tenant?->maxBranches() ?? 1;
        $branchCount = $branches->count();
        $canAddBranch = $tenant?->canAddBranch() ?? true;
        $plan = $tenant?->plan();
        $planName = $plan?->name ?? 'Starter';
    @endphp

    <div class="max-w-4xl mx-auto space-y-4">
        <!-- Flash Alert -->
        @if(session('status'))
            <div class="p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-2xs">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="check" class="w-4 h-4 stroke-[2.5]" />
                </div>
                <span class="text-xs sm:text-sm font-bold">{{ session('status') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="p-3.5 sm:p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 flex items-center justify-between gap-3 shadow-2xs">
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

        <!-- Card Kuota Cabang Berdasarkan Paket -->
        <div class="p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="store" class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-extrabold text-zinc-900">Kuota Cabang Barbershop</h2>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-zinc-100 text-zinc-700">
                            Paket {{ $planName }}
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Penggunaan kuota: <span class="font-extrabold text-zinc-900 num">{{ $branchCount }}</span> dari <span class="font-extrabold text-zinc-900 num">{{ $maxBranches }} Cabang</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-center">
                @if($canAddBranch)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Kuota Tersedia (Sisa {{ $maxBranches - $branchCount }})</span>
                    </span>
                @else
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>Kuota Penuh</span>
                        </span>
                        <a
                            href="{{ route('owner.subscription') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-black transition shadow-2xs"
                        >
                            <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                            <span>Upgrade</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
        <!-- Daftar Cabang -->
        <div class="space-y-3">
            @forelse($branches as $branch)
                @php
                    $isLocked = $tenant?->isBranchLocked($branch) ?? false;
                @endphp
                <x-ui.card class="{{ $isLocked ? 'border-rose-300 bg-rose-50/20' : '' }}">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="space-y-1.5 min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg {{ $isLocked ? 'bg-zinc-800 text-rose-300' : 'bg-zinc-900 text-amber-400' }} text-xs font-extrabold num">{{ $branch->code }}</span>
                                <h3 class="font-extrabold text-base text-zinc-900 truncate">{{ $branch->name }}</h3>
                                @if($isLocked)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800 border border-rose-300">
                                        <x-ui.icon name="lock" class="w-3 h-3 text-rose-600" />
                                        <span>Terkunci (Melebihi Kuota {{ $planName }})</span>
                                    </span>
                                @else
                                    <x-ui.badge :status="$branch->is_active ? 'active' : 'cancelled'" />
                                @endif
                            </div>

                            @if($isLocked)
                                <div class="p-2 sm:p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2 mt-1">
                                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0" />
                                    <span class="leading-relaxed">
                                        Dikunci karena melebihi kuota <strong>Paket {{ $planName }} (Maks. {{ $maxBranches }} Cabang)</strong>. Akses kasir &amp; TV dinonaktifkan.
                                    </span>
                                </div>
                            @endif

                            <p class="text-xs text-zinc-500">
                                {{ $branch->address ?? 'Alamat belum diisi' }} · Telp: <span class="num font-semibold text-zinc-700">{{ $branch->phone ?? '-' }}</span>
                            </p>

                            <!-- Link Google Maps jika tersedia -->
                            @if(!empty($branch->maps_url))
                                <div class="pt-0.5">
                                    <a
                                        href="{{ $branch->maps_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100/80 px-2.5 py-1 rounded-lg border border-blue-200/70 transition w-fit"
                                    >
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                                        <span>Buka Lokasi di Google Maps</span>
                                    </a>
                                </div>
                            @endif

                            <p class="text-[11px] text-zinc-400 pt-0.5">
                                Toleransi telat booking: <b class="text-zinc-700 num">{{ $branch->late_tolerance_minutes }} menit</b>
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-start sm:self-center flex-wrap">
                            @if($isLocked)
                                <!-- Tombol Upgrade jika terkunci -->
                                <a
                                    href="{{ route('owner.subscription') }}"
                                    class="inline-flex items-center gap-1.5 text-xs font-black px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 transition shadow-2xs"
                                >
                                    <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                                    <span>Buka Kunci (Upgrade)</span>
                                </a>
                            @else
                                <!-- Tombol Layar TV jika aktif -->
                                <a
                                    href="{{ route('display.queue', [$tenantSlug, $branch->code]) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition"
                                >
                                    <x-ui.icon name="tv" class="w-4 h-4" />
                                    <span>Layar TV</span>
                                </a>
                            @endif

                            <!-- Tombol Edit Cabang -->
                            <a
                                href="{{ route('owner.branches.edit', $branch) }}"
                                class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl transition shadow-2xs bg-amber-500/15 text-amber-800 hover:bg-amber-500 hover:text-zinc-950"
                            >
                                <x-ui.icon name="edit" class="w-3.5 h-3.5" />
                                <span>Edit</span>
                            </a>

                            <!-- Tombol Hapus Cabang -->
                            @if($branchCount > 1)
                                <form
                                    method="POST"
                                    action="{{ route('owner.branches.destroy', $branch) }}"
                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus cabang {{ $branch->name }} ({{ $branch->code }})? Seluruh riwayat cabang ini akan diarsipkan.')"
                                    class="inline-block"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl transition shadow-2xs bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white cursor-pointer border border-rose-200/80"
                                        title="Hapus Cabang {{ $branch->name }}"
                                    >
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                        <span>Hapus</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card class="text-center py-10">
                    <div class="w-12 h-12 rounded-2xl bg-zinc-100 text-zinc-400 grid place-items-center mx-auto mb-3">
                        <x-ui.icon name="store" class="w-6 h-6" />
                    </div>
                    <h3 class="font-bold text-zinc-800">Belum Ada Cabang Terdaftar</h3>
                    <p class="text-xs text-zinc-500 mt-1 max-w-sm mx-auto">
                        Mulai daftarkan cabang barbershop pertama Anda untuk mengaktifkan antrean dan jadwal pangkas.
                    </p>
                </x-ui.card>
            @endforelse
        </div>

        <!-- Tombol Tambah Cabang Baru: Lingkaran dengan Icon SVG + Tepat di Bawah List -->
        <div class="pt-3 pb-2 flex flex-col items-center justify-center gap-2">
            @if($canAddBranch)
                <a
                    href="{{ route('owner.branches.create') }}"
                    title="Tambah Cabang Baru"
                    aria-label="Tambah Cabang Baru"
                    class="w-12 h-12 rounded-full bg-amber-500 hover:bg-amber-400 text-zinc-950 flex items-center justify-center shadow-lg shadow-amber-500/30 hover:scale-110 active:scale-95 transition-all duration-200"
                >
                    <x-ui.icon name="plus" class="w-6 h-6 stroke-[2.5]" />
                </a>
            @else
                <div class="text-center space-y-2">
                    <button
                        type="button"
                        disabled
                        title="Batas kuota cabang telah tercapai"
                        class="w-12 h-12 rounded-full bg-zinc-200 text-zinc-400 flex items-center justify-center cursor-not-allowed mx-auto"
                    >
                        <x-ui.icon name="lock" class="w-5 h-5 text-zinc-400" />
                    </button>
                    <p class="text-xs text-zinc-500">
                        Batas kuota {{ $maxBranches }} cabang tercapai.
                        <a href="{{ route('owner.subscription') }}" class="font-bold text-amber-600 hover:underline inline-flex items-center gap-0.5 ml-1">
                            Upgrade Paket
                            <x-ui.icon name="arrow-right" class="w-3 h-3" />
                        </a>
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
