<x-layouts.app title="Pengajuan & Izin Libur Barber" subtitle="Persetujuan cuti, izin sakit, dan libur barber" role="owner">
    @php
        $branchFilterOptions = $branches->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->name,
            'sublabel' => $b->code,
        ])->all();

        $statusFilterOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'pending', 'label' => 'Menunggu Persetujuan'],
            ['value' => 'approved', 'label' => 'Disetujui'],
            ['value' => 'rejected', 'label' => 'Ditolak'],
        ];

        $barberSelectOptions = $barbers->map(fn ($b) => [
            'value' => (string) $b->id,
            'label' => $b->display_name ?? $b->user?->name,
            'sublabel' => $b->branch?->name ?? 'Semua Cabang',
        ])->all();

        $timeOffTypeOptions = [
            ['value' => 'leave', 'label' => 'Cuti'],
            ['value' => 'sick', 'label' => 'Sakit'],
            ['value' => 'holiday', 'label' => 'Izin Libur'],
        ];

        $timeOffStatusOptions = [
            ['value' => 'approved', 'label' => 'Langsung Disetujui (Approved)'],
            ['value' => 'pending', 'label' => 'Menunggu Review (Pending)'],
        ];
    @endphp

    <!-- Sub-Navigation Tab Khusus Manajemen Jadwal & Absen -->
    <x-owner.schedule-tabs active="time-offs" />

    <!-- Statistik Ringkas Time Off -->
    <div class="stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-5 sm:mb-6 transition-all duration-500 ease-in-out">
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs {{ $pendingCount > 0 ? 'ring-2 ring-amber-500/30' : '' }}">
            <p class="text-xs font-semibold text-amber-600 flex items-center justify-between">
                <span>Persetujuan</span>
                @if($pendingCount > 0)
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                @endif
            </p>
            <p class="text-xl sm:text-2xl font-black text-amber-700 mt-1 num">{{ $pendingCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Perlu tindakan Owner</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-emerald-600">Disetujui Bulan Ini</p>
            <p class="text-xl sm:text-2xl font-black text-emerald-700 mt-1 num">{{ $approvedMonthCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Periode {{ now()->translatedFormat('F Y') }}</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-sky-600">Libur / Izin Hari Ini</p>
            <p class="text-xl sm:text-2xl font-black text-sky-700 mt-1 num">{{ $todayOffBarbersCount }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Barber tidak bertugas</p>
        </div>
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <p class="text-xs font-semibold text-zinc-500">Total Pengajuan</p>
            <p class="text-xl sm:text-2xl font-black text-zinc-900 mt-1 num">{{ $timeOffs->total() }}</p>
            <p class="text-[10px] sm:text-[11px] text-zinc-400 mt-0.5 truncate">Data terdata di sistem</p>
        </div>
    </div>

    <!-- Filter & Tombol Tambah Izin Baru -->
    <div class="time-offs-filter-container flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 sm:p-4 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs mb-5 transition-all duration-500 ease-in-out">
        <form method="GET" action="{{ route('owner.time-offs') }}" class="time-offs-filter-form grid grid-cols-1 sm:grid-cols-2 md:flex md:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
            <!-- Filter Status -->
            <div class="w-full sm:w-auto md:w-48 lg:w-52">
                <x-ui.select
                    name="status"
                    size="sm"
                    icon="filter"
                    :options="$statusFilterOptions"
                    :value="$statusFilter"
                    class="w-full"
                    auto-submit
                />
            </div>

            <!-- Filter Cabang -->
            <div class="w-full sm:w-auto md:w-48 lg:w-52">
                <x-ui.select
                    name="branch_id"
                    size="sm"
                    icon="store"
                    placeholder="Semua Cabang"
                    :options="$branchFilterOptions"
                    :value="request('branch_id')"
                    search-placeholder="Cari cabang..."
                    class="w-full"
                    auto-submit
                />
            </div>
        </form>

        <button
            type="button"
            onclick="document.getElementById('modal-create-time-off').showModal()"
            class="time-offs-filter-btn inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold text-xs transition shadow-xs cursor-pointer w-full sm:w-auto shrink-0 whitespace-nowrap"
        >
            <x-ui.icon name="plus" class="w-4 h-4" />
            <span>Tambah Izin / Libur Baru</span>
        </button>
    </div>

    <!-- Tabel Daftar Pengajuan & Izin Libur -->
    <x-ui.card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-2 w-full">
                <div>
                    <h2 class="font-bold flex items-center gap-2 text-zinc-900">
                        <x-ui.icon name="calendar" class="w-4 h-4 text-amber-600" />
                        <span>Daftar Permohonan Izin &amp; Libur Barber</span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Daftar riwayat permohonan cuti, sakit, dan izin libur barber</p>
                </div>
                <span class="text-xs text-zinc-400">Total {{ $timeOffs->total() }} catatan</span>
            </div>
        </x-slot:header>

        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-zinc-400 text-left border-b border-zinc-100">
                    <tr>
                        <th class="py-3 font-bold">Barber</th>
                        <th>Cabang</th>
                        <th>Rentang Tanggal</th>
                        <th>Tipe Izin</th>
                        <th>Alasan / Keterangan</th>
                        <th>Bukti Surat</th>
                        <th>Status</th>
                        <th class="text-right font-bold py-3 pr-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($timeOffs as $to)
                        @php
                            $daysCount = $to->date_from->diffInDays($to->date_to) + 1;
                        @endphp
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="py-3.5 font-bold text-zinc-900">
                                <p class="text-xs font-extrabold text-zinc-900">{{ $to->membership?->display_name ?? $to->membership?->user?->name }}</p>
                                <p class="text-[10px] text-zinc-400 font-normal">{{ $to->membership?->specialty ?: 'Barber' }}</p>
                            </td>
                            <td class="font-semibold text-zinc-600">
                                <span class="px-2 py-1 rounded-md bg-zinc-100 text-zinc-700 text-[11px] font-bold">
                                    {{ $to->membership?->branch?->name ?? 'Semua Cabang' }}
                                </span>
                            </td>
                            <td class="font-semibold text-zinc-800">
                                <p class="text-xs num font-bold text-zinc-900">{{ $to->date_from->format('d M Y') }} - {{ $to->date_to->format('d M Y') }}</p>
                                <p class="text-[10px] text-zinc-400 font-semibold">{{ $daysCount }} Hari Libur</p>
                            </td>
                            <td>
                                @if($to->type === 'leave')
                                    <span class="px-2 py-1 rounded-lg bg-sky-50 text-sky-700 font-bold text-[11px] border border-sky-200/50">Cuti</span>
                                @elseif($to->type === 'sick')
                                    <span class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Sakit</span>
                                @elseif($to->type === 'holiday')
                                    <span class="px-2 py-1 rounded-lg bg-purple-50 text-purple-700 font-bold text-[11px] border border-purple-200/50">Izin Libur</span>
                                @else
                                    <span class="px-2 py-1 rounded-lg bg-zinc-100 text-zinc-700 font-bold text-[11px]">{{ ucfirst($to->type) }}</span>
                                @endif
                            </td>
                            <td class="text-zinc-600 max-w-[200px]">
                                <p class="truncate" title="{{ $to->reason }}">{{ $to->reason ?: '-' }}</p>
                                @if($to->notes)
                                    <p class="text-[10px] text-rose-500 font-medium truncate" title="Catatan: {{ $to->notes }}">Catatan: {{ $to->notes }}</p>
                                @endif
                            </td>
                            <td>
                                @if($to->attachment_url)
                                    <a
                                        href="{{ $to->attachment_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-[11px] font-bold transition shadow-2xs"
                                        title="Buka lampiran bukti surat / dokumen"
                                    >
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-amber-400" />
                                        <span>Lihat Dokumen</span>
                                    </a>
                                @else
                                    <span class="text-[11px] text-zinc-400 italic">Tanpa Dokumen</span>
                                @endif
                            </td>
                            <td>
                                @if($to->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200/50">
                                        <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                        <span>Disetujui</span>
                                    </span>
                                @elseif($to->status === 'rejected')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">
                                        <x-ui.icon name="x" class="w-3 h-3 text-rose-600" />
                                        <span>Ditolak</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200/50">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Menunggu</span>
                                    </span>
                                @endif
                            </td>
                            <td class="text-right pr-2">
                                @if($to->status === 'pending')
                                    <div class="inline-flex items-center gap-1.5">
                                        <form method="POST" action="{{ route('owner.time-offs.approve', $to) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer shadow-2xs"
                                                title="Setujui permohonan izin ini"
                                            >
                                                Setujui
                                            </button>
                                        </form>
                                        <button
                                            type="button"
                                            onclick="openRejectModal({{ $to->id }}, '{{ addslashes($to->membership?->display_name ?? $to->membership?->user?->name) }}')"
                                            class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition cursor-pointer shadow-2xs"
                                            title="Tolak permohonan izin ini"
                                        >
                                            Tolak
                                        </button>
                                    </div>
                                @else
                                    <span class="text-[11px] text-zinc-400 italic">
                                        {{ $to->approver ? 'Oleh ' . $to->approver->name : '-' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 text-zinc-400 italic">Belum ada catatan izin / libur sesuai filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile & Tablet Card List View -->
        <div class="divide-y divide-zinc-100 lg:hidden">
            @forelse($timeOffs as $to)
                @php
                    $daysCount = $to->date_from->diffInDays($to->date_to) + 1;
                @endphp
                <div class="p-3.5 sm:p-4 flex flex-col gap-3 hover:bg-zinc-50/50 transition">
                    <!-- Top: Barber Avatar, Name, Branch & Status -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-700 flex items-center justify-center font-black text-xs shrink-0">
                                {{ strtoupper(substr($to->membership?->display_name ?? $to->membership?->user?->name ?? 'B', 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-zinc-900 truncate">
                                    {{ $to->membership?->display_name ?? $to->membership?->user?->name }}
                                </p>
                                <p class="text-[11px] text-zinc-400 truncate">
                                    {{ $to->membership?->specialty ?: 'Barber' }} &bull; {{ $to->membership?->branch?->name ?? 'Semua Cabang' }}
                                </p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            @if($to->status === 'approved')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200/50">
                                    <x-ui.icon name="check" class="w-3 h-3 text-emerald-600" />
                                    <span>Disetujui</span>
                                </span>
                            @elseif($to->status === 'rejected')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">
                                    <x-ui.icon name="x" class="w-3 h-3 text-rose-600" />
                                    <span>Ditolak</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Menunggu</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Details Box: Tanggal & Tipe -->
                    <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-xs">
                        <div>
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider">Rentang Tanggal</span>
                            <span class="font-bold text-zinc-900 num">{{ $to->date_from->format('d M Y') }} &ndash; {{ $to->date_to->format('d M Y') }}</span>
                            <span class="text-[10px] text-zinc-500 font-medium block">({{ $daysCount }} Hari Libur)</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase tracking-wider mb-0.5">Tipe Izin</span>
                            @if($to->type === 'leave')
                                <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 font-bold text-[11px] border border-sky-200/50">Cuti</span>
                            @elseif($to->type === 'sick')
                                <span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 font-bold text-[11px] border border-rose-200/50">Sakit</span>
                            @elseif($to->type === 'holiday')
                                <span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 font-bold text-[11px] border border-purple-200/50">Izin Libur</span>
                            @else
                                <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 font-bold text-[11px]">{{ ucfirst($to->type) }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Bukti Surat (Mobile) -->
                    @if($to->attachment_url)
                        <div class="pt-0.5">
                            <a
                                href="{{ $to->attachment_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition w-full sm:w-auto shadow-2xs"
                            >
                                <x-ui.icon name="file-text" class="w-4 h-4 text-amber-400" />
                                <span>Lihat Bukti Surat / Dokumen</span>
                            </a>
                        </div>
                    @endif

                    <!-- Alasan & Catatan -->
                    @if($to->reason || $to->notes)
                        <div class="text-xs space-y-1">
                            @if($to->reason)
                                <p class="text-zinc-600"><span class="font-semibold text-zinc-700">Alasan:</span> {{ $to->reason }}</p>
                            @endif
                            @if($to->notes)
                                <p class="text-rose-600 font-medium text-[11px]"><span class="font-semibold">Catatan Owner:</span> {{ $to->notes }}</p>
                            @endif
                        </div>
                    @endif

                    <!-- Bottom: Actions / Approver Info -->
                    <div class="flex items-center justify-between pt-1">
                        @if($to->approver)
                            <span class="text-[11px] text-zinc-400 italic">
                                Oleh {{ $to->approver->name }}
                            </span>
                        @else
                            <span></span>
                        @endif

                        @if($to->status === 'pending')
                            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                                <form method="POST" action="{{ route('owner.time-offs.approve', $to) }}" class="flex-1 sm:flex-initial">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer shadow-2xs text-center"
                                        title="Setujui permohonan izin ini"
                                    >
                                        Setujui
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    onclick="openRejectModal({{ $to->id }}, '{{ addslashes($to->membership?->display_name ?? $to->membership?->user?->name) }}')"
                                    class="flex-1 sm:flex-initial px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition cursor-pointer shadow-2xs text-center"
                                    title="Tolak permohonan izin ini"
                                >
                                    Tolak
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10 px-4 text-zinc-400 italic text-xs">
                    Belum ada catatan izin / libur sesuai filter yang dipilih.
                </div>
            @endforelse
        </div>

        @if($timeOffs->hasPages())
            <div class="p-4 border-t border-zinc-100">
                {{ $timeOffs->links() }}
            </div>
        @endif
    </x-ui.card>

    <!-- Modal Form Tambah Izin / Libur Baru (Owner Input) -->
    <dialog id="modal-create-time-off" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/50 w-[calc(100%-2rem)] max-w-lg mx-auto my-auto">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900 flex items-center gap-2">
                    <x-ui.icon name="plus" class="w-4 h-4 text-amber-600" />
                    <span>Input Izin / Libur Barber Baru</span>
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">Catat libur atau izin barber secara langsung oleh Owner / Manajemen</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create-time-off').close()" class="text-zinc-400 hover:text-zinc-700">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('owner.time-offs.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf

            <div>
                <x-ui.select
                    label="Pilih Barber *"
                    name="membership_id"
                    icon="scissors"
                    placeholder="-- Pilih Barber --"
                    :options="$barberSelectOptions"
                    search-placeholder="Cari nama barber..."
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tanggal Mulai <span class="text-rose-500">*</span></label>
                    <input type="date" name="date_from" required value="{{ now()->toDateString() }}" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tanggal Selesai <span class="text-rose-500">*</span></label>
                    <input type="date" name="date_to" required value="{{ now()->toDateString() }}" class="w-full text-xs font-semibold px-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.select
                    label="Tipe Izin *"
                    name="type"
                    icon="calendar"
                    :options="$timeOffTypeOptions"
                    value="leave"
                    required
                />
                <x-ui.select
                    label="Status Pengajuan *"
                    name="status"
                    icon="check"
                    :options="$timeOffStatusOptions"
                    value="approved"
                    required
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Alasan / Keterangan</label>
                <textarea name="reason" rows="2.5" placeholder="Contoh: Izin acara keluarga, sakit demam, dll." class="w-full text-xs font-medium p-3 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Unggah Bukti Surat / Dokumen (Opsional)</label>
                <input
                    type="file"
                    name="attachment"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="w-full text-xs text-zinc-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/10 file:text-amber-800 hover:file:bg-amber-500/20 file:cursor-pointer border border-zinc-200 rounded-xl p-1 bg-white"
                />
                <p class="text-[10px] text-zinc-400 mt-1">Format gambar (JPG, PNG) atau PDF, maks. 5MB.</p>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-create-time-off').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-800 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer"
                >
                    Batal
                </button>
                <x-ui.button type="submit" size="md" variant="primary">
                    <span>Simpan Data Libur</span>
                </x-ui.button>
            </div>
        </form>
    </dialog>

    <!-- Modal Form Tolak Pengajuan -->
    <dialog id="modal-reject-time-off" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/50 w-[calc(100%-2rem)] max-w-md mx-auto my-auto">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
            <div>
                <h3 class="font-extrabold text-sm text-zinc-900 flex items-center gap-2">
                    <x-ui.icon name="x" class="w-4 h-4 text-rose-600" />
                    <span>Tolak Permohonan Libur: <span id="modal-reject-barber-name" class="text-rose-600"></span></span>
                </h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-reject-time-off').close()" class="text-zinc-400 hover:text-zinc-700">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <form id="form-reject-time-off" method="POST" action="" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1.5">Alasan Penolakan (Opsional)</label>
                <textarea name="notes" rows="3" placeholder="Contoh: Jadwal antrean hari tersebut sangat padat, mohon ajukan di hari lain." class="w-full text-xs font-medium p-3 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-rose-500/40"></textarea>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('modal-reject-time-off').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-800 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition cursor-pointer shadow-xs"
                >
                    Konfirmasi Tolak Izin
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openRejectModal(timeOffId, barberName) {
            document.getElementById('modal-reject-barber-name').textContent = barberName;
            document.getElementById('form-reject-time-off').action = `/owner/time-offs/${timeOffId}/reject`;
            document.getElementById('modal-reject-time-off').showModal();
        }
    </script>
</x-layouts.app>
