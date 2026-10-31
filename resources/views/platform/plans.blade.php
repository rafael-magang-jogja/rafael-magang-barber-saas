<x-layouts.app title="Paket Langganan Platform" subtitle="Atur paket sewa, harga bulanan, kuota cabang & fitur SaaS" role="superadmin">
    <!-- Header Actions (3 Paket Standar Platform) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-base sm:text-lg font-black text-zinc-900 tracking-tight flex items-center gap-2">
                <x-ui.icon name="coins" class="w-5 h-5 text-amber-600" />
                <span>Katalog Paket Langganan SaaS</span>
            </h1>
            <p class="text-xs text-zinc-500 mt-0.5">Atur skema harga sewa bulanan dan kuota outlet untuk barbershop penyewa</p>
        </div>

        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-zinc-100 border border-zinc-200/80 text-zinc-700 text-xs font-bold self-start sm:self-auto shrink-0 shadow-2xs">
            <x-ui.icon name="shield-check" class="w-4 h-4 text-emerald-600" />
            <span>3 Paket Standar (Starter, Pro, Business)</span>
        </div>
    </div>

    <!-- Grid Kartu 3 Paket Langganan Tetap -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        @foreach($plans as $plan)
            <div class="rounded-2xl border p-5 transition-all flex flex-col justify-between {{ $plan->is_active ? 'bg-white border-zinc-200/90 shadow-2xs hover:border-zinc-300' : 'bg-zinc-50 border-zinc-200/60 opacity-60' }}">
                <div>
                    <!-- Header Paket: Nama, Kode & Status -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="font-mono text-[10px] text-zinc-400 uppercase tracking-widest font-bold">{{ $plan->code }}</span>
                            <h3 class="text-base font-black text-zinc-900 leading-tight mt-0.5">{{ $plan->name }}</h3>
                        </div>
                        <span class="text-xs font-bold {{ $plan->is_active ? 'text-emerald-600' : 'text-zinc-400' }}">
                            {{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <!-- Harga Bulanan -->
                    <div class="mt-4 pb-4 border-b border-zinc-100">
                        <span class="text-[10px] text-zinc-400 font-bold uppercase tracking-wider block">Tarif Sewa:</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-2xl font-black text-zinc-950 num">Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}</span>
                            <span class="text-xs text-zinc-400 font-semibold">/ bulan</span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-2 leading-relaxed min-h-[34px]">{{ $plan->description ?: 'Paket langganan barbershop standar.' }}</p>
                    </div>

                    <!-- Daftar Fitur Lengkap (Disamakan Seperti di Owner Subscription) -->
                    <div class="py-4 space-y-2.5 text-xs text-zinc-700 border-b border-zinc-100">
                        <!-- Batas Cabang -->
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Maksimal <strong>{{ $plan->max_branches ? $plan->max_branches . ' Cabang' : 'Unlimited' }}</strong></span>
                        </div>

                        <!-- Batas Staf -->
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Maksimal <strong>{{ $plan->max_staff ? $plan->max_staff . ' Karyawan' : 'Unlimited Karyawan' }}</strong></span>
                        </div>

                        <!-- 1. Sistem Penggajian (Payroll) -->
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>
                                @if($plan->code === 'starter')
                                    Payroll Sederhana (Gaji Pokok Dasar)
                                @elseif($plan->code === 'pro')
                                    <strong>Payroll Full</strong> (Multi-Skema Gaji)
                                @else
                                    <strong>Payroll Full Multi-Outlet</strong> (Multi-Skema &amp; Cabang)
                                @endif
                            </span>
                        </div>

                        <!-- 2. Komisi Barber Otomatis -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'starter')
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Komisi Otomatis Real-time</span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span><strong>Komisi Otomatis</strong> (Per Barber, Layanan &amp; Kategori)</span>
                            @endif
                        </div>

                        <!-- 3. Dokumen Cetak Slip Gaji Resmi -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'business')
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span><strong>Cetak Slip Gaji Resmi</strong> (Siap Print &amp; PDF)</span>
                            @elseif($plan->code === 'pro')
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Cetak Slip Gaji Resmi <span class="text-[10px] text-zinc-400 no-underline font-normal">(Eksklusif Business)</span></span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Cetak Slip Gaji Resmi</span>
                            @endif
                        </div>

                        <!-- Grafik Analitik Fitur Pro (Efektivitas & Omzet) -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'starter')
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Grafik Analitik Pro (Efektivitas &amp; Omzet)</span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span><strong>Grafik Analitik Pro</strong> (Efektivitas &amp; Omzet)</span>
                            @endif
                        </div>

                        <!-- Notifikasi WhatsApp Pelanggan -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'starter')
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Notifikasi WhatsApp Pelanggan</span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span>Notifikasi WhatsApp Pelanggan</span>
                            @endif
                        </div>

                        <!-- Layar TV Display Antrean Live -->
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Layar TV Display Antrean Live</span>
                        </div>

                        <!-- Custom Logo & Warna Tema Barbershop (Pro & Business) -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'starter')
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">Custom Logo &amp; Warna Tema Barbershop</span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span>Custom Logo &amp; Warna Tema Barbershop</span>
                            @endif
                        </div>

                        <!-- White-label & Subdomain Khusus (Eksklusif Business) -->
                        <div class="flex items-center gap-2">
                            @if($plan->code === 'business')
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <span><strong>White-label &amp; Subdomain Khusus</strong></span>
                            @else
                                <span class="w-4 h-4 rounded-full bg-rose-50 text-rose-500 border border-rose-200/70 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                </span>
                                <span class="text-zinc-400 line-through">White-label &amp; Subdomain Khusus</span>
                            @endif
                        </div>
                    </div>

                    <!-- Tenant Pengguna Info -->
                    <div class="pt-3 pb-1 flex items-center justify-between text-xs text-zinc-600">
                        <span class="flex items-center gap-1.5 font-medium">
                            <x-ui.icon name="users" class="w-3.5 h-3.5 text-zinc-400" />
                            <span>Tenant Menggunakan:</span>
                        </span>
                        <span class="font-black text-amber-700 num">
                            {{ $plan->subscriptions_count }} Barbershop
                        </span>
                    </div>
                </div>

                <!-- Footer Card: Tombol Edit & Nonaktifkan (TIDAK BISA DIHAPUS) -->
                <div class="pt-4 border-t border-zinc-100 flex items-center justify-end gap-2">
                    <a
                        href="{{ route('platform.plans.edit', $plan) }}"
                        class="px-3.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 active:scale-95 text-zinc-800 text-xs font-bold transition flex items-center gap-1.5"
                    >
                        <x-ui.icon name="edit" class="w-3.5 h-3.5 text-zinc-500" />
                        <span>Edit</span>
                    </a>

                    <form method="POST" action="{{ route('platform.plans.toggle', $plan) }}" onsubmit="return confirm('{{ $plan->is_active ? 'Nonaktifkan paket ' . $plan->name . ' dari pendaftaran langganan baru?' : 'Aktifkan kembali paket ' . $plan->name . '?' }}')">
                        @csrf
                        @method('PATCH')
                        @if($plan->is_active)
                            <button
                                type="submit"
                                class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-zinc-950 text-xs font-bold transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                                title="Nonaktifkan Paket (Tidak dihapus)"
                            >
                                <x-ui.icon name="eye-off" class="w-3.5 h-3.5 text-zinc-950" />
                                <span>Nonaktifkan</span>
                            </button>
                        @else
                            <button
                                type="submit"
                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                                title="Aktifkan Kembali Paket"
                            >
                                <x-ui.icon name="check" class="w-3.5 h-3.5 text-white" />
                                <span>Aktifkan</span>
                            </button>
                        @endif
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
