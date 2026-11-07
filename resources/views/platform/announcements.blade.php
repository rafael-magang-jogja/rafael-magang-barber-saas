<x-layouts.app title="Siaran & Riwayat Pengumuman Global" subtitle="Kelola penyiaran pesan sistem, target audiens (Owner/Kasir/Barber), dan log riwayat pengumuman" role="superadmin">
    <div class="space-y-6">
        <!-- Stat Cards Ringkasan Pengumuman -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 announcements-stat-grid">
            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Pengumuman</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <x-ui.icon name="megaphone" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                    {{ $totalCount }}
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">Seluruh riwayat siaran pesan</p>
            </div>

            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Sedang Aktif Siar</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <x-ui.icon name="check-circle" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 num">
                    {{ $activeCount }}
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">Muncul di dashboard sasaran</p>
            </div>

            <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
                <div class="flex items-center justify-between text-zinc-400 mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Total Respon User</span>
                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                        <x-ui.icon name="users" class="w-4 h-4" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-zinc-950 num">
                    {{ $totalReads }}
                </div>
                <p class="text-[11px] text-zinc-500 mt-1">Pengguna telah menutup / checklist</p>
            </div>
        </div>

        <!-- Form Buat Pengumuman Baru -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white p-5 sm:p-6 shadow-2xs">
            <div class="flex items-center gap-2.5 pb-4 mb-4 border-b border-zinc-100">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <x-ui.icon name="plus" class="w-4 h-4 stroke-[2.5]" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-zinc-900 tracking-tight">Siarkan Pengumuman Baru</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Pesan akan muncul sebagai popup ringkas 1x per login bagi audiens yang ditargetkan</p>
                </div>
            </div>

            <form method="POST" action="{{ route('platform.announcements.store') }}" class="space-y-4">
                @csrf

                <!-- Pilihan Tingkat Urgensi / Tipe Pesan -->
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tingkat Urgensi / Kategori Pesan <span class="text-rose-500 font-bold">*</span></label>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 announcements-urgency-grid">
                        <label class="cursor-pointer select-none outline-none focus:outline-none focus:ring-0">
                            <input type="radio" name="type" value="info" {{ old('type', 'info') === 'info' ? 'checked' : '' }} class="peer sr-only outline-none focus:outline-none focus:ring-0" />
                            <div class="p-3 rounded-xl border border-zinc-200 hover:border-zinc-300 peer-checked:border-sky-500 peer-checked:bg-sky-50/70 peer-checked:ring-2 peer-checked:ring-sky-500/20 text-center transition select-none outline-none focus:outline-none">
                                <span class="text-xs font-extrabold text-sky-800 block">Info &amp; Update</span>
                                <span class="text-[10px] text-zinc-500 block">Fitur Baru / Tips</span>
                            </div>
                        </label>

                        <label class="cursor-pointer select-none outline-none focus:outline-none focus:ring-0">
                            <input type="radio" name="type" value="maintenance" {{ old('type') === 'maintenance' ? 'checked' : '' }} class="peer sr-only outline-none focus:outline-none focus:ring-0" />
                            <div class="p-3 rounded-xl border border-zinc-200 hover:border-zinc-300 peer-checked:border-amber-500 peer-checked:bg-amber-50/70 peer-checked:ring-2 peer-checked:ring-amber-500/20 text-center transition select-none outline-none focus:outline-none">
                                <span class="text-xs font-extrabold text-amber-800 block">Pemeliharaan</span>
                                <span class="text-[10px] text-zinc-500 block">Jadwal Maintenance</span>
                            </div>
                        </label>

                        <label class="cursor-pointer select-none outline-none focus:outline-none focus:ring-0">
                            <input type="radio" name="type" value="warning" {{ old('type') === 'warning' ? 'checked' : '' }} class="peer sr-only outline-none focus:outline-none focus:ring-0" />
                            <div class="p-3 rounded-xl border border-zinc-200 hover:border-zinc-300 peer-checked:border-orange-500 peer-checked:bg-orange-50/70 peer-checked:ring-2 peer-checked:ring-orange-500/20 text-center transition select-none outline-none focus:outline-none">
                                <span class="text-xs font-extrabold text-orange-800 block">Peringatan</span>
                                <span class="text-[10px] text-zinc-500 block">Pemberitahuan Penting</span>
                            </div>
                        </label>

                        <label class="cursor-pointer select-none outline-none focus:outline-none focus:ring-0">
                            <input type="radio" name="type" value="urgent" {{ old('type') === 'urgent' ? 'checked' : '' }} class="peer sr-only outline-none focus:outline-none focus:ring-0" />
                            <div class="p-3 rounded-xl border border-zinc-200 hover:border-zinc-300 peer-checked:border-rose-500 peer-checked:bg-rose-50/70 peer-checked:ring-2 peer-checked:ring-rose-500/20 text-center transition select-none outline-none focus:outline-none">
                                <span class="text-xs font-extrabold text-rose-800 block">Darurat</span>
                                <span class="text-[10px] text-zinc-500 block">Kritis &amp; Mendesak</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3.5 sm:gap-4 announcements-form-inputs-grid">
                    <!-- Judul Pengumuman (Opsional) -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1">
                            Judul Pengumuman <span class="text-zinc-400 font-normal">(Opsional)</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            maxlength="200"
                            placeholder="Contoh: Pemeliharaan Server Terjadwal"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition font-semibold"
                        />
                    </div>

                    <!-- Target Audiens -->
                    <div>
                        <x-ui.select
                            label="Target Audiens Pengumuman *"
                            name="target_role"
                            icon="users"
                            :value="old('target_role', 'all')"
                            :options="[
                                ['value' => 'all', 'label' => 'Semua Pengguna (Global: Owner, Kasir, Barber, Customer)'],
                                ['value' => 'owner', 'label' => 'Khusus Pemilik Barbershop (Owner)'],
                                ['value' => 'admin', 'label' => 'Khusus Kasir / Admin Cabang'],
                                ['value' => 'barber', 'label' => 'Khusus Staf Barber'],
                                ['value' => 'customer', 'label' => 'Khusus Pelanggan (Customer)'],
                            ]"
                        />
                    </div>
                </div>

                <!-- Isi Pesan Pengumuman -->
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">
                        Isi Pesan Pengumuman <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <textarea
                        name="message"
                        rows="3"
                        required
                        maxlength="1000"
                        placeholder="Tuliskan isi pengumuman. Contoh: Pemeliharaan server dijadwalkan Minggu pukul 02:00 - 04:00 WIB..."
                        class="w-full px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-zinc-50/50 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition leading-relaxed"
                    >{{ old('message') }}</textarea>
                    <p class="text-[11px] text-zinc-400 mt-1">Maksimal 1000 karakter. Ditampilkan dalam modal popup yang rapi.</p>
                </div>

                <!-- Pilihan Masa Penayangan Pengumuman (Hari Ini Saja / Seterusnya / Kustom) -->
                <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80 space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-zinc-800 mb-1">
                            Masa Berlaku / Durasi Siaran Pengumuman <span class="text-rose-500 font-bold ml-0.5">*</span>
                        </label>
                        <p class="text-[11px] text-zinc-500 mb-2.5">
                            Atur apakah pengumuman otomatis kedaluwarsa setelah hari ini atau terus tampil sampai dinonaktifkan secara manual.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <label class="relative flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition select-none bg-white hover:border-amber-400 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:shadow-2xs border-zinc-200">
                            <input
                                type="radio"
                                name="duration_type"
                                value="today_only"
                                {{ old('duration_type', 'today_only') === 'today_only' ? 'checked' : '' }}
                                onchange="toggleCustomDurationDate(this.value)"
                                class="w-4 h-4 text-amber-500 focus:ring-amber-500 border-zinc-300"
                            />
                            <div class="min-w-0">
                                <span class="block text-xs font-bold text-zinc-900">Hari Ini Saja (1 Hari)</span>
                                <span class="block text-[10px] text-zinc-500 leading-tight mt-0.5">Otomatis selesai pukul 23:59 WIB malam ini</span>
                            </div>
                        </label>

                        <label class="relative flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition select-none bg-white hover:border-amber-400 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:shadow-2xs border-zinc-200">
                            <input
                                type="radio"
                                name="duration_type"
                                value="forever"
                                {{ old('duration_type') === 'forever' ? 'checked' : '' }}
                                onchange="toggleCustomDurationDate(this.value)"
                                class="w-4 h-4 text-amber-500 focus:ring-amber-500 border-zinc-300"
                            />
                            <div class="min-w-0">
                                <span class="block text-xs font-bold text-zinc-900">Seterusnya (Tanpa Batas)</span>
                                <span class="block text-[10px] text-zinc-500 leading-tight mt-0.5">Tampil terus hingga dinonaktifkan manual</span>
                            </div>
                        </label>

                        <label class="relative flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition select-none bg-white hover:border-amber-400 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:shadow-2xs border-zinc-200">
                            <input
                                type="radio"
                                name="duration_type"
                                value="custom"
                                {{ old('duration_type') === 'custom' ? 'checked' : '' }}
                                onchange="toggleCustomDurationDate(this.value)"
                                class="w-4 h-4 text-amber-500 focus:ring-amber-500 border-zinc-300"
                            />
                            <div class="min-w-0">
                                <span class="block text-xs font-bold text-zinc-900">Sampai Tanggal Tertentu</span>
                                <span class="block text-[10px] text-zinc-500 leading-tight mt-0.5">Tentukan batas tanggal berakhir</span>
                            </div>
                        </label>
                    </div>

                    <div id="custom-date-container" class="{{ old('duration_type') === 'custom' ? '' : 'hidden' }} pt-2 border-t border-zinc-200/80">
                        <label for="custom_date" class="block text-[11px] font-bold text-zinc-700 mb-1">
                            Tanggal Terakhir Ditampilkan <span class="text-rose-500 font-bold ml-0.5">*</span>
                        </label>
                        <input
                            type="date"
                            id="custom_date"
                            name="custom_date"
                            value="{{ old('custom_date', now()->addDays(2)->format('Y-m-d')) }}"
                            min="{{ now()->format('Y-m-d') }}"
                            class="px-3 py-2 text-xs rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-semibold"
                        />
                    </div>
                </div>

                <!-- Aksi & Status Awal -->
                <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-zinc-100">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            {{ old() ? (old('is_active') ? 'checked' : '') : 'checked' }}
                            class="rounded border-zinc-300 text-amber-500 focus:ring-amber-500 w-4 h-4 cursor-pointer"
                        />
                        <span class="text-xs font-bold text-zinc-700">Langsung aktifkan siaran</span>
                    </label>

                    <button
                        type="submit"
                        class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 font-black text-xs shadow-xs transition cursor-pointer inline-flex items-center justify-center gap-2"
                    >
                        <x-ui.icon name="megaphone" class="w-4 h-4" />
                        <span>Siarkan Sekarang</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Riwayat & Log Pengumuman -->
        <div class="rounded-2xl border border-zinc-200/90 bg-white overflow-hidden shadow-2xs">
            <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col xl:flex-row xl:items-center justify-between gap-3.5 bg-zinc-50/50 announcements-header-container">
                <div>
                    <h2 class="text-sm font-black text-zinc-900 tracking-tight flex items-center gap-2">
                        <span>Riwayat Log Siaran Pengumuman</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-200 text-zinc-700">
                            {{ $announcements->total() }} Data
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Seluruh catatan pengumuman yang pernah disiarkan beserta status penayangannya</p>
                </div>

                <!-- Filter Form (Rapi, Tidak Scroll Menyamping di Mobile & Tablet) -->
                <form method="GET" action="{{ route('platform.announcements') }}" class="grid grid-cols-1 sm:grid-cols-3 xl:flex xl:items-center gap-2 w-full xl:w-auto announcements-filter-form">
                    <div class="w-full xl:w-40">
                        <x-ui.select
                            name="type"
                            size="sm"
                            icon="tag"
                            :value="$typeFilter"
                            :options="[
                                ['value' => 'all', 'label' => 'Semua Tipe'],
                                ['value' => 'info', 'label' => 'Info'],
                                ['value' => 'maintenance', 'label' => 'Maintenance'],
                                ['value' => 'warning', 'label' => 'Peringatan'],
                                ['value' => 'urgent', 'label' => 'Darurat'],
                            ]"
                            :auto-submit="true"
                        />
                    </div>

                    <div class="w-full xl:w-44">
                        <x-ui.select
                            name="target_role"
                            size="sm"
                            icon="users"
                            :value="$targetFilter"
                            :options="[
                                ['value' => 'all', 'label' => 'Semua Audiens'],
                                ['value' => 'owner', 'label' => 'Owner'],
                                ['value' => 'admin', 'label' => 'Kasir / Admin'],
                                ['value' => 'barber', 'label' => 'Barber'],
                                ['value' => 'customer', 'label' => 'Customer'],
                            ]"
                            :auto-submit="true"
                        />
                    </div>

                    <div class="w-full xl:w-36">
                        <x-ui.select
                            name="status"
                            size="sm"
                            icon="check-circle"
                            :value="$statusFilter"
                            :options="[
                                ['value' => 'all', 'label' => 'Semua Status'],
                                ['value' => 'active', 'label' => 'Aktif'],
                                ['value' => 'inactive', 'label' => 'Nonaktif'],
                            ]"
                            :auto-submit="true"
                        />
                    </div>

                    @if($typeFilter !== 'all' || $targetFilter !== 'all' || $statusFilter !== 'all')
                        <a
                            href="{{ route('platform.announcements') }}"
                            class="sm:col-span-3 xl:w-auto px-3 py-2 text-xs font-bold text-center text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl transition border border-rose-200/60 inline-flex items-center justify-center gap-1"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                            <span>Reset</span>
                        </a>
                    @endif
                </form>
            </div>

            <!-- Tampilan Card Khusus Mobile & Tablet (Super Rapi & Tidak Perlu Scroll Horizontal) -->
            <div class="block lg:hidden divide-y divide-zinc-100">
                @forelse($announcements as $ann)
                    <div class="p-4 sm:p-5 space-y-3.5 hover:bg-zinc-50/50 transition">
                        <!-- Baris Atas: Badge Tipe & Target di Kiri, Status Siar di Kanan -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $ann->typeBadgeClasses() }} border inline-flex items-center gap-1.5 shadow-2xs shrink-0">
                                    <x-ui.icon name="{{ $ann->typeIcon() }}" class="w-3.5 h-3.5 stroke-[2.5]" />
                                    <span>{{ $ann->typeLabel() }}</span>
                                </span>
                                <span class="px-2 py-1 rounded-lg text-[10px] font-bold bg-zinc-100 text-zinc-700 border border-zinc-200 inline-flex items-center gap-1 shrink-0">
                                    <x-ui.icon name="users" class="w-3 h-3 text-zinc-400" />
                                    <span>{{ $ann->targetRoleLabel() }}</span>
                                </span>
                            </div>

                            <div class="shrink-0">
                                @if($ann->isExpired())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-500 border border-zinc-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                        <span>Kedaluwarsa</span>
                                    </span>
                                @elseif($ann->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-2xs whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Aktif Siar</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-600 border border-zinc-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                        <span>Nonaktif</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Kotak Konten Pesan -->
                        <div class="rounded-xl bg-zinc-50/80 border border-zinc-200/80 p-3 sm:p-3.5 space-y-1">
                            @if($ann->title)
                                <h4 class="font-black text-zinc-900 text-xs sm:text-sm tracking-tight leading-snug">{{ $ann->title }}</h4>
                            @endif
                            <p class="text-xs text-zinc-600 leading-relaxed break-words whitespace-pre-line">
                                {{ $ann->message }}
                            </p>
                        </div>

                        <!-- Info Metadata: Tanggal Terbit, Masa Durasi, Pembuat & Respon User -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-[11px] text-zinc-500 pt-0.5">
                            <div class="flex items-center gap-1.5 flex-wrap min-w-0 flex-1">
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                <span class="font-semibold text-zinc-700 whitespace-nowrap">{{ $ann->created_at->translatedFormat('d M Y') }}, {{ $ann->created_at->format('H:i') }} WIB</span>
                                @if($ann->creator)
                                    <span class="text-zinc-300 shrink-0">&bull;</span>
                                    <span class="text-zinc-500 truncate max-w-[130px]">oleh {{ $ann->creator->name }}</span>
                                @endif
                                <span class="text-zinc-300 shrink-0">&bull;</span>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/70 shrink-0">
                                    <x-ui.icon name="clock" class="w-3 h-3 text-amber-600" />
                                    <span>{{ $ann->durationLabel() }}</span>
                                </span>
                            </div>

                            <div class="inline-flex items-center gap-1.5 bg-zinc-100/90 px-2.5 py-1 rounded-lg border border-zinc-200/80 text-[11px] whitespace-nowrap shrink-0 self-start sm:self-auto">
                                <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span class="font-black text-zinc-900 num whitespace-nowrap">{{ $ann->reads_count }} User</span>
                                <span class="text-zinc-400 text-[10px] whitespace-nowrap">({{ $ann->permanently_dismissed_count }} tutup permanen)</span>
                            </div>
                        </div>

                        <!-- Bar Aksi (Tombol Toggle Status & Hapus) -->
                        <div class="pt-2 flex items-center gap-2 border-t border-zinc-100">
                            <!-- Toggle Status -->
                            <form id="toggle-form-mobile-{{ $ann->id }}" method="POST" action="{{ route('platform.announcements.toggle', $ann) }}" class="flex-1">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="button"
                                    data-form-id="toggle-form-mobile-{{ $ann->id }}"
                                    data-mode="{{ $ann->is_active ? 'deactivate' : 'activate' }}"
                                    data-title="{{ $ann->is_active ? 'Nonaktifkan Pengumuman?' : 'Aktifkan Pengumuman?' }}"
                                    data-desc="{{ $ann->is_active ? 'Pengumuman ini tidak akan lagi muncul sebagai popup di dashboard pengguna.' : 'Pengumuman ini akan mulai disiarkan kembali ke target audiens.' }}"
                                    data-btn-text="{{ $ann->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                                    data-btn-class="{{ $ann->is_active ? 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black' : 'bg-emerald-600 hover:bg-emerald-500 text-white font-black' }}"
                                    onclick="openConfirmModal(this.dataset.formId, this.dataset.mode, this.dataset.title, this.dataset.desc, this.dataset.btnText, this.dataset.btnClass)"
                                    class="w-full px-3.5 py-2 rounded-xl text-xs font-bold transition text-center cursor-pointer inline-flex items-center justify-center gap-1.5 shadow-2xs border outline-none focus:outline-none {{
                                        $ann->is_active
                                            ? 'bg-amber-50 hover:bg-amber-100 text-amber-900 border-amber-200'
                                            : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border-emerald-200'
                                    }}"
                                >
                                    <x-ui.icon name="{{ $ann->is_active ? 'slash' : 'check' }}" class="w-3.5 h-3.5" />
                                    <span>{{ $ann->is_active ? 'Nonaktifkan Siaran' : 'Aktifkan Siaran' }}</span>
                                </button>
                            </form>

                            <!-- Hapus -->
                            <form id="delete-form-mobile-{{ $ann->id }}" method="POST" action="{{ route('platform.announcements.destroy', $ann) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="button"
                                    onclick="openConfirmModal(
                                        'delete-form-mobile-{{ $ann->id }}',
                                        'delete',
                                        'Hapus Pengumuman?',
                                        'Pengumuman & riwayat penayangan ini akan dihapus secara permanen dari sistem.',
                                        'Ya, Hapus Permanen',
                                        'bg-rose-600 hover:bg-rose-500 text-white font-black'
                                    )"
                                    class="p-2 rounded-xl text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 transition border border-rose-200 cursor-pointer shadow-2xs inline-flex items-center justify-center shrink-0 outline-none focus:outline-none"
                                    title="Hapus riwayat pengumuman"
                                >
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-zinc-400">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <x-ui.icon name="megaphone" class="w-8 h-8 text-zinc-300 stroke-[1.5]" />
                            <span class="font-bold text-xs">Belum ada riwayat pengumuman yang disiarkan.</span>
                            <span class="text-[11px] text-zinc-400">Gunakan formulir di atas untuk menyiarkan pesan ke pengguna platform.</span>
                        </div>
                    </div>
                @endforelse
            </div>

            <!-- Tabel Data Khusus Desktop (Layar Lebar) -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-100/60 text-zinc-600 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-3 px-4">Tanggal &amp; Terbit</th>
                            <th class="py-3 px-4">Tipe &amp; Target</th>
                            <th class="py-3 px-4">Judul &amp; Pesan</th>
                            <th class="py-3 px-4 text-center">Status Siar</th>
                            <th class="py-3 px-4 text-center">Respon User</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($announcements as $ann)
                            <tr class="hover:bg-zinc-50/70 transition">
                                <!-- Tanggal -->
                                <td class="py-3.5 px-4 whitespace-nowrap align-top">
                                    <span class="font-bold text-zinc-900 block">{{ $ann->created_at->translatedFormat('d M Y') }}</span>
                                    <span class="text-[11px] text-zinc-400">{{ $ann->created_at->format('H:i') }} WIB</span>
                                    @if($ann->creator)
                                        <span class="text-[10px] text-zinc-500 block mt-0.5 truncate max-w-[120px]">oleh {{ $ann->creator->name }}</span>
                                    @endif
                                    <div class="mt-1.5">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200/70">
                                            <x-ui.icon name="clock" class="w-3 h-3 text-amber-600" />
                                            <span>{{ $ann->durationLabel() }}</span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Tipe & Target -->
                                <td class="py-3.5 px-4 whitespace-nowrap align-top space-y-1">
                                    <div>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ $ann->typeBadgeClasses() }} border inline-flex items-center gap-1">
                                            <x-ui.icon name="{{ $ann->typeIcon() }}" class="w-3 h-3" />
                                            <span>{{ $ann->typeLabel() }}</span>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-zinc-100 text-zinc-700 border border-zinc-200 inline-block">
                                            {{ $ann->targetRoleLabel() }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Judul & Pesan -->
                                <td class="py-3.5 px-4 min-w-[280px] max-w-md align-top">
                                    @if($ann->title)
                                        <h4 class="font-black text-zinc-900 text-xs tracking-tight mb-0.5">{{ $ann->title }}</h4>
                                    @endif
                                    <p class="text-xs text-zinc-600 leading-relaxed line-clamp-3">
                                        {{ $ann->message }}
                                    </p>
                                </td>

                                <!-- Status Siar -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap align-top space-y-1">
                                    @if($ann->isExpired())
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-500 border border-zinc-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                                <span>Kedaluwarsa</span>
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-zinc-400 block font-medium">Batas Waktu Habis</span>
                                    @elseif($ann->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-2xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Aktif Siar</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-500 border border-zinc-200">
                                            <span>Nonaktif</span>
                                        </span>
                                    @endif
                                </td>

                                <!-- Respon User -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap align-top">
                                    <span class="font-bold text-zinc-900 block num whitespace-nowrap">{{ $ann->reads_count }} User</span>
                                    <span class="text-[10px] text-zinc-400 block whitespace-nowrap">{{ $ann->permanently_dismissed_count }} tutup permanen</span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap align-top">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Toggle Status -->
                                        <form id="toggle-form-desktop-{{ $ann->id }}" method="POST" action="{{ route('platform.announcements.toggle', $ann) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="button"
                                                data-form-id="toggle-form-desktop-{{ $ann->id }}"
                                                data-mode="{{ $ann->is_active ? 'deactivate' : 'activate' }}"
                                                data-title="{{ $ann->is_active ? 'Nonaktifkan Pengumuman?' : 'Aktifkan Pengumuman?' }}"
                                                data-desc="{{ $ann->is_active ? 'Pengumuman ini tidak akan lagi muncul sebagai popup di dashboard pengguna.' : 'Pengumuman ini akan mulai disiarkan kembali ke target audiens.' }}"
                                                data-btn-text="{{ $ann->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                                                data-btn-class="{{ $ann->is_active ? 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black' : 'bg-emerald-600 hover:bg-emerald-500 text-white font-black' }}"
                                                onclick="openConfirmModal(this.dataset.formId, this.dataset.mode, this.dataset.title, this.dataset.desc, this.dataset.btnText, this.dataset.btnClass)"
                                                class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer outline-none focus:outline-none {{ $ann->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' }}"
                                                title="{{ $ann->is_active ? 'Hentikan siaran pengumuman' : 'Aktifkan siaran pengumuman' }}"
                                            >
                                                {{ $ann->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <!-- Hapus -->
                                        <form id="delete-form-desktop-{{ $ann->id }}" method="POST" action="{{ route('platform.announcements.destroy', $ann) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="button"
                                                onclick="openConfirmModal(
                                                    'delete-form-desktop-{{ $ann->id }}',
                                                    'delete',
                                                    'Hapus Pengumuman?',
                                                    'Pengumuman & riwayat penayangan ini akan dihapus secara permanen dari sistem.',
                                                    'Ya, Hapus Permanen',
                                                    'bg-rose-600 hover:bg-rose-500 text-white font-black'
                                                )"
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition cursor-pointer outline-none focus:outline-none"
                                                title="Hapus riwayat pengumuman"
                                            >
                                                <x-ui.icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-zinc-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <x-ui.icon name="megaphone" class="w-8 h-8 text-zinc-300 stroke-[1.5]" />
                                        <span class="font-bold text-xs">Belum ada riwayat pengumuman yang disiarkan.</span>
                                        <span class="text-[11px] text-zinc-400">Gunakan formulir di atas untuk menyiarkan pesan ke pengguna platform.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($announcements->hasPages())
                <div class="p-4 border-t border-zinc-100 bg-zinc-50/50">
                    {{ $announcements->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Konfirmasi Popup Kecil Ringkas -->
    <div
        id="announcement-confirm-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-xs transition-opacity duration-150"
        role="dialog"
        aria-modal="true"
        onclick="if(event.target === this) closeConfirmModal()"
    >
        <div class="relative w-full max-w-xs sm:max-w-sm rounded-2xl bg-white border border-zinc-200/90 shadow-2xl p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-start gap-3">
                <div id="confirm-modal-icon-bg" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
                    <span id="confirm-modal-icon"></span>
                </div>
                <div class="space-y-1">
                    <h3 id="confirm-modal-title" class="text-sm font-black text-zinc-950 tracking-tight leading-snug"></h3>
                    <p id="confirm-modal-desc" class="text-xs text-zinc-600 leading-relaxed"></p>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeConfirmModal()"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition cursor-pointer outline-none focus:outline-none"
                >
                    Batal
                </button>
                <button
                    type="button"
                    id="confirm-modal-submit-btn"
                    class="px-4 py-2 rounded-xl text-xs font-black shadow-xs transition cursor-pointer outline-none focus:outline-none"
                >
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentConfirmFormId = null;

        function toggleCustomDurationDate(val) {
            const container = document.getElementById('custom-date-container');
            if (!container) return;
            if (val === 'custom') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function openConfirmModal(formId, actionType, title, desc, submitText, btnClasses) {
            currentConfirmFormId = formId;
            const modal = document.getElementById('announcement-confirm-modal');
            const titleEl = document.getElementById('confirm-modal-title');
            const descEl = document.getElementById('confirm-modal-desc');
            const submitBtn = document.getElementById('confirm-modal-submit-btn');
            const iconBg = document.getElementById('confirm-modal-icon-bg');
            const iconEl = document.getElementById('confirm-modal-icon');

            titleEl.textContent = title;
            descEl.textContent = desc;
            submitBtn.textContent = submitText;
            submitBtn.className = 'px-4 py-2 rounded-xl text-xs shadow-xs transition cursor-pointer outline-none focus:outline-none ' + btnClasses;

            if (actionType === 'delete') {
                iconBg.className = 'w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 border border-rose-200';
                iconEl.innerHTML = '<svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>';
            } else if (actionType === 'deactivate') {
                iconBg.className = 'w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200';
                iconEl.innerHTML = '<svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>';
            } else {
                iconBg.className = 'w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-200';
                iconEl.innerHTML = '<svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeConfirmModal() {
            const modal = document.getElementById('announcement-confirm-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            currentConfirmFormId = null;
        }

        document.getElementById('confirm-modal-submit-btn').addEventListener('click', function() {
            if (currentConfirmFormId) {
                const form = document.getElementById(currentConfirmFormId);
                if (form) {
                    form.submit();
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConfirmModal();
            }
        });
    </script>
</x-layouts.app>
