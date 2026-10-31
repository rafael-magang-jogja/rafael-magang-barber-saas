<x-layouts.app title="Dashboard Owner" subtitle="Ringkasan performa barbershop hari ini" role="owner">
    @if(($branchesCount ?? 0) === 0)
        <div class="mb-5 p-4 sm:p-5 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-zinc-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 shadow-2xs">
            <div class="flex items-center gap-3.5 min-w-0">
                <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0">
                    <x-ui.icon name="store" class="w-5 h-5 text-amber-700" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-black text-sm text-zinc-950">Selamat Datang! Tambahkan Cabang Pertama Anda</h3>
                    <p class="text-xs text-zinc-600 mt-0.5 leading-relaxed">Akun barbershop Anda sudah aktif. Daftarkan nama dan lokasi cabang operasional pertama Anda untuk mulai mengatur antrean dan melayani pelanggan.</p>
                </div>
            </div>
            <a
                href="{{ route('owner.branches.create') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 text-xs font-black shadow-sm transition shrink-0 whitespace-nowrap cursor-pointer"
            >
                <x-ui.icon name="plus" class="w-3.5 h-3.5 stroke-[3]" />
                <span>Tambah Cabang Sekarang</span>
            </a>
        </div>
    @endif

    <div class="stat-cards-grid grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 transition-all duration-500 ease-in-out">
        <x-ui.stat
            label="Omzet Hari Ini"
            :value="'Rp' . number_format($todayRevenue, 0, ',', '.')"
            trend="Transaksi terverifikasi"
            highlight
        />
        <x-ui.stat label="Customer Hari Ini" :value="$customersToday . ' orang'" />
        <x-ui.stat label="Booking Aktif" :value="$activeBookings . ' booking'" />
        <x-ui.stat label="Barber Aktif" :value="$barbers->count() . ' barber'" />
    </div>

    @if($draftPayroll)
        <div class="mt-4 rounded-2xl bg-zinc-950 text-white border border-zinc-800 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white/10 text-white grid place-items-center shrink-0">
                    <x-ui.icon name="banknote" class="w-5 h-5 text-white" />
                </div>
                <div class="min-w-0">
                    <a href="{{ route('owner.payroll', ['payroll_id' => $draftPayroll->id]) }}" class="hover:underline">
                        <p class="font-extrabold text-sm text-white">Payroll Periode {{ $draftPayroll->period_start->format('d M') }} – {{ $draftPayroll->period_end->format('d M Y') }} ({{ $draftPayroll->status->value }})</p>
                    </a>
                    <p class="text-xs text-white/80 mt-0.5">{{ $draftPayroll->items_count ?? $draftPayroll->items->count() }} barber · Total Rp{{ number_format($draftPayroll->total_amount, 0, ',', '.') }}</p>
                </div>
            </div>
            <a
                href="{{ route('owner.payroll', ['payroll_id' => $draftPayroll->id]) }}"
                class="inline-flex items-center justify-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-zinc-950 text-xs font-black shadow-sm transition cursor-pointer shrink-0"
                title="Buka menu Gaji & Komisi"
            >
                <x-ui.icon name="draft" class="w-3.5 h-3.5 text-zinc-950" />
                <span>Draft</span>
                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
            </a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
        <x-ui.card class="lg:col-span-2">
            <x-slot:header>
                <h2 class="font-bold">Transaksi Terbaru</h2>
            </x-slot:header>

            @if($latestOrders->isEmpty())
                <x-ui.empty-state title="Belum ada transaksi hari ini" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-zinc-400 text-left border-b border-zinc-100">
                            <tr>
                                <th class="py-2">Invoice</th>
                                <th>Customer</th>
                                <th>Barber</th>
                                <th>Status</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach($latestOrders as $order)
                                <tr>
                                    <td class="py-2.5 font-semibold num">{{ $order->number }}</td>
                                    <td>{{ $order->customerProfile?->name ?? 'Walk-in' }}</td>
                                    <td>{{ $order->barber?->display_name ?? '-' }}</td>
                                    <td><x-ui.badge :status="$order->payment_status" /></td>
                                    <td class="text-right font-bold num">Rp{{ number_format($order->grand_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card>
            <x-slot:header>
                <h2 class="font-bold">Tim Barber &amp; Cabang</h2>
            </x-slot:header>

            <div class="space-y-3">
                @foreach($barbers as $barber)
                    <div class="flex items-center justify-between gap-3">
                        <div class="w-9 h-9 rounded-full bg-zinc-900 text-amber-400 grid place-items-center font-bold text-sm">
                            {{ substr($barber->display_name ?? $barber->user->name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ $barber->display_name ?? $barber->user->name }}</p>
                            <p class="text-xs text-zinc-400 truncate">{{ $barber->specialty }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs font-bold num text-amber-600">
                            <x-ui.icon name="star" class="w-3.5 h-3.5 text-amber-500" />
                            <span>{{ number_format($barber->rating_avg, 1) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
