<x-layouts.app title="Langganan & Paket Fitur" subtitle="Status paket langganan aktif, kuota cabang & staf, serta upgrade paket fitur barbershop" role="owner">
    @php
        $currentPlanCode = $tenantSub?->plan?->code ?? 'starter';
        $branchCount = $tenant?->branches()->count() ?? 0;
        $staffCount = $tenant?->staffMemberships()->count() ?? 0;
        $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);
        $isLocked = $currentTenantCtx->isSubscriptionLocked();
        $pendingInvoice = $currentTenantCtx->latestPendingInvoice();

        $supportPhone = \App\Support\Platform\PlatformConfig::get('support_phone', '081100000001');
        $supportEmail = \App\Support\Platform\PlatformConfig::get('support_email', 'support@barberos.id');
        $platformName = \App\Support\Platform\PlatformConfig::get('platform_name', 'BarberOS Platform');

        $cleanWaNumber = preg_replace('/[^0-9]/', '', (string) $supportPhone);
        if (str_starts_with($cleanWaNumber, '0')) {
            $cleanWaNumber = '62' . substr($cleanWaNumber, 1);
        }
        $ownerUser = auth()->user();
        $ownerName = $ownerUser?->name ?? 'Owner';
        $tenantName = $tenant?->name ?? 'Barbershop';
        $waMessage = "Halo Superadmin {$platformName},\n\nSaya {$ownerName} dari {$tenantName}.\nSaya membutuhkan bantuan / ingin menyampaikan keluhan terkait langganan dan paket fitur.";
        $waLink = "https://wa.me/{$cleanWaNumber}?text=" . rawurlencode($waMessage);
        $emailSubject = rawurlencode("[Bantuan & Komplain Langganan] {$tenantName}");
        $emailBody = rawurlencode("Halo Tim Superadmin {$platformName},\n\nNama Barbershop: {$tenantName}\nNama Pemilik: {$ownerName}\nEmail: " . ($ownerUser?->email ?? '') . "\n\nKendala / Komplain:\n- \n\nMohon bantuannya, terima kasih.");
        $emailLink = "mailto:{$supportEmail}?subject={$emailSubject}&body={$emailBody}";
        $telLink = "tel:" . preg_replace('/[^0-9+]/', '', (string) $supportPhone);
    @endphp

    <!-- Banner Peringatan Jika Langganan Habis / Terkunci -->
    @if($isLocked)
        <div class="mb-6 p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-rose-600 via-rose-700 to-amber-600 text-white shadow-xl space-y-3 border border-rose-500">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center shrink-0 shadow-inner">
                    <x-ui.icon name="lock" class="w-7 h-7 text-white" />
                </div>
                <div class="space-y-1">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/20 text-[10px] font-black uppercase tracking-wider text-rose-100">
                        Sistem Terkunci
                    </span>
                    <h2 class="text-lg sm:text-xl font-black text-white tracking-tight">Masa Aktif Langganan Telah Berakhir</h2>
                    <p class="text-xs sm:text-sm text-rose-100 leading-relaxed max-w-2xl">
                        Seluruh fitur operasional barbershop (kasir POS, antrean pelanggan, absensi, dan jadwal) saat ini dikunci sementara. Silakan pilih paket di bawah untuk menerbitkan tagihan perpanjangan. Setelah pembayaran ditransfer dan dikonfirmasi oleh Superadmin, sistem akan aktif kembali.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Banner Tagihan Pending Menunggu Konfirmasi Superadmin -->
    @if($pendingInvoice)
        <div id="pending-invoice-banner" class="mb-6 p-4 sm:p-5 rounded-2xl bg-amber-50/80 border border-amber-300 text-amber-950 shadow-2xs space-y-3.5 scroll-mt-20">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
                <!-- Info Utama: Ikon & Detail Tagihan -->
                <div class="flex items-start gap-3 min-w-0">
                    <span class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-800 flex items-center justify-center shrink-0 mt-0.5 animate-pulse">
                        <x-ui.icon name="alert-triangle" class="w-5 h-5 text-amber-700" />
                    </span>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h4 class="font-black text-sm text-zinc-900 leading-snug">
                                Tagihan Langganan Menunggu Pembayaran / Konfirmasi
                            </h4>
                            <span class="text-[11px] font-black uppercase tracking-wider {{ $pendingInvoice->status === 'pending' ? 'text-amber-700' : 'text-rose-600' }} shrink-0">
                                {{ $pendingInvoice->status === 'pending' ? 'Pending Verifikasi' : 'Menunggu Bayar' }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-600 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span>No. Tagihan: <strong class="font-mono text-zinc-900 font-bold">{{ $pendingInvoice->number }}</strong></span>
                            <span class="text-zinc-300 hidden sm:inline">&bull;</span>
                            <span>Total: <strong class="text-emerald-700 font-black text-sm">Rp{{ number_format($pendingInvoice->amount, 0, ',', '.') }}</strong></span>
                        </p>
                    </div>
                </div>

                <!-- Tombol Aksi: Full Width di Mobile, Auto di Tablet/Desktop -->
                <div class="shrink-0 w-full sm:w-auto">
                    <a
                        href="{{ route('owner.subscription.invoice', $pendingInvoice->id) }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.98] text-white text-xs font-black shadow-sm transition"
                    >
                        <x-ui.icon name="credit-card" class="w-4 h-4 text-amber-400" />
                        <span>Lihat Tagihan &amp; Bayar</span>
                    </a>
                </div>
            </div>

            <!-- Petunjuk Pembayaran -->
            <div class="p-3.5 rounded-xl bg-white/90 border border-amber-200/80 text-xs text-zinc-700 space-y-1">
                <p class="font-extrabold text-zinc-900 flex items-center gap-1.5">
                    <x-ui.icon name="info" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                    <span>Petunjuk Pembayaran:</span>
                </p>
                <p class="leading-relaxed text-zinc-600 pl-5">
                    Silakan buka halaman rincian tagihan untuk memilih rekening bank resmi platform, melakukan transfer, dan mengunggah struk bukti pembayaran agar akun Anda segera diproses Superadmin.
                </p>
            </div>
        </div>
    @endif

    <!-- Card Ringkasan Status Langganan Saat Ini -->
    @php
        $subEndsAt = $tenantSub?->ends_at ?? $tenant?->trial_ends_at;
        $subDiffDays = $subEndsAt ? (int) ceil(now()->floatDiffInDays($subEndsAt, false)) : null;
        $subIsPastDue = $tenantSub?->status === \App\Enums\SubscriptionStatus::PastDue;
        $subIsNearExpiry = $subDiffDays !== null && $subDiffDays <= 7 && $subDiffDays >= 0;
        $subIsExpired = $subEndsAt ? $subEndsAt->isPast() : false;
        $subIsTenggang = $subIsPastDue || $subIsNearExpiry || $subIsExpired;
        $canRenew = $isLocked || $subIsTenggang || $subDiffDays === null || $subDiffDays <= 7;
    @endphp
    <x-ui.card class="mb-6 relative overflow-hidden border-amber-500/20 bg-gradient-to-br from-white via-zinc-50/50 to-amber-50/20">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0 shadow-inner">
                    <x-ui.icon name="sparkles" class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base sm:text-lg font-black text-zinc-900 truncate">
                            {{ $tenantSub?->plan?->name ? 'Paket ' . $tenantSub->plan->name : 'Paket Starter' }}
                        </h2>
                        @if($tenantSub && in_array($tenantSub->status->value, ['active', 'trialing', 'past_due'], true))
                            @if($subIsTenggang)
                                <span class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase text-amber-700">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Tenggang (Sisa {{ max(0, $subDiffDays ?? 0) }} Hari)</span>
                                </span>
                            @elseif($tenantSub->status->value === 'trialing')
                                <span class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase text-amber-700">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Masa Trial</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase text-emerald-700">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Langganan Aktif</span>
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase text-rose-700">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>Tidak Aktif</span>
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5 line-clamp-2">
                        {{ $tenantSub?->plan?->description ?? 'Paket dasar untuk barbershop 1 cabang.' }}
                    </p>
                </div>
            </div>

            <div class="shrink-0 flex flex-col sm:items-end justify-center gap-2.5 pt-2 sm:pt-0 border-t border-zinc-100 sm:border-0">
                <div class="sm:text-right">
                    <span class="text-[11px] font-medium text-zinc-400 block">Biaya Berlangganan</span>
                    <span class="text-base sm:text-lg font-black text-amber-600 num">
                        Rp{{ number_format($tenantSub?->price ?? ($tenantSub?->plan?->price_monthly ?? 99000), 0, ',', '.') }}
                        <span class="text-xs font-semibold text-zinc-400">/bulan</span>
                    </span>
                </div>

                @if($pendingInvoice)
                    <a
                        href="{{ route('owner.subscription.invoice', $pendingInvoice->id) }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-black border border-amber-300 transition"
                    >
                        <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 animate-pulse" />
                        <span>Lihat Tagihan Pending</span>
                    </a>
                @elseif($tenantSub?->plan && $canRenew)
                    <button
                        type="button"
                        onclick="openPlanPopup('{{ $tenantSub->plan_id }}', '{{ $tenantSub->plan->name }}', 'Rp{{ number_format($tenantSub->plan->price_monthly, 0, ',', '.') }}', true, {{ $subDiffDays ?? 0 }}, false, '{{ $tenantSub->plan->code }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 font-black text-xs shadow-sm hover:shadow transition cursor-pointer"
                    >
                        <x-ui.icon name="refresh-cw" class="w-3.5 h-3.5 text-zinc-950" />
                        <span>Perpanjang Langganan</span>
                    </button>
                @elseif($tenantSub?->plan)
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-100 text-zinc-400 text-xs font-bold border border-zinc-200 cursor-not-allowed" title="Perpanjangan paket aktif dibuka saat sisa masa aktif 7 hari atau kurang">
                        <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400" />
                        <span>Perpanjangan Mulai H-7 (Sisa {{ $subDiffDays }} Hari)</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- 3 Metrik Kuota Outlet & Periode -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 subscription-metrics-grid">
            <div class="p-3 sm:p-3.5 rounded-xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 flex items-center gap-1.5 mb-1 truncate">
                    <x-ui.icon name="store" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                    <span>Kuota Cabang</span>
                </span>
                <div class="text-sm sm:text-base font-extrabold text-zinc-900 truncate num">
                    {{ $branchCount }} / {{ $tenantSub?->plan?->max_branches ?? 1 }} Cabang
                </div>
                <div class="w-full bg-zinc-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    @php
                        $maxBr = $tenantSub?->plan?->max_branches ?? 1;
                        $pctBr = min(100, (int) round(($branchCount / max(1, $maxBr)) * 100));
                    @endphp
                    <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $pctBr }}%"></div>
                </div>
            </div>

            <div class="p-3 sm:p-3.5 rounded-xl bg-white border border-zinc-200/80 shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 flex items-center gap-1.5 mb-1 truncate">
                    <x-ui.icon name="users" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                    <span>Kuota Staf &amp; Barber</span>
                </span>
                <div class="text-sm sm:text-base font-extrabold text-zinc-900 truncate num">
                    {{ $staffCount }} / {{ $tenantSub?->plan?->max_staff ? $tenantSub->plan->max_staff . ' Staf' : 'Unlimited' }}
                </div>
                <div class="w-full bg-zinc-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    @php
                        $maxSt = $tenantSub?->plan?->max_staff ?? 5;
                        $pctSt = $maxSt ? min(100, (int) round(($staffCount / max(1, $maxSt)) * 100)) : 20;
                    @endphp
                    <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $pctSt }}%"></div>
                </div>
            </div>

            <div class="p-3 sm:p-3.5 rounded-xl bg-white border {{ $subIsTenggang ? 'border-amber-300 bg-amber-50/20' : 'border-zinc-200/80' }} shadow-2xs">
                <span class="text-[11px] font-bold text-zinc-500 flex items-center gap-1.5 mb-1 truncate">
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                    <span>Periode Langganan</span>
                </span>
                <div class="text-sm sm:text-base font-extrabold text-zinc-900 truncate">
                    {{ $tenantSub?->ends_at ? $tenantSub->ends_at->format('d M Y') : 'Auto-renew' }}
                </div>
                <div class="w-full flex items-center justify-between text-[10px] {{ $subIsTenggang ? 'text-amber-600 font-bold' : 'text-emerald-600 font-bold' }} mt-2 pt-0.5">
                    <span class="inline-flex items-center gap-1 truncate">
                        <span class="w-1.5 h-1.5 rounded-full {{ $subIsTenggang ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }} shrink-0"></span>
                        <span class="truncate">{{ $subIsTenggang ? ($subDiffDays !== null && $subDiffDays >= 0 ? 'Sisa ' . $subDiffDays . ' hari' : 'Tenggang') : 'Auto-renew aktif' }}</span>
                    </span>
                    <span class="text-zinc-400 font-normal shrink-0">Bulanan</span>
                </div>
            </div>
        </div>
    </x-ui.card>

    <!-- Section Heading: Pilih Paket & Upgrade -->
    <div class="mb-4">
        <h3 class="font-extrabold text-sm sm:text-base text-zinc-900 flex items-center gap-2">
            <x-ui.icon name="zap" class="w-4 h-4 text-amber-500" />
            <span>Pilihan Paket Langganan &amp; Upgrade Fitur</span>
        </h3>
        <p class="text-xs text-zinc-500">Pilih paket yang sesuai dengan skala bisnis barbershop Anda untuk membuka fitur eksklusif.</p>
    </div>

    <!-- Pricing Grid: Starter, Pro, Business (Responsif: 1 kolom di mobile & tablet, 3 kolom di desktop xl) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-5 mb-6 subscription-plans-grid" data-tablet-flip>
        @foreach($plans as $plan)
            @php
                $isCurrent = $tenantSub && $tenantSub->plan_id === $plan->id;
                $isPro = $plan->code === 'pro';
            @endphp

            <div class="relative rounded-2xl flex flex-col justify-between p-4 sm:p-5 xl:p-5 transition-all duration-300 {{ $isCurrent ? 'bg-amber-50/40 border-2 border-amber-500 shadow-md ring-2 ring-amber-500/20' : ($isPro ? 'bg-white border-2 border-zinc-200/90 hover:border-amber-400 shadow-xs' : 'bg-white border border-zinc-200/90 hover:border-zinc-300 shadow-2xs') }}">
                @if($isCurrent)
                    <div class="absolute -top-3 left-6 sm:left-8 xl:left-1/2 xl:-translate-x-1/2 px-3 py-0.5 rounded-full bg-amber-500 text-zinc-950 font-black text-[10px] uppercase tracking-wider shadow-sm">
                        Paket Aktif Anda
                    </div>
                @elseif($isPro)
                    <div class="absolute -top-3 left-6 sm:left-8 xl:left-1/2 xl:-translate-x-1/2 px-2.5 py-0.5 rounded-full bg-zinc-800 text-zinc-200 font-bold text-[9px] uppercase tracking-wider shadow-sm border border-zinc-700">
                        Paling Populer
                    </div>
                @endif

                <div>
                    <!-- Header & Harga Paket (Responsive: Row di Tablet, Col di Mobile & Desktop xl) -->
                    <div class="flex flex-col sm:flex-row xl:flex-col sm:items-start xl:items-stretch justify-between gap-3 pb-4 mb-4 border-b border-zinc-100">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-black text-base text-zinc-900">{{ $plan->name }}</h4>
                                <x-ui.plan-badge :code="$plan->code" />
                                <span class="text-xs font-bold text-zinc-400">&bull; Bulanan</span>
                            </div>
                            <p class="text-xs text-zinc-500 leading-relaxed max-w-xl">
                                {{ $plan->description }}
                            </p>
                        </div>

                        <!-- Harga -->
                        <div class="shrink-0 sm:text-right xl:text-left self-start sm:self-auto xl:self-start">
                            <span class="text-2xl sm:text-3xl font-black text-zinc-900 num">
                                Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}
                            </span>
                            <span class="text-xs font-semibold text-zinc-400">/bln</span>
                        </div>
                    </div>

                    <!-- Fitur Utama (Responsive Grid: 2 kolom di tablet/lebar, 1 kolom di desktop xl) -->
                    <ul class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-x-6 gap-y-2.5 text-xs text-zinc-700 mb-6">
                        <li class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Maksimal <strong>{{ $plan->max_branches }} Cabang</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Maksimal <strong>{{ $plan->max_staff ? $plan->max_staff . ' Karyawan' : 'Unlimited Karyawan' }}</strong></span>
                        </li>
                        <!-- 1. Sistem Penggajian (Payroll) -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- 2. Komisi Barber Otomatis -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- 3. Dokumen Cetak Slip Gaji Resmi -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- Grafik Analitik Fitur Pro (Efektivitas & Tren Omzet) -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- Notifikasi WhatsApp Pelanggan -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- Layar TV Display Antrean Live -->
                        <li class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <x-ui.icon name="check" class="w-2.5 h-2.5" />
                            </span>
                            <span>Layar TV Display Antrean Live</span>
                        </li>

                        <!-- Custom Logo & Warna Tema Barbershop (Pro & Business) -->
                        <li class="flex items-center gap-2">
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
                        </li>

                        <!-- White-label & Subdomain Khusus (Eksklusif Business) -->
                        <li class="flex items-center gap-2">
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
                        </li>
                    </ul>
                </div>

                <!-- Tombol Aksi -->
                <div class="pt-3 border-t border-zinc-100">
                    @if($isCurrent)
                        @if($pendingInvoice)
                            <a
                                href="{{ route('owner.subscription.invoice', $pendingInvoice->id) }}"
                                class="w-full py-2.5 px-3 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-black text-center flex items-center justify-center gap-1.5 border border-amber-300 transition"
                            >
                                <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600 animate-pulse" />
                                <span>Lihat Tagihan Pending</span>
                            </a>
                        @elseif($canRenew)
                            <button
                                type="button"
                                onclick="openPlanPopup('{{ $plan->id }}', '{{ $plan->name }}', 'Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}', true, {{ $subDiffDays ?? 0 }}, false, '{{ $plan->code }}')"
                                class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 font-black text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-sm"
                            >
                                @if($plan->code === 'business')
                                    <x-ui.icon name="crown" class="w-4 h-4 text-zinc-950" />
                                @elseif($plan->code === 'pro')
                                    <div class="inline-flex items-center gap-0.5 text-zinc-950 shrink-0">
                                        <x-ui.icon name="crystal" class="w-3 h-3.5" />
                                        <x-ui.icon name="crystal" class="w-3 h-3.5" />
                                    </div>
                                @else
                                    <x-ui.icon name="crystal" class="w-3.5 h-4 text-zinc-950" />
                                @endif
                                <span>Perpanjang Paket Ini (+30 Hari)</span>
                            </button>
                        @else
                            <button
                                type="button"
                                disabled
                                class="w-full py-2.5 rounded-xl bg-zinc-100 text-zinc-400 font-bold text-xs flex items-center justify-center gap-1.5 cursor-not-allowed border border-zinc-200"
                                title="Perpanjangan hanya dapat dilakukan saat sisa masa aktif 7 hari atau kurang"
                            >
                                <x-ui.icon name="lock" class="w-3.5 h-3.5 text-zinc-400" />
                                <span>Perpanjangan Mulai H-7 (Sisa {{ $subDiffDays }} Hari)</span>
                            </button>
                        @endif
                    @else
                        @php
                            $currentPrice = $tenantSub?->price ?? 0;
                            $isUpgrade = $plan->price_monthly > $currentPrice;
                            $isDowngrade = $tenantSub && $plan->price_monthly < $currentPrice;
                            $actionLabel = $isUpgrade ? 'Upgrade ke ' . $plan->name : 'Pilih ' . $plan->name;
                        @endphp
                        <button
                            type="button"
                            onclick="openPlanPopup('{{ $plan->id }}', '{{ $plan->name }}', 'Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}', false, {{ $subDiffDays ?? 0 }}, {{ $isDowngrade ? 'true' : 'false' }}, '{{ $plan->code }}')"
                            class="w-full py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-extrabold text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-xs"
                        >
                            @if($plan->code === 'business')
                                <x-ui.icon name="crown" class="w-4 h-4 text-white" />
                            @elseif($plan->code === 'pro')
                                <div class="inline-flex items-center gap-0.5 text-white shrink-0">
                                    <x-ui.icon name="crystal" class="w-3 h-3.5" />
                                    <x-ui.icon name="crystal" class="w-3 h-3.5" />
                                </div>
                            @else
                                <x-ui.icon name="crystal" class="w-3.5 h-4 text-white" />
                            @endif
                            <span>{{ $actionLabel }}</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Riwayat Tagihan & Invoice Langganan -->
    <x-ui.card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <h3 class="font-bold text-xs sm:text-sm flex items-center gap-2">
                    <x-ui.icon name="receipt" class="w-4 h-4 text-amber-500" />
                    <span>Riwayat Tagihan &amp; Invoice Langganan</span>
                </h3>
                <span class="text-xs text-zinc-400 font-medium">{{ $invoices->count() }} invoice</span>
            </div>
        </x-slot:header>

        @if($invoices->isEmpty())
            <x-ui.empty-state title="Belum ada riwayat tagihan invoice langganan" />
        @else
            <!-- Tampilan Kartu Mobile (< sm) -->
            <div class="sm:hidden divide-y divide-zinc-100">
                @foreach($invoices as $inv)
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('owner.subscription.invoice', $inv->id) }}" class="font-mono font-bold text-amber-600 hover:text-amber-700 hover:underline text-xs">
                                {{ $inv->number }}
                            </a>
                            @if($inv->status === 'paid')
                                <span class="font-bold text-xs text-emerald-600">Lunas</span>
                            @elseif($inv->status === 'pending')
                                <span class="font-bold text-xs text-amber-600">Menunggu Konfirmasi</span>
                            @elseif($inv->status === 'cancelled')
                                <span class="font-bold text-xs text-zinc-400">Dibatalkan</span>
                            @else
                                <span class="font-bold text-xs text-rose-600">Menunggu Bayar</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 pt-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-bold uppercase {{ $inv->type === 'upgrade' ? 'text-amber-700' : ($inv->type === 'downgrade' ? 'text-purple-700' : 'text-emerald-700') }}">
                                    {{ $inv->type ?? 'renewal' }}
                                </span>
                                <span>{{ $inv->paid_at ? $inv->paid_at->translatedFormat('d M Y') : ($inv->due_at ? $inv->due_at->translatedFormat('d M Y') : '-') }}</span>
                            </div>
                            <span class="font-extrabold text-zinc-900 num">Rp{{ number_format($inv->amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="pt-1">
                            <a href="{{ route('owner.subscription.invoice', $inv->id) }}" class="w-full py-1.5 px-3 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-center font-bold text-xs block transition">
                                {{ $inv->status === 'paid' ? 'Lihat Bukti & Rincian' : 'Buka Tagihan & Bayar' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Tampilan Tabel Desktop & Tablet (>= sm) -->
            <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200/80">
                <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-extrabold uppercase tracking-wider text-zinc-500 whitespace-nowrap">
                            <th class="py-2.5 px-3 sm:px-4">No. Invoice</th>
                            <th class="py-2.5 px-3 sm:px-4">Jenis Transaksi</th>
                            <th class="py-2.5 px-3 sm:px-4">Tanggal Tagihan</th>
                            <th class="py-2.5 px-3 sm:px-4">Nominal</th>
                            <th class="py-2.5 px-3 sm:px-4">Status</th>
                            <th class="py-2.5 px-3 sm:px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($invoices as $inv)
                            <tr class="hover:bg-zinc-50/60 transition">
                                <td class="py-3 px-3 sm:px-4 font-bold text-zinc-900 font-mono whitespace-nowrap">
                                    <a href="{{ route('owner.subscription.invoice', $inv->id) }}" class="text-zinc-900 hover:text-amber-600 transition">
                                        {{ $inv->number }}
                                    </a>
                                </td>
                                <td class="py-3 px-3 sm:px-4 whitespace-nowrap">
                                    <span class="text-[11px] font-bold uppercase
                                        @if($inv->type === 'upgrade') text-amber-700
                                        @elseif($inv->type === 'downgrade') text-purple-700
                                        @else text-emerald-700 @endif
                                    ">
                                        {{ $inv->type ?? 'renewal' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-zinc-500 whitespace-nowrap">
                                    {{ $inv->paid_at ? $inv->paid_at->translatedFormat('d M Y, H:i') : ($inv->due_at ? $inv->due_at->translatedFormat('d M Y') : '-') }}
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-extrabold text-zinc-900 whitespace-nowrap num">
                                    Rp{{ number_format($inv->amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 sm:px-4 whitespace-nowrap">
                                    @if($inv->status === 'paid')
                                        <span class="font-bold text-xs text-emerald-600">Lunas</span>
                                    @elseif($inv->status === 'pending')
                                        <span class="font-bold text-xs text-amber-600">Menunggu Konfirmasi</span>
                                    @elseif($inv->status === 'cancelled')
                                        <span class="font-bold text-xs text-zinc-400">Dibatalkan</span>
                                    @else
                                        <span class="font-bold text-xs text-rose-600">Menunggu Bayar</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">
                                    <a
                                        href="{{ route('owner.subscription.invoice', $inv->id) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-xs transition"
                                    >
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-500" />
                                        <span>Detail</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <!-- Modal Dialog Popup Konfirmasi Langganan Lengkap dengan Benefit -->
    @php
        $planBenefitsMap = $plans->mapWithKeys(function ($p) {
            $code = strtolower((string) $p->code);
            $benefits = [];
            $benefits[] = 'Maksimal ' . $p->max_branches . ' Cabang Outlet';
            $benefits[] = 'Maksimal ' . ($p->max_staff ? $p->max_staff . ' Karyawan' : 'Unlimited Karyawan');

            if ($code === 'starter') {
                $benefits[] = 'Papan Kasir POS & Antrean Walk-in / Booking';
                $benefits[] = 'Layar TV Display Antrean Live di Outlet';
                $benefits[] = 'Payroll Sederhana (Gaji Pokok Dasar)';
            } elseif ($code === 'pro') {
                $benefits[] = 'Komisi Barber Otomatis (Per Layanan & Kategori)';
                $benefits[] = 'Payroll Full (Multi-Skema Gaji & Tunjangan)';
                $benefits[] = 'Grafik Analitik Pro (Tren Omzet & Efektivitas Staf)';
                $benefits[] = 'Custom Logo & Warna Tema Brand Barbershop';
                $benefits[] = 'Notifikasi WhatsApp Pelanggan';
            } elseif ($code === 'business') {
                $benefits[] = 'Cetak Dokumen Slip Gaji Resmi (Print & PDF)';
                $benefits[] = 'Konsolidasi Payroll Multi-Outlet Seluruh Cabang';
                $benefits[] = 'Prioritas Server Superadmin & Dukungan Khusus';
                $benefits[] = 'Semua Fitur Pro & Kustomisasi Tanpa Batas';
            } else {
                $benefits[] = 'Papan Kasir POS & Antrean Walk-in / Booking';
                $benefits[] = 'Layar TV Display Antrean Live';
            }

            return [(string) $p->id => $benefits];
        });
    @endphp

    <style>
        #modal-plan-confirm {
            outline: none !important;
            border: 1px solid rgb(228 228 231) !important;
        }
        #modal-plan-confirm:focus,
        #modal-plan-confirm:focus-visible {
            outline: none !important;
            border: 1px solid rgb(228 228 231) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        }
    </style>

    <dialog id="modal-plan-confirm" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] max-w-md mx-auto my-auto border border-zinc-200 bg-white outline-none focus:outline-none focus-visible:outline-none ring-0">
        <form method="POST" action="{{ route('owner.subscription.upgrade') }}" id="form-plan-confirm">
            @csrf
            <input type="hidden" name="plan_id" id="popup-plan-id" value="">

            <div class="p-5 sm:p-6 space-y-4 text-center">
                <!-- Header Popup: Judul Paket & Deskripsi -->
                <div>
                    <div class="inline-flex items-center justify-center gap-2">
                        <h3 class="font-black text-base sm:text-lg text-zinc-900 tracking-tight" id="popup-plan-title">Ganti Paket</h3>
                        <span id="popup-plan-badge" class="inline-flex items-center shrink-0">
                            <span id="popup-icon-starter" style="display: none;" class="items-center">
                                <x-ui.plan-badge code="starter" size="sm" />
                            </span>
                            <span id="popup-icon-pro" style="display: none;" class="items-center">
                                <x-ui.plan-badge code="pro" size="sm" />
                            </span>
                            <span id="popup-icon-business" style="display: none;" class="items-center">
                                <x-ui.plan-badge code="business" size="sm" />
                            </span>
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500 mt-1 leading-relaxed" id="popup-plan-desc">
                        Sisa hari paket lama tidak diakumulasikan, diganti 30 hari paket baru.
                    </p>
                </div>

                <!-- Benefit & Fitur Utama Paket yang Dipilih -->
                <div class="p-3.5 sm:p-4 rounded-2xl bg-zinc-50/80 border border-zinc-200/80 text-left space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-zinc-500 flex items-center gap-1.5">
                        <x-ui.icon name="sparkles" class="w-3.5 h-3.5 text-amber-500" />
                        <span>Benefit &amp; Fitur yang Didapatkan:</span>
                    </span>
                    <ul id="popup-plan-benefits" class="space-y-1.5 text-xs text-zinc-700">
                        <!-- Disisipkan otomatis via JavaScript -->
                    </ul>
                </div>

                <!-- Peringatan Ringkas & Simpel Khusus Downgrade -->
                <div id="popup-downgrade-warning" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-200/80 text-[11px] text-amber-900 font-semibold text-left flex items-start gap-2">
                    <x-ui.icon name="lock" class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
                    <span>Fitur paket sebelumnya akan dikunci setelah pembayaran dikonfirmasi.</span>
                </div>

                <!-- Total Tagihan -->
                <div class="py-2.5 px-4 rounded-xl bg-amber-50/60 border border-amber-200/80 flex items-center justify-between text-xs">
                    <div class="text-left">
                        <span class="text-zinc-500 block text-[11px] font-semibold">Total Tagihan:</span>
                        <span class="text-[10px] text-zinc-400 font-medium">Masa aktif 30 hari</span>
                    </div>
                    <strong class="text-zinc-950 font-black text-base sm:text-lg" id="popup-plan-price">Rp0</strong>
                </div>
            </div>

            <!-- Footer Tombol Aksi -->
            <div class="p-3.5 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-2.5 rounded-b-3xl">
                <button
                    type="button"
                    onclick="document.getElementById('modal-plan-confirm').close()"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-600 hover:bg-zinc-200/70 transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl text-xs font-black bg-zinc-900 hover:bg-zinc-800 text-white shadow-xs transition cursor-pointer"
                    id="popup-btn-submit"
                >
                    Lanjutkan
                </button>
            </div>
        </form>
    </dialog>

    <script>
        const allPlanBenefits = @json($planBenefitsMap);

        function openPlanPopup(planId, planName, planPriceFormatted, isRenewal, daysRemaining, isDowngrade, planCode) {
            document.getElementById('popup-plan-id').value = planId;
            const title = document.getElementById('popup-plan-title');
            const desc = document.getElementById('popup-plan-desc');
            const price = document.getElementById('popup-plan-price');
            const submitBtn = document.getElementById('popup-btn-submit');
            const downgradeWarning = document.getElementById('popup-downgrade-warning');
            const benefitsList = document.getElementById('popup-plan-benefits');

            // Render daftar benefit paket
            if (benefitsList) {
                benefitsList.innerHTML = '';
                const benefits = allPlanBenefits[planId] || allPlanBenefits[String(planId)] || [];
                benefits.forEach(function (b) {
                    const li = document.createElement('li');
                    li.className = 'flex items-start gap-2 text-xs text-zinc-700';
                    li.innerHTML = '<span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><span class="leading-tight font-medium text-zinc-800">' + b + '</span>';
                    benefitsList.appendChild(li);
                });
            }

            // Set icon modal sesuai paket di sebelah nama langganan
            const iconStarter = document.getElementById('popup-icon-starter');
            const iconPro = document.getElementById('popup-icon-pro');
            const iconBusiness = document.getElementById('popup-icon-business');

            if (iconStarter) iconStarter.style.display = 'none';
            if (iconPro) iconPro.style.display = 'none';
            if (iconBusiness) iconBusiness.style.display = 'none';

            const normalizedCode = (planCode || '').toLowerCase();
            const lowerName = (planName || '').toLowerCase();
            if (normalizedCode === 'business' || lowerName.includes('business')) {
                if (iconBusiness) iconBusiness.style.display = 'inline-flex';
            } else if (normalizedCode === 'pro' || lowerName.includes('pro')) {
                if (iconPro) iconPro.style.display = 'inline-flex';
            } else {
                if (iconStarter) iconStarter.style.display = 'inline-flex';
            }

            price.textContent = planPriceFormatted;

            if (isRenewal) {
                title.textContent = `Perpanjang ${planName}`;
                desc.textContent = 'Masa aktif bertambah +30 hari dari masa aktif saat ini.';
                if (downgradeWarning) downgradeWarning.classList.add('hidden');
                submitBtn.className = 'px-5 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 shadow-xs transition cursor-pointer';
                submitBtn.textContent = 'Perpanjang (+30 Hari)';
            } else {
                title.textContent = `Pilih ${planName}`;
                if (daysRemaining > 0) {
                    desc.textContent = `Sisa masa aktif (${daysRemaining} hari) tidak ditumpuk, diganti 30 hari paket baru.`;
                } else {
                    desc.textContent = 'Masa aktif berlaku selama 30 hari kalender.';
                }

                // Tampilkan peringatan simpel khusus downgrade
                if (isDowngrade) {
                    if (downgradeWarning) downgradeWarning.classList.remove('hidden');
                } else {
                    if (downgradeWarning) downgradeWarning.classList.add('hidden');
                }

                submitBtn.className = 'px-5 py-2.5 rounded-xl text-xs font-black bg-zinc-900 hover:bg-zinc-800 text-white shadow-xs transition cursor-pointer';
                submitBtn.textContent = 'Lanjutkan';
            }

            document.getElementById('modal-plan-confirm').showModal();
        }

        document.getElementById('modal-support-superadmin')?.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) {
                this.close();
            }
        });
    </script>

    <!-- ========================================================================= -->
    <!-- FLOATING ACTION BUTTON: BANTUAN & KOMPLAIN SUPERADMIN                     -->
    <!-- Menyesuaikan tampilan mobile, tablet, & desktop secara responsif          -->
    <!-- Diposisikan bottom-20 di Mobile agar tidak tertutup nav drawer bawah       -->
    <!-- ========================================================================= -->
    <div class="fixed bottom-20 sm:bottom-6 right-4 sm:right-6 z-40">
        <button
            type="button"
            onclick="document.getElementById('modal-support-superadmin').showModal()"
            class="group flex items-center gap-2.5 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-full bg-gradient-to-r from-amber-600 via-amber-700 to-amber-800 hover:from-amber-500 hover:to-amber-700 text-white shadow-xl hover:shadow-2xl shadow-amber-950/25 hover:scale-105 active:scale-95 transition-all duration-200 border border-amber-400/40 cursor-pointer"
            title="Pusat Bantuan & Komplain Langganan ke Superadmin"
        >
            <div class="relative">
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white shrink-0 shadow-inner group-hover:rotate-12 transition-transform">
                    <x-ui.icon name="help-circle" class="w-4 h-4 text-white" />
                </span>
                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-amber-700 animate-pulse"></span>
            </div>
            <div class="text-left hidden sm:block">
                <span class="block text-[11px] font-black leading-tight tracking-tight">Butuh Bantuan?</span>
                <span class="block text-[10px] text-amber-200 font-semibold leading-tight">Kontak Superadmin</span>
            </div>
            <span class="sm:hidden text-xs font-black pr-1">Bantuan</span>
        </button>
    </div>

    <!-- Modal Dialog Bantuan & Komplain Superadmin -->
    <dialog id="modal-support-superadmin" class="fixed inset-0 m-auto rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/60 backdrop:backdrop-blur-xs w-[calc(100%-2rem)] sm:w-full max-w-md border border-zinc-200">
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-amber-500/15 text-amber-600 flex items-center justify-center shrink-0">
                        <x-ui.icon name="help-circle" class="w-6 h-6 text-amber-600" />
                    </div>
                    <div>
                        <h3 class="font-black text-base text-zinc-900">Pusat Bantuan &amp; Komplain</h3>
                        <p class="text-xs text-zinc-500">Hubungi tim Superadmin platform resmi</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('modal-support-superadmin').close()"
                    class="w-8 h-8 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-500 flex items-center justify-center transition cursor-pointer"
                    title="Tutup Modal"
                >
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-xs text-amber-950 space-y-1">
                <p class="font-bold flex items-center gap-1.5 text-amber-900">
                    <x-ui.icon name="info" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                    <span>Informasi Layanan Superadmin</span>
                </p>
                <p class="text-zinc-600 text-[11px] leading-relaxed">
                    Sampaikan keluhan operasional, konfirmasi pembayaran invoice, permintaan paket kustom, atau kendala fitur langganan Anda secara langsung.
                </p>
            </div>

            <!-- Opsi Kontak Responsif -->
            <div class="space-y-2.5 pt-1">
                <!-- 1. WhatsApp Superadmin -->
                <a
                    href="{{ $waLink }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="p-3.5 rounded-2xl border border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/70 hover:border-emerald-300 transition flex items-center justify-between group cursor-pointer"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <x-ui.icon name="message-circle" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-xs text-zinc-900 group-hover:text-emerald-800">Chat WhatsApp Langsung</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-emerald-200/70 text-emerald-800 uppercase tracking-wider">Fast Response</span>
                            </div>
                            <span class="text-[11px] text-zinc-500 truncate block">{{ $supportPhone }}</span>
                        </div>
                    </div>
                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-emerald-600 group-hover:translate-x-1 transition shrink-0" />
                </a>

                <!-- 2. Telepon Seluler -->
                <a
                    href="{{ $telLink }}"
                    class="p-3.5 rounded-2xl border border-zinc-200/90 bg-white hover:bg-zinc-50 hover:border-zinc-300 transition flex items-center justify-between group cursor-pointer"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="phone" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <span class="font-extrabold text-xs text-zinc-900 group-hover:text-zinc-950 block">Panggilan Suara / Telepon</span>
                            <span class="text-[11px] text-zinc-500 truncate block">{{ $supportPhone }}</span>
                        </div>
                    </div>
                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-zinc-400 group-hover:translate-x-1 transition shrink-0" />
                </a>

                <!-- 3. Email Resmi -->
                <a
                    href="{{ $emailLink }}"
                    class="p-3.5 rounded-2xl border border-zinc-200/90 bg-white hover:bg-zinc-50 hover:border-zinc-300 transition flex items-center justify-between group cursor-pointer"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                            <x-ui.icon name="mail" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <span class="font-extrabold text-xs text-zinc-900 group-hover:text-zinc-950 block">Kirim Email Komplain Formal</span>
                            <span class="text-[11px] text-zinc-500 truncate block">{{ $supportEmail }}</span>
                        </div>
                    </div>
                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-zinc-400 group-hover:translate-x-1 transition shrink-0" />
                </a>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-400">
                <span>Jam Layanan: Setiap Hari 08:00 - 21:00 WIB</span>
                <button
                    type="button"
                    onclick="document.getElementById('modal-support-superadmin').close()"
                    class="font-bold text-zinc-600 hover:text-zinc-900 cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </dialog>
</x-layouts.app>
