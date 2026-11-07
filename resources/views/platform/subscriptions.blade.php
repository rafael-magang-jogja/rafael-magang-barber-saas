<x-layouts.app title="Tagihan & Langganan SaaS" subtitle="Monitoring pembayaran sewa software, riwayat invoice & aktivasi otomatis via payment gateway" role="superadmin">
    @php
        $statusOptions = [
            ['value' => 'all', 'label' => 'Semua Status', 'sublabel' => null],
            ['value' => 'pending', 'label' => 'Menunggu Konfirmasi' . ($pendingCount > 0 ? " ({$pendingCount})" : ''), 'sublabel' => 'Pending'],
            ['value' => 'paid', 'label' => 'Lunas' . ($paidCount > 0 ? " ({$paidCount})" : ''), 'sublabel' => 'Paid'],
            ['value' => 'cancelled', 'label' => 'Dibatalkan', 'sublabel' => 'Cancelled'],
        ];

        $tenantSelectOptions = $allTenants->map(fn ($t) => [
            'value' => (string) $t->id,
            'label' => $t->name,
            'sublabel' => $t->slug,
        ])->all();

        $gatewayOptions = [
            ['value' => 'xendit', 'label' => 'Xendit Gateway', 'sublabel' => 'QRIS & Virtual Account Otomatis'],
            ['value' => 'free_tier', 'label' => 'Paket Gratis (Free Tier)', 'sublabel' => 'Bypass Rp0'],
            ['value' => 'manual_transfer', 'label' => 'Transfer Bank Manual', 'sublabel' => 'BCA / Mandiri / BNI / BRI'],
            ['value' => 'cash', 'label' => 'Pembayaran Tunai (Cash)', 'sublabel' => 'Offline / Langsung'],
        ];
    @endphp

    <!-- 4 Kartu Metrik Tagihan SaaS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <!-- 1. Pendapatan Bulan Ini -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-zinc-500">Pendapatan SaaS Bulan Ini</span>
                <span class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-600 grid place-items-center shrink-0">
                    <x-ui.icon name="trending-up" class="w-3.5 h-3.5" />
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-zinc-900 mt-2 tracking-tight">
                Rp{{ number_format($totalMonthlyRevenue, 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-zinc-400 font-medium block mt-0.5">Bulan {{ now()->translatedFormat('F Y') }}</span>
        </div>

        <!-- 2. Total Akumulasi Pendapatan -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-zinc-500">Total Akumulasi SaaS</span>
                <span class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="coins" class="w-3.5 h-3.5" />
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-zinc-900 mt-2 tracking-tight">
                Rp{{ number_format($totalAllTimeRevenue, 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-zinc-400 font-medium block mt-0.5">Seluruh transaksi lunas</span>
        </div>

        <!-- 3. Aktivasi Otomatis via Gateway -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-zinc-500">Aktivasi Langganan</span>
                <span class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-600 grid place-items-center shrink-0">
                    <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-zinc-900 mt-2 tracking-tight">
                100% Otomatis
            </p>
            <span class="text-[11px] text-emerald-700 font-bold block mt-0.5">
                Real-time via Payment Gateway
            </span>
        </div>

        <!-- 4. Tagihan Lunas -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white border border-zinc-200/80 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-zinc-500">Tagihan Terverifikasi</span>
                <span class="w-7 h-7 rounded-xl bg-blue-500/10 text-blue-600 grid place-items-center shrink-0">
                    <x-ui.icon name="shield-check" class="w-3.5 h-3.5" />
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-zinc-900 mt-2 tracking-tight">
                {{ $paidCount }} Lunas
            </p>
            <span class="text-[11px] text-zinc-400 font-medium block mt-0.5">Invoice langganan aktif</span>
        </div>
    </div>

    <!-- Filter & Pencarian Tagihan (Ukuran Seragam & Rapi) -->
    <x-ui.card class="mb-6">
        <form method="GET" action="{{ route('platform.subscriptions') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- 1. Search Input -->
            <div>
                <label class="block text-xs font-semibold text-zinc-700 mb-1.5">Cari Tagihan / Barbershop</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $searchFilter }}"
                        placeholder="No. invoice, barbershop, owner..."
                        class="w-full h-[44px] text-sm font-medium pl-10 pr-3.5 rounded-xl border border-zinc-200 bg-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 transition shadow-2xs"
                    />
                    <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none flex items-center justify-center">
                        <x-ui.icon name="search" class="w-4 h-4" />
                    </div>
                </div>
            </div>

            <!-- 2. Status Filter (Custom Dropdown UI) -->
            <div>
                <x-ui.select
                    label="Status Pembayaran"
                    name="status"
                    icon="shield-check"
                    size="md"
                    :value="$statusFilter"
                    :options="$statusOptions"
                />
            </div>

            <!-- 3. Tenant Filter (Custom Dropdown UI) -->
            <div>
                <x-ui.select
                    label="Barbershop / Tenant"
                    name="tenant_id"
                    icon="store"
                    size="md"
                    placeholder="Semua Barbershop"
                    :value="$tenantFilter ? (string) $tenantFilter : ''"
                    :options="$tenantSelectOptions"
                    search-placeholder="Cari barbershop..."
                />
            </div>

            <!-- 4. Tombol Filter & Reset -->
            <div>
                <label class="block text-xs font-semibold text-zinc-700 mb-1.5 select-none hidden sm:block" aria-hidden="true">&nbsp;</label>
                <div class="flex items-center gap-2 h-[44px]">
                    <button
                        type="submit"
                        class="flex-1 h-[44px] px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white font-bold text-sm transition cursor-pointer flex items-center justify-center gap-2 shadow-2xs hover:shadow-xs"
                    >
                        <x-ui.icon name="filter" class="w-4 h-4" />
                        <span>Terapkan</span>
                    </button>

                    @if($searchFilter || $statusFilter !== 'all' || $tenantFilter)
                        <a
                            href="{{ route('platform.subscriptions') }}"
                            class="h-[44px] w-[44px] rounded-xl border border-zinc-200 bg-white hover:bg-zinc-100 text-zinc-600 transition cursor-pointer flex items-center justify-center shrink-0 shadow-2xs hover:shadow-xs active:scale-95"
                            title="Reset Filter"
                        >
                            <x-ui.icon name="rotate-ccw" class="w-4 h-4" />
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </x-ui.card>

    <!-- Tabel & Kartu Daftar Tagihan SaaS -->
    <div class="rounded-2xl border border-zinc-200/90 bg-white overflow-hidden shadow-2xs mb-6" data-pagination-container="platform-subscriptions">
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50/50">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 grid place-items-center shrink-0">
                    <x-ui.icon name="banknote" class="w-5 h-5" />
                </span>
                <div>
                    <h2 class="font-black text-sm sm:text-base text-zinc-900">Daftar Tagihan &amp; Invoice Langganan</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Total {{ $invoices->total() }} invoice terdata dalam sistem</p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
                <a
                    href="{{ route('platform.settings') }}"
                    class="h-9 inline-flex items-center gap-1.5 px-3.5 rounded-xl border border-zinc-200/90 bg-white hover:bg-zinc-50 text-zinc-700 text-xs font-bold transition shadow-2xs cursor-pointer select-none"
                    title="Atur Rekening Bank Pembayaran Platform"
                >
                    <x-ui.icon name="building-2" class="w-3.5 h-3.5 text-zinc-500" />
                    <span>Kelola Rekening Bank</span>
                </a>
                <div class="h-9 inline-flex items-center gap-1.5 px-3.5 rounded-xl border border-zinc-200/90 bg-white text-zinc-600 text-xs font-bold shadow-2xs select-none">
                    <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-zinc-400" />
                    <span>Halaman {{ $invoices->currentPage() }} dari {{ max(1, $invoices->lastPage()) }}</span>
                </div>
            </div>
        </div>

        @if($invoices->isEmpty())
            <div class="text-center py-16 px-4">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 text-zinc-400 grid place-items-center mx-auto mb-3">
                    <x-ui.icon name="receipt" class="w-6 h-6" />
                </div>
                <h3 class="font-extrabold text-sm text-zinc-900">Belum Ada Tagihan Ditemukan</h3>
                <p class="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
                    Tidak ada data invoice langganan yang sesuai dengan filter pencarian Anda saat ini.
                </p>
            </div>
        @else
            <!-- Desktop Table View (>= 1024px) -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-zinc-200/90 bg-zinc-50/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500">
                            <th class="py-2.5 px-3">Invoice &amp; Terbit</th>
                            <th class="py-2.5 px-3">Barbershop &amp; Owner</th>
                            <th class="py-2.5 px-3">Jenis &amp; Paket</th>
                            <th class="py-2.5 px-3">Periode Masa Aktif</th>
                            <th class="py-2.5 px-3">Nominal</th>
                            <th class="py-2.5 px-3">Metode &amp; Bukti</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($invoices as $inv)
                            @php
                                $sub = $inv->subscription;
                                $targetPlan = $inv->targetPlan ?? $sub?->plan;
                                $periodEnd = $inv->period_ends_at;
                                $periodStart = $inv->period_starts_at;
                                $dueAt = $inv->due_at;
                                $isInvoiceExpired = $periodEnd ? $periodEnd->isPast() : false;
                                $daysRemaining = $periodEnd && !$isInvoiceExpired ? (int) ceil(now()->floatDiffInDays($periodEnd)) : 0;
                                $type = $inv->type ?? 'renewal';

                                // Format WhatsApp Kuitansi / Bukti Lunas ke Owner (HANYA UNTUK YANG SUDAH LUNAS)
                                $waLink = null;
                                if ($inv->status === 'paid') {
                                    $ownerPhoneClean = preg_replace('/[^0-9]/', '', (string) ($inv->tenant?->owner?->phone ?? ''));
                                    if (str_starts_with($ownerPhoneClean, '0')) {
                                        $ownerPhoneClean = '62' . substr($ownerPhoneClean, 1);
                                    } elseif (str_starts_with($ownerPhoneClean, '8')) {
                                        $ownerPhoneClean = '62' . $ownerPhoneClean;
                                    }

                                    $platformNameVal = \App\Support\Platform\PlatformConfig::name();
                                    $ownerName = $inv->tenant?->owner?->name ?? 'Owner';
                                    $tenantName = $inv->tenant?->name ?? 'Barbershop';
                                    $planName = $targetPlan?->name ?? 'Paket Langganan';
                                    $typeName = ucfirst($type);
                                    $invoiceUrl = route('owner.subscription.invoice', $inv->id);

                                    $waMessageText = "Halo Bapak/Ibu {$ownerName} ({$tenantName}),\n\n"
                                        . "Terima kasih, pembayaran tagihan langganan SaaS Anda telah kami terima dan berstatus *LUNAS*:\n\n"
                                        . "• *No. Tagihan*: {$inv->number}\n"
                                        . "• *Paket*: {$planName} ({$typeName})\n"
                                        . "• *Total Pembayaran*: Rp" . number_format($inv->amount, 0, ',', '.') . "\n"
                                        . "• *Status*: LUNAS (Terverifikasi)\n"
                                        . "• *Masa Aktif Layanan*: s/d " . ($periodEnd ? $periodEnd->translatedFormat('d F Y') : '-') . "\n\n"
                                        . "Anda dapat melihat dan mencetak dokumen faktur / kuitansi resmi melalui link berikut:\n"
                                        . $invoiceUrl . "\n\n"
                                        . "Operasional barbershop Anda telah aktif sepenuhnya. Terima kasih atas kerja samanya!\n\n"
                                        . "Salam hangat,\n*{$platformNameVal}*";

                                    $waLink = !empty($ownerPhoneClean) ? 'https://wa.me/' . $ownerPhoneClean . '?text=' . rawurlencode($waMessageText) : null;
                                }
                            @endphp
                            <tr class="hover:bg-zinc-50/70 transition duration-150">
                                <!-- 1. No. Invoice & Tanggal Terbit -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <span class="font-mono font-bold text-zinc-900 block">{{ $inv->number }}</span>
                                    <span class="text-[11px] text-zinc-400 block">{{ $inv->created_at?->translatedFormat('d M Y') }}</span>
                                </td>

                                <!-- 2. Barbershop & Owner -->
                                <td class="py-2.5 px-3 align-middle">
                                    <a href="{{ $inv->tenant ? route('platform.tenants.show', $inv->tenant) : '#' }}" class="font-bold text-zinc-900 hover:text-amber-600 block truncate max-w-[190px]" title="{{ $inv->tenant?->name }}">
                                        {{ $inv->tenant?->name ?? 'Tenant Dihapus' }}
                                    </a>
                                    <span class="text-[11px] text-zinc-500 block truncate max-w-[190px]" title="{{ $inv->tenant?->owner?->name }} ({{ $inv->tenant?->owner?->phone }})">
                                        {{ $inv->tenant?->owner?->name ?? '-' }} <span class="text-zinc-400">({{ $inv->tenant?->owner?->phone ?? '-' }})</span>
                                    </span>
                                </td>

                                <!-- 3. Jenis & Paket Langganan -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-xs font-bold {{ $inv->type_text_color }}">
                                             {{ $inv->type_label }}
                                        </span>
                                        <span class="text-zinc-300 font-bold">&bull;</span>
                                        <span class="font-bold text-zinc-900">{{ $targetPlan?->name ?? 'Paket Kustom' }}</span>
                                    </div>
                                    <span class="text-[11px] text-zinc-400 block">Maks {{ $targetPlan?->max_branches ?? 1 }} Cabang</span>
                                </td>

                                <!-- 4. Periode Masa Aktif Khusus Invoice Ini -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <span class="font-bold text-zinc-900 block">s/d {{ $periodEnd?->translatedFormat('d M Y') ?? '-' }}</span>
                                    @if($inv->status === 'pending' || $inv->status === 'unpaid')
                                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 block mt-0.5 whitespace-nowrap">Menunggu Verifikasi</span>
                                    @elseif($inv->isSuperseded())
                                        <span class="text-[11px] font-medium text-zinc-400 block">Digantikan</span>
                                    @elseif($isInvoiceExpired)
                                        <span class="text-[11px] font-bold text-rose-600 block">Kedaluwarsa</span>
                                    @elseif($periodEnd && $daysRemaining <= 3)
                                        <span class="text-[11px] font-bold text-amber-600 block">Sisa {{ $daysRemaining }} Hari</span>
                                    @elseif($periodEnd)
                                        <span class="text-[11px] font-medium text-emerald-600 block">Sisa {{ $daysRemaining }} Hari</span>
                                    @endif
                                </td>

                                <!-- 5. Nominal -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <span class="font-bold text-zinc-900">Rp{{ number_format($inv->amount, 0, ',', '.') }}</span>
                                </td>

                                <!-- 6. Metode Pembayaran & Bukti Transfer -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-zinc-700 font-semibold text-[11px]">
                                            @if($inv->gateway === 'xendit')
                                                <span class="inline-flex items-center gap-1 text-emerald-700 font-black">
                                                    <x-ui.icon name="zap" class="w-3 h-3 text-emerald-600" />
                                                    <span>Xendit ({{ $inv->xendit_payment_channel ?? 'Online' }})</span>
                                                </span>
                                            @elseif($inv->gateway === 'free_tier')
                                                <span class="text-zinc-600 font-bold">Gratis (Rp0)</span>
                                            @elseif($inv->payment_method === 'transfer')
                                                <span>Transfer {{ $inv->bank_name ?? 'Bank' }}</span>
                                            @elseif($inv->payment_method === 'cash')
                                                <span>Tunai (Cash)</span>
                                            @else
                                                <span>{{ strtoupper(str_replace('_', ' ', $inv->gateway ?: 'MANUAL')) }}</span>
                                            @endif
                                        </span>
                                        @if($inv->proof_url)
                                            <button
                                                type="button"
                                                data-proof-url="{{ $inv->proof_url }}"
                                                data-number="{{ $inv->number }}"
                                                data-tenant-name="{{ $inv->tenant?->name ?? 'Tenant' }}"
                                                data-notes="{{ $inv->notes ?? '' }}"
                                                data-method="{{ $inv->payment_method ?? 'transfer' }}"
                                                data-bank="{{ $inv->bank_name ?? '-' }}"
                                                onclick="openProofModal(this.dataset.proofUrl, this.dataset.number, this.dataset.tenantName, this.dataset.notes, this.dataset.method, this.dataset.bank)"
                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 hover:text-amber-700 hover:underline cursor-pointer"
                                            >
                                                <x-ui.icon name="image" class="w-3.5 h-3.5" />
                                                <span>Lihat Bukti Bayar</span>
                                            </button>
                                        @elseif($inv->gateway === 'xendit')
                                            <span class="text-[10px] text-emerald-600 font-medium">Otomatis Terverifikasi</span>
                                        @elseif($inv->gateway === 'free_tier')
                                            <span class="text-[10px] text-zinc-400 font-medium">Paket Gratis (Rp0)</span>
                                        @else
                                            <span class="text-[10px] text-zinc-400 italic">Belum ada bukti</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 7. Status -->
                                <td class="py-2.5 px-3 align-middle whitespace-nowrap">
                                    <div>
                                        @if($inv->status === 'paid')
                                            <span class="font-bold text-emerald-600">Lunas</span>
                                            @if($inv->paid_at)
                                                <span class="text-[10px] text-zinc-400 block">{{ $inv->paid_at->translatedFormat('d M Y') }}</span>
                                            @endif
                                        @elseif($inv->status === 'pending')
                                            <span class="font-bold text-amber-600">Pending</span>
                                            @if($dueAt)
                                                <span class="text-[10px] {{ $dueAt->isPast() ? 'text-rose-600 font-bold' : 'text-zinc-400' }} block">Due: {{ $dueAt->translatedFormat('d M') }}</span>
                                            @endif
                                        @elseif($inv->status === 'cancelled')
                                            <span class="font-bold text-zinc-400 block">Dibatalkan</span>
                                            <span class="text-[10px] text-zinc-400 block">Oleh: {{ ucfirst($inv->cancelled_by ?? 'Sistem') }}</span>
                                            @if($inv->cancellation_reason)
                                                <span class="text-[10px] text-rose-500 italic block truncate max-w-[130px]" title="{{ $inv->cancellation_reason }}">"{{ $inv->cancellation_reason }}"</span>
                                            @endif
                                        @else
                                            <span class="font-bold text-rose-600">Unpaid</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 8. Aksi -->
                                <td class="py-2.5 px-3 text-right align-middle whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($inv->status === 'paid' && $waLink)
                                            <!-- Tombol Kirim Kuitansi / Bukti Lunas via WhatsApp -->
                                            <a
                                                href="{{ $waLink }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                                title="Kirim Kuitansi &amp; Faktur Lunas ke WhatsApp Owner ({{ $inv->tenant?->owner?->phone ?? '-' }})"
                                            >
                                                <x-ui.icon name="whatsapp" class="w-4 h-4 fill-current" />
                                            </a>
                                        @endif

                                        @if($inv->status === 'pending' || $inv->status === 'unpaid')
                                            <!-- Tombol Tolak / Batalkan -->
                                            <button
                                                type="button"
                                                data-cancel-url="{{ route('platform.subscriptions.cancel', $inv->id) }}"
                                                data-number="{{ $inv->number }}"
                                                data-tenant-name="{{ $inv->tenant?->name ?? 'Tenant' }}"
                                                data-amount="Rp{{ number_format($inv->amount, 0, ',', '.') }}"
                                                onclick="openRejectInvoiceModal(this.dataset.cancelUrl, this.dataset.number, this.dataset.tenantName, this.dataset.amount)"
                                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                                title="Batalkan Tagihan"
                                            >
                                                <x-ui.icon name="x" class="w-4 h-4 stroke-[3]" />
                                            </button>
                                        @endif

                                        <!-- Buka Detail Invoice -->
                                        <a
                                            href="{{ route('platform.subscriptions.show', $inv->id) }}"
                                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-zinc-800 hover:bg-zinc-700 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                            title="Buka Dokumen Invoice"
                                        >
                                            <x-ui.icon name="file-text" class="w-4 h-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card List View (< 1024px) -->
            <div class="lg:hidden divide-y divide-zinc-100">
                @foreach($invoices as $inv)
                    @php
                        $sub = $inv->subscription;
                        $targetPlan = $inv->targetPlan ?? $sub?->plan;
                        $periodEnd = $inv->period_ends_at;
                        $periodStart = $inv->period_starts_at;
                        $dueAt = $inv->due_at;
                        $isInvoiceExpired = $periodEnd ? $periodEnd->isPast() : false;
                        $daysRemaining = $periodEnd && !$isInvoiceExpired ? (int) ceil(now()->floatDiffInDays($periodEnd)) : 0;
                        $type = $inv->type ?? 'renewal';

                        // Format WhatsApp Kuitansi / Bukti Lunas ke Owner (HANYA UNTUK YANG SUDAH LUNAS)
                        $waLink = null;
                        if ($inv->status === 'paid') {
                            $ownerPhoneClean = preg_replace('/[^0-9]/', '', (string) ($inv->tenant?->owner?->phone ?? ''));
                            if (str_starts_with($ownerPhoneClean, '0')) {
                                $ownerPhoneClean = '62' . substr($ownerPhoneClean, 1);
                            } elseif (str_starts_with($ownerPhoneClean, '8')) {
                                $ownerPhoneClean = '62' . $ownerPhoneClean;
                            }

                            $platformNameVal = \App\Support\Platform\PlatformConfig::name();
                            $ownerName = $inv->tenant?->owner?->name ?? 'Owner';
                            $tenantName = $inv->tenant?->name ?? 'Barbershop';
                            $planName = $targetPlan?->name ?? 'Paket Langganan';
                            $typeName = ucfirst($type);
                            $invoiceUrl = route('owner.subscription.invoice', $inv->id);

                            $waMessageText = "Halo Bapak/Ibu {$ownerName} ({$tenantName}),\n\n"
                                . "Terima kasih, pembayaran tagihan langganan SaaS Anda telah kami terima dan berstatus *LUNAS*:\n\n"
                                . "• *No. Tagihan*: {$inv->number}\n"
                                . "• *Paket*: {$planName} ({$typeName})\n"
                                . "• *Total Pembayaran*: Rp" . number_format($inv->amount, 0, ',', '.') . "\n"
                                . "• *Status*: LUNAS (Terverifikasi)\n"
                                . "• *Masa Aktif Layanan*: s/d " . ($periodEnd ? $periodEnd->translatedFormat('d F Y') : '-') . "\n\n"
                                . "Anda dapat melihat dan mencetak dokumen faktur / kuitansi resmi melalui link berikut:\n"
                                . $invoiceUrl . "\n\n"
                                . "Operasional barbershop Anda telah aktif sepenuhnya. Terima kasih atas kerja samanya!\n\n"
                                . "Salam hangat,\n*{$platformNameVal}*";

                            $waLink = !empty($ownerPhoneClean) ? 'https://wa.me/' . $ownerPhoneClean . '?text=' . rawurlencode($waMessageText) : null;
                        }
                    @endphp
                    <div class="p-4 sm:p-5 space-y-4 hover:bg-zinc-50/50 transition duration-150">
                        <!-- Row 1: Nomor Invoice & Status & Nominal -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-zinc-900">
                                        {{ $inv->number }}
                                    </span>
                                    <span class="text-xs font-bold {{ $inv->type_text_color }}">
                                        {{ $inv->type_label }}
                                    </span>
                                    <span class="text-zinc-300">&bull;</span>
                                    @if($inv->status === 'paid')
                                        <span class="text-xs font-black text-emerald-600">Lunas</span>
                                    @elseif($inv->status === 'pending')
                                        <span class="text-xs font-black text-amber-600">Menunggu Verifikasi</span>
                                    @elseif($inv->status === 'cancelled')
                                        <span class="text-xs font-bold text-zinc-400">Dibatalkan</span>
                                    @else
                                        <span class="text-xs font-bold text-rose-600">Unpaid</span>
                                    @endif
                                </div>

                                <!-- Barbershop & Owner -->
                                <div class="pt-0.5">
                                    <a href="{{ $inv->tenant ? route('platform.tenants.show', $inv->tenant) : '#' }}" class="font-black text-sm text-zinc-900 hover:text-amber-600 transition inline-flex items-center gap-1">
                                        <span>{{ $inv->tenant?->name ?? 'Tenant Dihapus' }}</span>
                                        <x-ui.icon name="external-link" class="w-3 h-3 text-zinc-400" />
                                    </a>
                                    <p class="text-xs text-zinc-600 mt-0.5">
                                        <span class="font-medium text-zinc-800">{{ $inv->tenant?->owner?->name ?? '-' }}</span>
                                        @if($inv->tenant?->owner?->phone)
                                            <span class="text-zinc-400 text-[11px] ml-0.5">({{ $inv->tenant?->owner?->phone }})</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="font-black text-base text-zinc-950 block">
                                    Rp{{ number_format($inv->amount, 0, ',', '.') }}
                                </span>
                                <span class="text-[10px] text-zinc-400 font-bold uppercase tracking-wider block mt-0.5">
                                    30 Hari
                                </span>
                            </div>
                        </div>

                        <!-- Row 2: Grid Info Paket & Masa Aktif -->
                        <div class="p-3.5 rounded-xl bg-zinc-50/80 border border-zinc-100 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <!-- Info Paket & Metode Pembayaran -->
                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400 uppercase font-black tracking-wider block">Paket &amp; Pembayaran</span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-black text-xs text-zinc-900">{{ $targetPlan?->name ?? 'Paket Kustom' }}</span>
                                    <span class="text-zinc-300">&bull;</span>
                                    <span class="text-[11px] text-zinc-500">Maks {{ $targetPlan?->max_branches ?? 1 }} Cabang</span>
                                </div>
                                <div class="text-xs font-semibold text-zinc-800 pt-0.5">
                                    @if($inv->gateway === 'xendit')
                                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold">
                                            <x-ui.icon name="zap" class="w-3 h-3 text-emerald-600" />
                                            <span>Xendit Online ({{ $inv->xendit_payment_channel ?? 'QRIS/VA' }})</span>
                                        </span>
                                    @elseif($inv->gateway === 'free_tier')
                                        <span class="text-zinc-600 font-bold">Gratis (Rp0)</span>
                                    @elseif($inv->payment_method === 'transfer')
                                        <span>Transfer Bank ({{ $inv->bank_name ?? 'Bank' }})</span>
                                    @elseif($inv->payment_method === 'cash')
                                        <span>Tunai (Cash)</span>
                                    @else
                                        <span>{{ strtoupper(str_replace('_', ' ', $inv->gateway ?: 'MANUAL')) }}</span>
                                    @endif
                                </div>
                                @if($inv->proof_url)
                                    <div class="pt-1">
                                        <button
                                            type="button"
                                            data-proof-url="{{ $inv->proof_url }}"
                                            data-number="{{ $inv->number }}"
                                            data-tenant-name="{{ $inv->tenant?->name ?? 'Tenant' }}"
                                            data-notes="{{ $inv->notes ?? '' }}"
                                            data-method="{{ $inv->payment_method ?? 'transfer' }}"
                                            data-bank="{{ $inv->bank_name ?? '-' }}"
                                            onclick="openProofModal(this.dataset.proofUrl, this.dataset.number, this.dataset.tenantName, this.dataset.notes, this.dataset.method, this.dataset.bank)"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 hover:text-amber-700 hover:underline cursor-pointer"
                                        >
                                            <x-ui.icon name="image" class="w-3.5 h-3.5" />
                                            <span>Lihat Bukti Bayar</span>
                                        </button>
                                    </div>
                                @elseif($inv->gateway === 'xendit')
                                    <div class="pt-1">
                                        <span class="text-[10px] text-emerald-600 font-medium">Otomatis Terverifikasi</span>
                                    </div>
                                @elseif($inv->gateway === 'free_tier')
                                    <div class="pt-1">
                                        <span class="text-[10px] text-zinc-400 font-medium">Paket Gratis (Rp0)</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Info Masa Aktif & Berakhir Khusus Tagihan Ini -->
                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400 uppercase font-black tracking-wider block">Periode Masa Aktif</span>
                                <div class="flex items-center gap-1.5">
                                    <x-ui.icon name="clock" class="w-3 h-3 text-zinc-400 shrink-0" />
                                    @if($periodEnd)
                                        <span class="font-black text-xs text-zinc-900">s/d {{ $periodEnd->translatedFormat('d M Y') }}</span>
                                    @else
                                        <span class="text-zinc-400 italic text-xs">-</span>
                                    @endif
                                </div>
                                <div>
                                    @if($inv->status === 'pending' || $inv->status === 'unpaid')
                                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 inline-block mt-0.5">Menunggu Verifikasi</span>
                                    @elseif($inv->isSuperseded())
                                        <span class="font-medium text-xs text-zinc-400 block">Digantikan</span>
                                    @elseif($isInvoiceExpired)
                                        <span class="font-bold text-xs text-rose-600 block">Kedaluwarsa</span>
                                    @elseif($periodEnd && $daysRemaining <= 3)
                                        <span class="font-bold text-xs text-amber-600 block">Sisa {{ $daysRemaining }} Hari</span>
                                    @elseif($periodEnd)
                                        <span class="font-medium text-xs text-emerald-600 block">Sisa {{ $daysRemaining }} Hari</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Footer Card (Tanggal & Tombol Aksi) -->
                        <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="text-[11px] text-zinc-500 space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                    <span>Terbit: {{ $inv->created_at?->translatedFormat('d M Y, H:i') }}</span>
                                </div>
                                @if($inv->status === 'pending' && $dueAt)
                                    <div class="{{ $dueAt->isPast() ? 'text-rose-600 font-bold' : 'text-zinc-500' }} flex items-center gap-1 pl-5">
                                        <span>Tenggat: {{ $dueAt->translatedFormat('d M Y') }}</span>
                                    </div>
                                @elseif($inv->paid_at)
                                    <div class="text-emerald-700 font-medium pl-5">
                                        Lunas: {{ $inv->paid_at->translatedFormat('d M Y') }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                                @if($inv->status === 'paid' && $waLink)
                                    <a
                                        href="{{ $waLink }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="w-8 h-8 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                        title="Kirim Kuitansi &amp; Faktur Lunas ke WhatsApp Owner ({{ $inv->tenant?->owner?->phone ?? '-' }})"
                                    >
                                        <x-ui.icon name="whatsapp" class="w-4 h-4 fill-current" />
                                    </a>
                                @endif

                                @if($inv->status === 'pending' || $inv->status === 'unpaid')
                                    <button
                                        type="button"
                                        data-cancel-url="{{ route('platform.subscriptions.cancel', $inv->id) }}"
                                        data-number="{{ $inv->number }}"
                                        data-tenant-name="{{ $inv->tenant?->name ?? 'Tenant' }}"
                                        data-amount="Rp{{ number_format($inv->amount, 0, ',', '.') }}"
                                        onclick="openRejectInvoiceModal(this.dataset.cancelUrl, this.dataset.number, this.dataset.tenantName, this.dataset.amount)"
                                        class="w-8 h-8 rounded-xl bg-rose-600 hover:bg-rose-500 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                        title="Batalkan Tagihan"
                                    >
                                        <x-ui.icon name="x" class="w-4 h-4 stroke-[3]" />
                                    </button>
                                @endif

                                <a
                                    href="{{ route('platform.subscriptions.show', $inv->id) }}"
                                    class="w-8 h-8 rounded-xl bg-zinc-800 hover:bg-zinc-700 active:scale-95 text-white flex items-center justify-center shadow-xs transition cursor-pointer shrink-0"
                                    title="Buka Dokumen Invoice"
                                >
                                    <x-ui.icon name="file-text" class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($invoices->hasPages())
                <div class="p-4 border-t border-zinc-100 bg-zinc-50/50">
                    {{ $invoices->links() }}
                </div>
            @endif
        @endif
    </div>

    <style>
        #modal-reject-invoice,
        #modal-reject-invoice:focus,
        #modal-reject-invoice:focus-visible,
        #modal-proof-preview,
        #modal-proof-preview:focus,
        #modal-proof-preview:focus-visible {
            outline: none !important;
            border: 1px solid rgb(228 228 231) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        }
    </style>

    <!-- Modal Dialog Preview Bukti Pembayaran (Dengan Zoom In / Out & Pan) -->
    <dialog id="modal-proof-preview" class="rounded-2xl p-0 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-3xl mx-auto my-auto border border-zinc-200 bg-white outline-none focus:outline-none focus-visible:outline-none ring-0">
        <div class="p-4 sm:p-5 border-b border-zinc-100 flex items-center justify-between">
            <div>
                <h3 class="font-black text-sm sm:text-base text-zinc-900">Bukti Pembayaran Langganan</h3>
                <p class="text-xs text-zinc-500 mt-0.5" id="proof-modal-meta">-</p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('modal-proof-preview').close()"
                class="p-1.5 rounded-xl text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer"
            >
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        <div class="p-4 sm:p-5 space-y-3">
            <!-- Toolbar Kontrol Zoom -->
            <div class="flex items-center justify-between text-xs text-zinc-500">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-zinc-400 hidden sm:inline">Zoom:</span>
                    <div class="inline-flex items-center gap-1 bg-zinc-100 p-1 rounded-xl border border-zinc-200">
                        <button
                            type="button"
                            onclick="zoomProof(-0.25)"
                            class="w-7 h-7 rounded-lg bg-white hover:bg-zinc-200 active:scale-95 text-zinc-700 flex items-center justify-center transition shadow-2xs cursor-pointer"
                            title="Perkecil (Zoom Out)"
                        >
                            <x-ui.icon name="minus" class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            onclick="resetProofZoom()"
                            id="proof-zoom-label"
                            class="px-2 text-[11px] font-mono font-black text-zinc-800 min-w-[48px] text-center select-none hover:text-amber-600 transition cursor-pointer"
                            title="Klik untuk reset zoom ke 100%"
                        >
                            100%
                        </button>
                        <button
                            type="button"
                            onclick="zoomProof(0.25)"
                            class="w-7 h-7 rounded-lg bg-white hover:bg-zinc-200 active:scale-95 text-zinc-700 flex items-center justify-center transition shadow-2xs cursor-pointer"
                            title="Perbesar (Zoom In)"
                        >
                            <x-ui.icon name="plus" class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            onclick="resetProofZoom()"
                            class="px-2 py-1 rounded-lg bg-white hover:bg-zinc-200 text-zinc-700 text-[10px] font-bold transition shadow-2xs cursor-pointer ml-0.5"
                            title="Reset Ukuran (100%)"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-zinc-400 italic hidden md:inline">Scroll mouse / drag gambar untuk geser</span>
                    <a
                        id="proof-modal-external"
                        href="#"
                        target="_blank"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold transition border border-zinc-200 cursor-pointer"
                        title="Buka gambar asli di tab baru"
                    >
                        <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                        <span>Tab Baru</span>
                    </a>
                </div>
            </div>

            <!-- Viewport Gambar Bukti (Dark Contrast + Pan & Zoom) -->
            <div
                id="proof-modal-viewport"
                class="bg-zinc-950 rounded-2xl h-[48vh] sm:h-[54vh] flex items-center justify-center overflow-hidden relative select-none border border-zinc-800 cursor-default"
                title="Double-click untuk toggle zoom 2x / 1x"
            >
                <img
                    src=""
                    alt="Bukti Transfer"
                    id="proof-modal-img"
                    class="max-h-full max-w-full rounded-lg object-contain pointer-events-none transition-transform duration-75"
                    style="transform-origin: center center;"
                />
            </div>

            <!-- Info Detail Transaksi -->
            <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-zinc-500">Metode Bayar:</span>
                    <strong class="text-zinc-900 capitalize" id="proof-modal-method">-</strong>
                </div>
                <div class="flex justify-between" id="proof-modal-bank-row">
                    <span class="text-zinc-500">Rekening Tujuan:</span>
                    <strong class="text-zinc-900" id="proof-modal-bank">-</strong>
                </div>
                <div class="pt-1 border-t border-zinc-200/60" id="proof-modal-notes-row">
                    <span class="text-zinc-500 block">Catatan Pengirim:</span>
                    <p class="text-zinc-800 font-medium italic mt-0.5" id="proof-modal-notes">-</p>
                </div>
            </div>
        </div>

        <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex justify-end rounded-b-2xl">
            <button
                type="button"
                onclick="document.getElementById('modal-proof-preview').close()"
                class="px-5 py-2 rounded-xl bg-zinc-900 text-white font-bold text-xs hover:bg-zinc-800 transition cursor-pointer"
            >
                Tutup
            </button>
        </div>
    </dialog>

    <!-- Modal Dialog Tolak / Batalkan Tagihan Oleh Superadmin -->
    <dialog id="modal-reject-invoice" class="rounded-2xl p-0 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-md mx-auto my-auto border border-zinc-200 bg-white outline-none focus:outline-none focus-visible:outline-none ring-0">
        <form id="form-reject-invoice" method="POST" action="">
            @csrf
            @method('PATCH')

            <div class="p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 shadow-2xs">
                        <x-ui.icon name="x-circle" class="w-6 h-6 text-rose-600" />
                    </div>
                    <div>
                        <h3 class="font-black text-base text-zinc-900">Tolak / Batalkan Tagihan</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Alasan penolakan wajib diisi untuk dibaca oleh Owner</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 text-xs space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-zinc-500 font-medium">No. Invoice:</span>
                        <strong class="font-mono text-zinc-900" id="modal-reject-inv-number">-</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500 font-medium">Barbershop:</span>
                        <strong class="text-zinc-900" id="modal-reject-inv-tenant">-</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500 font-medium">Total Nominal:</span>
                        <strong class="text-zinc-900 font-bold" id="modal-reject-inv-amount">-</strong>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="reject-cancellation-reason" class="block text-xs font-bold text-zinc-800">
                        Alasan Penolakan Tagihan <span class="text-rose-500 font-bold ml-0.5">*</span>
                    </label>
                    <textarea
                        name="cancellation_reason"
                        id="reject-cancellation-reason"
                        rows="3"
                        required
                        placeholder="Contoh: Bukti transfer buram/tidak terbaca, nominal kurang, atau dana belum masuk..."
                        class="w-full text-xs rounded-xl border border-zinc-200 bg-white px-3 py-2.5 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/25 transition"
                    ></textarea>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <button type="button" onclick="setSuperadminRejectReason('Bukti transfer tidak valid atau foto buram')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                            Bukti buram / tidak valid
                        </button>
                        <button type="button" onclick="setSuperadminRejectReason('Nominal transfer tidak sesuai dengan jumlah tagihan')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                            Nominal tidak sesuai
                        </button>
                        <button type="button" onclick="setSuperadminRejectReason('Mutasi rekening bank tidak ditemukan / belum masuk')" class="text-[10px] font-medium px-2 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer">
                            Mutasi belum masuk
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2.5 rounded-b-2xl">
                <button
                    type="button"
                    onclick="document.getElementById('modal-reject-invoice').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition cursor-pointer inline-flex items-center gap-1.5"
                >
                    <x-ui.icon name="x" class="w-4 h-4 stroke-[3]" />
                    <span>Tolak &amp; Batalkan Tagihan</span>
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openRejectInvoiceModal(actionUrl, invoiceNumber, tenantName, amount) {
            const modal = document.getElementById('modal-reject-invoice');
            const form = document.getElementById('form-reject-invoice');
            form.action = actionUrl;

            document.getElementById('modal-reject-inv-number').textContent = invoiceNumber;
            document.getElementById('modal-reject-inv-tenant').textContent = tenantName;
            document.getElementById('modal-reject-inv-amount').textContent = amount;
            document.getElementById('reject-cancellation-reason').value = '';

            modal.showModal();
        }

        function setSuperadminRejectReason(text) {
            const textarea = document.getElementById('reject-cancellation-reason');
            if (textarea) {
                textarea.value = text;
                textarea.focus();
            }
        }

        // ==========================================
        // Fitur Zoom In / Out & Pan Bukti Pembayaran
        // ==========================================
        let proofZoom = 1;
        let proofPanX = 0;
        let proofPanY = 0;
        let isProofPanning = false;
        let proofStartX = 0;
        let proofStartY = 0;

        function updateProofTransform() {
            const img = document.getElementById('proof-modal-img');
            const label = document.getElementById('proof-zoom-label');
            const viewport = document.getElementById('proof-modal-viewport');
            if (!img) return;

            img.style.transform = `translate(${proofPanX}px, ${proofPanY}px) scale(${proofZoom})`;
            if (label) label.textContent = `${Math.round(proofZoom * 100)}%`;

            if (viewport) {
                if (proofZoom > 1.05) {
                    viewport.style.cursor = isProofPanning ? 'grabbing' : 'grab';
                } else {
                    viewport.style.cursor = 'default';
                }
            }
        }

        function zoomProof(delta) {
            const newZoom = Math.min(Math.max(proofZoom + delta, 0.5), 4.0);
            proofZoom = Math.round(newZoom * 100) / 100;
            if (proofZoom <= 1.05) {
                proofPanX = 0;
                proofPanY = 0;
            }
            updateProofTransform();
        }

        function resetProofZoom() {
            proofZoom = 1;
            proofPanX = 0;
            proofPanY = 0;
            updateProofTransform();
        }

        function openProofModal(imageUrl, invoiceNumber, tenantName, notes, method, bankName) {
            const modal = document.getElementById('modal-proof-preview');
            document.getElementById('proof-modal-img').src = imageUrl;
            document.getElementById('proof-modal-meta').textContent = `${invoiceNumber} • ${tenantName}`;
            document.getElementById('proof-modal-method').textContent = method === 'transfer' ? 'Transfer Bank' : (method === 'cash' ? 'Tunai (Cash)' : method);
            
            const extLink = document.getElementById('proof-modal-external');
            if (extLink) extLink.href = imageUrl;

            const bankRow = document.getElementById('proof-modal-bank-row');
            if (method === 'transfer' && bankName && bankName !== '-') {
                bankRow.classList.remove('hidden');
                document.getElementById('proof-modal-bank').textContent = bankName;
            } else {
                bankRow.classList.add('hidden');
            }

            const notesRow = document.getElementById('proof-modal-notes-row');
            if (notes && notes.trim() !== '') {
                notesRow.classList.remove('hidden');
                document.getElementById('proof-modal-notes').textContent = notes;
            } else {
                notesRow.classList.add('hidden');
            }

            resetProofZoom();
            modal.showModal();
        }

        // Inisialisasi event listener interaktif zoom & pan
        document.addEventListener('DOMContentLoaded', function () {
            const viewport = document.getElementById('proof-modal-viewport');
            const proofModal = document.getElementById('modal-proof-preview');

            if (proofModal) {
                proofModal.addEventListener('close', function() {
                    resetProofZoom();
                });
            }

            if (viewport) {
                viewport.addEventListener('mousedown', function (e) {
                    if (proofZoom > 1.05) {
                        isProofPanning = true;
                        proofStartX = e.clientX - proofPanX;
                        proofStartY = e.clientY - proofPanY;
                        updateProofTransform();
                    }
                });

                window.addEventListener('mousemove', function (e) {
                    if (!isProofPanning) return;
                    proofPanX = e.clientX - proofStartX;
                    proofPanY = e.clientY - proofStartY;
                    updateProofTransform();
                });

                window.addEventListener('mouseup', function () {
                    if (isProofPanning) {
                        isProofPanning = false;
                        updateProofTransform();
                    }
                });

                viewport.addEventListener('wheel', function (e) {
                    e.preventDefault();
                    const delta = e.deltaY < 0 ? 0.25 : -0.25;
                    zoomProof(delta);
                }, { passive: false });

                // Toggle zoom 2x / 1x via double-click
                viewport.addEventListener('dblclick', function () {
                    if (proofZoom > 1.05) {
                        resetProofZoom();
                    } else {
                        proofZoom = 2.0;
                        proofPanX = 0;
                        proofPanY = 0;
                        updateProofTransform();
                    }
                });
            }
        });
    </script>
</x-layouts.app>
