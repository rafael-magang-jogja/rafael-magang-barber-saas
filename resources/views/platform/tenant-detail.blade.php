<x-layouts.app :title="'Kelola Barbershop: ' . $tenant->name" subtitle="Detail profil, cabang, staf, akun owner & performa bisnis" role="superadmin">
    @php
        $tenantStatusOptions = [
            ['value' => 'active', 'label' => 'Aktif (Active)', 'sublabel' => 'Tenant beroperasi normal'],
            ['value' => 'trial', 'label' => 'Masa Uji Coba (Trial)', 'sublabel' => 'Masa evaluasi sistem'],
            ['value' => 'suspended', 'label' => 'Disuspend (Suspended)', 'sublabel' => 'Akses sistem dinonaktifkan'],
            ['value' => 'cancelled', 'label' => 'Dibatalkan (Cancelled)', 'sublabel' => 'Langganan dihentikan'],
        ];
    @endphp

    <!-- Tombol Kembali -->
    <div class="mb-5 flex items-center justify-between">
        <a
            href="{{ route('platform.dashboard') }}"
            class="inline-flex items-center gap-2 text-xs font-bold text-zinc-500 hover:text-zinc-950 transition group"
        >
            <span class="p-1.5 rounded-xl bg-white border border-zinc-200 group-hover:border-zinc-300 shadow-2xs">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 text-zinc-600" />
            </span>
            <span>Kembali ke Daftar Barbershop</span>
        </a>
    </div>

    <!-- Header Profil Singkat Tenant -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-2xl text-white font-black text-xl flex items-center justify-center shrink-0 shadow-xs ring-1 ring-black/10" style="background-color: {{ $tenant->brand_color ?: '#18181b' }}">
                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-black text-zinc-900 tracking-tight truncate">{{ $tenant->name }}</h1>
                        <x-ui.badge :status="$tenant->status" plain />
                        @if($activeSubscription?->plan)
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-zinc-700">
                                <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-amber-500" />
                                <span>Paket {{ $activeSubscription->plan->name }}</span>
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 mt-1 truncate flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-zinc-600 text-[11px] font-semibold">/b/{{ $tenant->slug }}</span>
                        <span class="text-zinc-300">·</span>
                        <span>Terdaftar: <b class="text-zinc-700">{{ $tenant->created_at->translatedFormat('d M Y') }}</b></span>
                        @if($activeSubscription?->ends_at)
                            <span class="text-zinc-300">·</span>
                            <span>Masa Aktif: <b class="text-zinc-700">s/d {{ $activeSubscription->ends_at->translatedFormat('d M Y') }}</b></span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0 flex-wrap">
                <!-- Tombol Suspend / Aktifkan Tenant dengan Popup Konfirmasi -->
                @if($tenant->status === \App\Enums\TenantStatus::Suspended)
                    <button
                        type="button"
                        onclick="document.getElementById('modal-confirm-toggle').showModal()"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer inline-flex items-center gap-1.5 shadow-xs"
                    >
                        <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-white" />
                        <span>Aktifkan Kembali Tenant</span>
                    </button>
                @else
                    <button
                        type="button"
                        onclick="document.getElementById('modal-confirm-toggle').showModal()"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer inline-flex items-center gap-1.5 shadow-xs"
                    >
                        <x-ui.icon name="shield-alert" class="w-3.5 h-3.5 text-white" />
                        <span>Suspend Barbershop</span>
                    </button>
                @endif

                <a
                    href="{{ url('/b/' . $tenant->slug) }}"
                    target="_blank"
                    class="px-3.5 py-2 rounded-xl bg-zinc-900 text-white hover:bg-zinc-800 text-xs font-bold transition inline-flex items-center gap-1.5 shadow-xs"
                >
                    <span>Kunjungi Portal</span>
                    <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-300" />
                </a>
            </div>
        </div>

        <!-- 5 Stat Mini Tenant (Netral & Bersih, Tanpa Warna Background Mencolok) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3 mt-4 pt-4 border-t border-zinc-100 text-xs">
            <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/80">
                <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">Total Outlet</span>
                <p class="text-base font-black text-zinc-900 num mt-0.5">{{ $tenant->branches->count() }} Cabang</p>
            </div>
            <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/80">
                <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">Total Staf</span>
                <p class="text-base font-black text-zinc-900 num mt-0.5">{{ $tenant->memberships->count() }} Orang</p>
            </div>
            <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/80">
                <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">Total Transaksi</span>
                <p class="text-base font-black text-zinc-900 num mt-0.5">{{ $ordersCount }} Invoice</p>
            </div>
            <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-200/80">
                <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">Total Omzet (GMV)</span>
                <p class="text-base font-black text-zinc-900 num mt-0.5">Rp{{ number_format($totalGmv, 0, ',', '.') }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1 p-3 rounded-xl bg-zinc-50 border border-zinc-200/80">
                <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">Paket Langganan</span>
                <p class="text-sm font-black text-zinc-900 mt-0.5 truncate">
                    {{ $activeSubscription?->plan?->name ?? 'Masa Trial' }}
                </p>
                <p class="text-[10px] text-zinc-500 mt-0.5 truncate">
                    {{ $activeSubscription?->ends_at ? 's/d ' . $activeSubscription->ends_at->translatedFormat('d M Y') : 'Masa aktif tak terbatas' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Grid Konten Utama: 2 Kolom (Kiri: Profil, Cabang & Staf, Billing | Kanan: Owner, Akses & Data, Zona Bahaya) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Profil & Operasional (Lebar 2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Form Edit Profil Barbershop -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="store" class="w-4 h-4 text-zinc-700" />
                        <h2 class="font-bold text-sm sm:text-base text-zinc-900">Profil &amp; Konfigurasi Barbershop</h2>
                    </div>
                </x-slot:header>

                <form method="POST" action="{{ route('platform.tenants.update', $tenant) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <x-ui.input label="Nama Barbershop" name="name" :value="$tenant->name" required />
                        <x-ui.input label="Slug URL (Permanen)" name="slug" :value="$tenant->slug" readonly disabled class="bg-zinc-100 text-zinc-500 cursor-not-allowed font-mono" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <x-ui.input label="Nomor Telepon Barbershop" name="phone" :value="$tenant->phone" placeholder="08..." />
                        <x-ui.input label="Email Bisnis" name="email" type="email" :value="$tenant->email" placeholder="admin@barber.com" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-end">
                        <div>
                            <x-ui.select
                                label="Status Operasional Barbershop"
                                name="status"
                                icon="shield-check"
                                size="md"
                                :value="$tenant->status->value"
                                :options="$tenantStatusOptions"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Warna Brand Utama</label>
                            <div class="flex items-center gap-2.5">
                                <div class="relative w-9 h-9 rounded-xl border border-zinc-200 shadow-2xs overflow-hidden shrink-0 flex items-center justify-center p-0.5 bg-white">
                                    <input
                                        type="color"
                                        id="brand-color-input"
                                        name="brand_color"
                                        value="{{ $tenant->brand_color ?: '#D97706' }}"
                                        class="absolute inset-0 w-[150%] h-[150%] -top-1/4 -left-1/4 cursor-pointer border-0"
                                        oninput="document.getElementById('brand-color-val').textContent = this.value"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <span id="brand-color-val" class="text-xs font-mono font-bold text-zinc-800">{{ $tenant->brand_color ?: '#D97706' }}</span>
                                    <p class="text-[10px] text-zinc-400">Aksen tema portal &amp; booking</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1">Alamat Kantor / Pusat</label>
                        <textarea name="address" rows="2" class="w-full text-xs font-semibold p-3 rounded-xl border border-zinc-200 focus:outline-none focus:ring-1 focus:ring-zinc-950 bg-zinc-50" placeholder="Jl. Sudirman No...">{{ $tenant->address }}</textarea>
                    </div>

                    <div class="pt-3 border-t border-zinc-100 flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                        >
                            <x-ui.icon name="check" class="w-4 h-4 text-white" />
                            <span>Simpan Perubahan Profil</span>
                        </button>
                    </div>
                </form>
            </x-ui.card>

            <!-- Cabang & Karyawan Berdampingan (Grid 2 Kolom) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Daftar Outlet / Cabang -->
                <x-ui.card class="flex flex-col">
                    <x-slot:header>
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="store" class="w-4 h-4 text-zinc-700" />
                                <h2 class="font-bold text-sm text-zinc-900">Cabang / Outlet</h2>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-700">
                                {{ $tenant->branches->count() }}
                            </span>
                        </div>
                    </x-slot:header>

                    <div class="divide-y divide-zinc-100 max-h-72 overflow-y-auto pr-1">
                        @forelse($tenant->branches as $branch)
                            <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <p class="font-bold text-zinc-900 flex items-center gap-1.5 truncate">
                                        <span class="truncate">{{ $branch->name }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-zinc-100 text-zinc-700 font-mono shrink-0">{{ $branch->code }}</span>
                                    </p>
                                    <p class="text-[11px] text-zinc-400 truncate mt-0.5">{{ $branch->address ?: 'Alamat belum diatur' }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $branch->is_active ? 'bg-zinc-100 text-zinc-800' : 'bg-zinc-100 text-zinc-400' }}">
                                        {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-center py-6 text-zinc-400 italic text-xs">Belum ada cabang terdaftar.</p>
                        @endforelse
                    </div>
                </x-ui.card>

                <!-- Daftar Barber & Staf -->
                <x-ui.card class="flex flex-col">
                    <x-slot:header>
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="users" class="w-4 h-4 text-zinc-700" />
                                <h2 class="font-bold text-sm text-zinc-900">Barber &amp; Staf</h2>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-700">
                                {{ $tenant->memberships->count() }}
                            </span>
                        </div>
                    </x-slot:header>

                    <div class="divide-y divide-zinc-100 max-h-72 overflow-y-auto pr-1">
                        @forelse($tenant->memberships as $mem)
                            <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($mem->display_name ?? $mem->user?->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-zinc-900 truncate">{{ $mem->display_name ?? $mem->user?->name }}</p>
                                        <p class="text-[11px] text-zinc-400 truncate">{{ ucfirst((string) $mem->role->value) }} &bull; {{ $mem->branch?->name ?? 'Semua Cabang' }}</p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold shrink-0 {{ $mem->is_active ? 'text-emerald-600' : 'text-zinc-400' }}">
                                    {{ $mem->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        @empty
                            <p class="text-center py-6 text-zinc-400 italic text-xs">Belum ada karyawan terdaftar.</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>

            <!-- Riwayat Tagihan & Invoice Langganan -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="banknote" class="w-4 h-4 text-zinc-700" />
                            <h2 class="font-bold text-sm text-zinc-900">Riwayat Tagihan Langganan ({{ $subscriptionInvoices->count() }})</h2>
                        </div>
                        <a href="{{ route('platform.subscriptions', ['tenant_id' => $tenant->id]) }}" class="text-[11px] font-bold text-zinc-700 hover:text-zinc-950 underline">
                            Lihat Semua di Billing &rarr;
                        </a>
                    </div>
                </x-slot:header>

                <div class="divide-y divide-zinc-100 text-xs">
                    @forelse($subscriptionInvoices as $inv)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-zinc-900">{{ $inv->number }}</span>
                                    @if($inv->status === 'paid')
                                        <span class="text-xs font-bold text-emerald-600">Lunas</span>
                                    @elseif($inv->status === 'pending')
                                        <span class="text-xs font-bold text-amber-600">Pending</span>
                                    @else
                                        <span class="text-xs font-bold text-zinc-500">{{ ucfirst($inv->status) }}</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    <span>Masa Aktif: <strong>s/d {{ $inv->subscription?->ends_at?->translatedFormat('d M Y') ?? '-' }}</strong></span>
                                    <span class="text-zinc-300 mx-1">&bull;</span>
                                    <span>Tenggat: {{ $inv->due_at?->translatedFormat('d M Y') ?? '-' }}</span>
                                    @if($inv->paid_at)
                                        <span class="text-zinc-700 font-semibold ml-1">(Lunas: {{ $inv->paid_at->translatedFormat('d M Y') }})</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm text-zinc-900 num">
                                    Rp{{ number_format($inv->amount, 0, ',', '.') }}
                                </span>

                                @if($inv->status === 'pending')
                                    <form method="POST" action="{{ route('platform.subscriptions.confirm', $inv->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] shadow-xs transition cursor-pointer"
                                            title="Konfirmasi Pembayaran Lunas"
                                        >
                                            <x-ui.icon name="check" class="w-3 h-3 text-white" />
                                            <span>Konfirmasi</span>
                                        </button>
                                    </form>
                                @else
                                    <a
                                        href="{{ route('platform.subscriptions.show', $inv->id) }}"
                                        target="_blank"
                                        class="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition"
                                        title="Cetak Dokumen Invoice"
                                    >
                                        <x-ui.icon name="file-text" class="w-3.5 h-3.5" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-6 text-zinc-400 italic text-xs">Belum ada riwayat tagihan langganan.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <!-- Kolom Kanan: Akun Owner, Akses & Data Terkait, Zona Bahaya (Lebar 1/3) -->
        <div class="space-y-6">
            <!-- Akun Owner & Reset Password -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="user" class="w-4 h-4 text-zinc-700" />
                        <h2 class="font-bold text-sm text-zinc-900">Akun Owner &amp; Kredensial</h2>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-2">
                        <div class="flex items-center gap-3 pb-2 border-b border-zinc-200/60">
                            <div class="w-9 h-9 rounded-xl bg-zinc-900 text-white font-extrabold flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($tenant->owner?->name ?? 'O', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-zinc-900 truncate">{{ $tenant->owner?->name ?? 'Owner belum terdaftar' }}</p>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-zinc-200 text-zinc-800">Owner Utama</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-0.5">
                            <span class="text-zinc-500">Email Login:</span>
                            <span class="font-bold text-zinc-800 font-mono truncate max-w-[170px]">{{ $tenant->owner?->email ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-500">No HP (WA):</span>
                            <span class="font-bold text-zinc-800 font-mono">{{ $tenant->owner?->phone ?? '-' }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('platform.tenants.reset-password', $tenant) }}" class="space-y-2.5 pt-2 border-t border-zinc-100">
                        @csrf
                        <x-ui.input
                            label="Reset Password Owner"
                            name="new_password"
                            type="password"
                            placeholder="Ketik password baru (default: password)"
                        />
                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                        >
                            <x-ui.icon name="key" class="w-3.5 h-3.5 text-white" />
                            <span>Reset Password Akun Owner</span>
                        </button>
                    </form>
                </div>
            </x-ui.card>

            <!-- Akses & Data Terkait -->
            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="compass" class="w-4 h-4 text-zinc-700" />
                        <h2 class="font-bold text-sm text-zinc-900">Akses &amp; Data Terkait</h2>
                    </div>
                </x-slot:header>

                <div class="space-y-2 text-xs">
                    <a
                        href="{{ route('platform.transactions', ['tenant_id' => $tenant->id]) }}"
                        class="p-2.5 rounded-xl border border-zinc-200/80 hover:bg-zinc-50 hover:border-zinc-300 transition flex items-center justify-between group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-zinc-100 text-zinc-800 grid place-items-center shrink-0">
                                <x-ui.icon name="banknote" class="w-4 h-4 text-zinc-700" />
                            </div>
                            <div>
                                <span class="font-bold text-zinc-900 group-hover:text-zinc-950 block">Transaksi Kasir</span>
                                <span class="text-[10px] text-zinc-500">{{ $ordersCount }} invoice tercatat</span>
                            </div>
                        </div>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-zinc-400 group-hover:translate-x-0.5 transition" />
                    </a>


                    <a
                        href="{{ route('platform.subscriptions', ['tenant_id' => $tenant->id]) }}"
                        class="p-2.5 rounded-xl border border-zinc-200/80 hover:bg-zinc-50 hover:border-zinc-300 transition flex items-center justify-between group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-zinc-100 text-zinc-800 grid place-items-center shrink-0">
                                <x-ui.icon name="credit-card" class="w-4 h-4 text-zinc-700" />
                            </div>
                            <div>
                                <span class="font-bold text-zinc-900 group-hover:text-zinc-950 block">Billing &amp; Langganan</span>
                                <span class="text-[10px] text-zinc-500">{{ $subscriptionInvoices->count() }} invoice langganan</span>
                            </div>
                        </div>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-zinc-400 group-hover:translate-x-0.5 transition" />
                    </a>
                </div>
            </x-ui.card>

            <!-- Zona Bahaya: Hapus / Arsipkan Barbershop -->
            <x-ui.card class="border-zinc-200/90">
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="alert-triangle" class="w-4 h-4 text-rose-600" />
                        <h2 class="font-bold text-sm text-zinc-900">Hapus / Arsipkan Barbershop</h2>
                    </div>
                </x-slot:header>

                <div class="space-y-3 text-xs text-zinc-600">
                    <p class="leading-relaxed">
                        Menghapus tenant akan menonaktifkan seluruh akses owner, kasir, barber, dan portal booking publik.
                    </p>

                    <button
                        type="button"
                        onclick="document.getElementById('modal-confirm-destroy').showModal()"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                    >
                        <x-ui.icon name="trash" class="w-3.5 h-3.5 text-white" />
                        <span>Hapus / Arsipkan Barbershop</span>
                    </button>
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Dialog Modal: Konfirmasi Suspend / Aktifkan Tenant -->
    <dialog id="modal-confirm-toggle" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs m-auto fixed inset-0 w-[calc(100%-2rem)] max-w-md overflow-hidden border border-zinc-200 bg-white">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                    <x-ui.icon name="{{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'check-circle' : 'shield-alert' }}" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-zinc-900 leading-tight">
                        {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'Aktifkan Kembali Barbershop?' : 'Suspend Barbershop?' }}
                    </h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ $tenant->name }}</p>
                </div>
            </div>

            <p class="text-xs text-zinc-600 leading-relaxed">
                @if($tenant->status === \App\Enums\TenantStatus::Suspended)
                    Akses operasional barbershop ini akan diaktifkan kembali. Owner, kasir, barber, dan pelanggan dapat kembali mengakses dashboard dan booking portal.
                @else
                    Barbershop yang disuspend tidak akan bisa login ke dashboard dan halaman portal booking publik akan ditutup sementara hingga Anda mengaktifkannya kembali.
                @endif
            </p>

            <div class="mt-6 pt-4 border-t border-zinc-100 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    onclick="document.getElementById('modal-confirm-toggle').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition cursor-pointer"
                >
                    Batal
                </button>
                <form method="POST" action="{{ route('platform.tenants.toggle', $tenant) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white transition cursor-pointer shadow-xs {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700' }}"
                    >
                        {{ $tenant->status === \App\Enums\TenantStatus::Suspended ? 'Ya, Aktifkan Barbershop' : 'Ya, Suspend Barbershop' }}
                    </button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Dialog Modal: Konfirmasi Hapus Tenant -->
    <dialog id="modal-confirm-destroy" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs m-auto fixed inset-0 w-[calc(100%-2rem)] max-w-md overflow-hidden border border-zinc-200 bg-white">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                    <x-ui.icon name="trash" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-zinc-900 leading-tight">Hapus / Arsipkan Barbershop?</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ $tenant->name }}</p>
                </div>
            </div>

            <p class="text-xs text-zinc-600 leading-relaxed">
                Menghapus barbershop ini akan menonaktifkan seluruh akun operasional (owner, kasir, barber) dan menutup portal publik selamanya. Tindakan ini hanya dapat dipulihkan secara manual melalui basis data.
            </p>

            <div class="mt-6 pt-4 border-t border-zinc-100 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    onclick="document.getElementById('modal-confirm-destroy').close()"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition cursor-pointer"
                >
                    Batal
                </button>
                <form method="POST" action="{{ route('platform.tenants.destroy', $tenant) }}">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer shadow-xs"
                    >
                        Ya, Hapus Barbershop
                    </button>
                </form>
            </div>
        </div>
    </dialog>
</x-layouts.app>
