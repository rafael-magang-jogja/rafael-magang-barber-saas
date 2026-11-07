<x-layouts.app title="Database & Loyalitas Customer" subtitle="Database riwayat pelanggan dan loyalitas transaksi" role="owner">
    <!-- Stat Cards Customer -->
    <div class="stat-cards-grid grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5 transition-all duration-500 ease-in-out">
        <x-ui.stat
            label="Total Pelanggan Cukur"
            :value="$totalCustomers . ' orang'"
            trend="Pernah menggunakan layanan"
            highlight
        />
        <x-ui.stat
            label="Pelanggan Aktif (30 Hari)"
            :value="$activeCustomers . ' orang'"
        />
        <x-ui.stat
            label="Total Akumulasi Transaksi"
            :value="'Rp' . number_format($totalSpentAll, 0, ',', '.')"
        />
    </div>

    <!-- Filter Pencarian -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" action="{{ route('owner.customers') }}" class="flex items-center gap-2 flex-1 max-w-md">
            <div class="relative w-full">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama pelanggan atau nomor WhatsApp..."
                    class="w-full text-xs font-semibold pl-8 pr-3 py-2 rounded-xl bg-white border border-zinc-200 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                />
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-400">
                    <x-ui.icon name="search" class="w-3.5 h-3.5" />
                </div>
            </div>

            <x-ui.button type="submit" size="sm" variant="secondary">
                <span>Cari</span>
            </x-ui.button>

            @if(request('search'))
                <a
                    href="{{ route('owner.customers') }}"
                    class="inline-flex items-center gap-1 text-xs font-extrabold text-rose-600 hover:text-rose-700 px-2 py-1.5 transition"
                >
                    <x-ui.icon name="x" class="w-3.5 h-3.5" />
                    <span>Reset</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Tabel Database Customer CRM -->
    <x-ui.card data-pagination-container="owner-customers">
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <h2 class="font-bold flex items-center gap-2">
                    <x-ui.icon name="user-check" class="w-4 h-4 text-amber-600" />
                    <span>Daftar Pelanggan Barbershop</span>
                </h2>
                <span class="text-xs text-zinc-400 font-medium">Menampilkan {{ $customers->firstItem() ?? 0 }} - {{ $customers->lastItem() ?? 0 }} dari {{ $customers->total() }} customer</span>
            </div>
        </x-slot:header>

        @if($customers->isEmpty())
            <x-ui.empty-state title="Belum ada data pelanggan yang pernah cukur atau sesuai pencarian" />
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200/80">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500">
                            <th class="py-3 px-4 sm:px-5">Nama Customer</th>
                            <th class="py-3 px-4 sm:px-5">Kontak WhatsApp / HP</th>
                            <th class="py-3 px-4 sm:px-5">Kunjungan Terakhir</th>
                            <th class="py-3 px-4 sm:px-5 text-right">Total Belanja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 text-xs">
                        @foreach($customers as $c)
                            @php
                                $cleanPhone = preg_replace('/[^0-9]/', '', (string)$c->phone);
                                if (str_starts_with($cleanPhone, '0')) {
                                    $cleanPhone = '62' . substr($cleanPhone, 1);
                                }
                            @endphp
                            <tr class="hover:bg-zinc-50/70 transition">
                                <td class="py-3.5 px-4 sm:px-5 font-bold text-zinc-900">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-amber-500/15 text-amber-700 font-extrabold text-xs grid place-items-center shrink-0">
                                            {{ strtoupper(substr($c->name, 0, 1)) }}
                                        </div>
                                        <span class="font-semibold text-zinc-900">{{ $c->name }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 sm:px-5 font-mono">
                                    @if($c->phone)
                                        <a
                                            href="https://wa.me/{{ $cleanPhone }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold transition"
                                            title="Chat WhatsApp"
                                        >
                                            <span>{{ $c->phone }}</span>
                                            <x-ui.icon name="external-link" class="w-3 h-3 text-emerald-600" />
                                        </a>
                                    @else
                                        <span class="text-zinc-400 font-sans">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 sm:px-5 text-zinc-600">
                                    @if($c->last_visit_at)
                                        <div class="inline-flex items-center gap-1.5 text-zinc-700 font-medium">
                                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                            <span>{{ $c->last_visit_at->translatedFormat('d M Y') }}</span>
                                        </div>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 sm:px-5 text-right font-mono font-bold text-zinc-900">
                                    Rp{{ number_format($c->total_spent, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-100">
                {{ $customers->links() }}
            </div>
        @endif
    </x-ui.card>
</x-layouts.app>
