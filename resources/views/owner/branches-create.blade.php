<x-layouts.app
    :title="$editingBranch ? 'Edit Cabang' : 'Tambah Cabang Baru'"
    :subtitle="$editingBranch ? 'Perbarui informasi lokasi & operasional cabang ' . $editingBranch->name : 'Daftarkan outlet atau cabang baru barbershop Anda'"
    role="owner"
>
    <div class="max-w-2xl mx-auto space-y-4">
        <!-- Tombol Kembali -->
        <div>
            <a
                href="{{ route('owner.branches') }}"
                class="inline-flex items-center gap-2 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition py-1"
            >
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Kembali ke Daftar Cabang</span>
            </a>
        </div>

        <!-- Form Card -->
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2 w-full">
                    <h2 class="font-bold flex items-center gap-2 text-base text-zinc-900">
                        <x-ui.icon :name="$editingBranch ? 'edit' : 'plus'" class="w-4 h-4 text-amber-600" />
                        <span>{{ $editingBranch ? 'Edit Cabang: ' . $editingBranch->name : 'Tambah Cabang Baru' }}</span>
                    </h2>
                    @if($editingBranch)
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 text-[11px] font-extrabold">
                            Mode Edit
                        </span>
                    @endif
                </div>
            </x-slot:header>

            <form
                method="POST"
                action="{{ $editingBranch ? route('owner.branches.update', $editingBranch) : route('owner.branches.store') }}"
                class="space-y-4"
            >
                @csrf
                @if($editingBranch)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <x-ui.input
                            label="Nama Cabang *"
                            name="name"
                            :value="old('name', $editingBranch?->name)"
                            placeholder="Contoh: Cabang Senopati"
                            required
                        />
                    </div>
                    <div>
                        <x-ui.input
                            label="Kode Cabang (Prefix) *"
                            name="code"
                            :value="old('code', $editingBranch?->code)"
                            placeholder="Contoh: SNP"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-ui.input
                        label="Nomor Telepon *"
                        name="phone"
                        :value="old('phone', $editingBranch?->phone)"
                        placeholder="Contoh: 0215550101"
                        required
                    />

                    <x-ui.input
                        label="Toleransi Telat Booking (Menit) *"
                        name="late_tolerance_minutes"
                        type="number"
                        :value="old('late_tolerance_minutes', $editingBranch?->late_tolerance_minutes ?? 15)"
                        required
                    />
                </div>

                <x-ui.input
                    label="Alamat Lengkap *"
                    name="address"
                    :value="old('address', $editingBranch?->address)"
                    placeholder="Contoh: Jl. Senopati No. 10, Kebayoran Baru, Jakarta Selatan"
                    required
                />

                <!-- Input Link Google Maps -->
                <div class="space-y-1">
                    <x-ui.input
                        label="Link Google Maps (Lokasi Tempat) *"
                        name="maps_url"
                        :value="old('maps_url', $editingBranch?->maps_url)"
                        placeholder="https://maps.app.goo.gl/... atau https://maps.google.com/?q=..."
                        required
                    />
                    <p class="text-[11px] text-zinc-500">
                        Pelanggan dapat mengklik link ini untuk langsung melihat lokasi &amp; rute cabang di Google Maps.
                    </p>
                </div>

                @if($editingBranch)
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80">
                        <label class="block text-xs font-bold text-zinc-700 mb-2">Status Operasional Cabang</label>
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700 cursor-pointer">
                                <input
                                    type="radio"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', (string) (int) ($editingBranch->is_active ?? true)) === '1' ? 'checked' : '' }}
                                    class="text-amber-500 focus:ring-amber-500"
                                />
                                <span>Aktif Beroperasi</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700 cursor-pointer">
                                <input
                                    type="radio"
                                    name="is_active"
                                    value="0"
                                    {{ old('is_active', (string) (int) ($editingBranch->is_active ?? true)) === '0' ? 'checked' : '' }}
                                    class="text-rose-500 focus:ring-rose-500"
                                />
                                <span>Nonaktif / Libur</span>
                            </label>
                        </div>
                    </div>
                @endif

                <div class="pt-2 flex items-center justify-between gap-2.5">
                    <div>
                        @if($editingBranch)
                            <button
                                type="button"
                                onclick="if(confirm('Apakah Anda yakin ingin menghapus cabang {{ $editingBranch->name }} ({{ $editingBranch->code }})? Seluruh riwayat cabang ini akan diarsipkan.')) { document.getElementById('delete-branch-form').submit(); }"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 shadow-sm transition cursor-pointer"
                            >
                                <x-ui.icon name="trash" class="w-4 h-4" />
                                <span>Hapus Cabang</span>
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2.5">
                        <x-ui.button :href="route('owner.branches')" variant="ghost" size="md">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="md">
                            <x-ui.icon :name="$editingBranch ? 'check' : 'plus'" class="w-4 h-4" />
                            <span>{{ $editingBranch ? 'Perbarui Cabang' : 'Simpan Cabang' }}</span>
                        </x-ui.button>
                    </div>
                </div>
            </form>

            @if($editingBranch)
                <form id="delete-branch-form" method="POST" action="{{ route('owner.branches.destroy', $editingBranch) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
