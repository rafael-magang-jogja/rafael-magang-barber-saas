@props([
    'title' => 'Dashboard',
    'role' => null,
    'subtitle' => null,
    'fullWidth' => false,
])

@php
    $currentTenantCtx = app(\App\Support\Tenancy\CurrentTenant::class);

    // Gunakan role asli dari middleware SetCurrentTenant agar Owner tidak berubah menjadi Kasir saat membuka halaman Kasir
    $effectiveRole = $currentTenantCtx->role()
        ?? request()->attributes->get('current_role')
        ?? $role
        ?? (request()->routeIs('cashier.*') ? 'cashier' : 'owner');

    $isSuperadminArea = $effectiveRole === 'superadmin' || request()->routeIs('platform.*') || request()->is('platform*');

    if ($isSuperadminArea) {
        $tenant = null;
        $brandColor = \App\Support\Platform\PlatformConfig::themeColor();
    } else {
        $tenant = $currentTenantCtx->get();
        $brandColor = $tenant?->brand_color ?? \App\Support\Platform\PlatformConfig::themeColor();
    }

    $isCashierOrAdmin = in_array($effectiveRole, ['admin', 'cashier'], true) && $effectiveRole !== 'owner';

    $roleLabel = match ($effectiveRole) {
        'superadmin' => 'Superadmin SaaS',
        'owner' => 'Owner Barbershop',
        'admin', 'cashier' => 'Admin / Kasir',
        'barber' => 'Staff Barber',
        'customer' => 'Pelanggan',
        default => strtoupper((string) $effectiveRole),
    };

    $hasWalkinBookingDot = false;
    $hasLowColorStockDot = false;
    $hasPendingTimeOffDot = false;
    if ($tenant && in_array($effectiveRole, ['owner', 'admin', 'cashier'], true)) {
        $hasWalkinBookingDot = $currentTenantCtx->hasWalkinBookingDot();
    }
    if ($tenant && $effectiveRole === 'owner') {
        $hasLowColorStockDot = $currentTenantCtx->hasLowColorStockDot();
        $hasPendingTimeOffDot = $currentTenantCtx->hasPendingTimeOffDot();
    }

    $isSubscriptionLocked = ($effectiveRole !== 'superadmin') && $currentTenantCtx->isSubscriptionLocked();
    $sidebarSub = $currentTenantCtx->subscription();
    $tenantPlanCode = $sidebarSub?->plan?->code ?? ($tenant?->subscription?->plan?->code ?? 'starter');

    $superadminPendingConfirmationsCount = 0;
    $pendingCustomerWithdrawalsCount = 0;
    if ($effectiveRole === 'superadmin') {
        $superadminPendingConfirmationsCount = $currentTenantCtx->superadminPendingInvoicesCount();
        $pendingCustomerWithdrawalsCount = \App\Models\CustomerWithdrawal::query()->where('status', 'pending')->count();
    }

    $staffMembership = null;
    $staffAttendanceStatus = null;
    if ($tenant && auth()->check() && in_array($effectiveRole, ['admin', 'cashier'], true)) {
        $staffMembership = $currentTenantCtx->staffMembership(auth()->id());
        $staffAttendanceStatus = $currentTenantCtx->staffAttendanceStatus(auth()->id());
    }

    $menus = match ($effectiveRole) {
        'superadmin' => [
            // Monitoring & Analitik
            ['is_section' => true, 'label' => 'Monitoring & Analitik'],
            [
                'label' => 'Platform Overview',
                'short_label' => 'Overview',
                'href' => route('platform.dashboard'),
                'icon' => 'zap',
                'active_routes' => ['platform.dashboard', 'platform.tenants.*'],
            ],
            [
                'label' => 'Laporan Platform',
                'short_label' => 'Laporan',
                'href' => route('platform.reports'),
                'icon' => 'trending-up',
                'active_routes' => ['platform.reports*'],
            ],

            // Langganan & Keuangan
            ['is_section' => true, 'label' => 'Langganan & Keuangan'],
            [
                'label' => 'Tagihan & Konfirmasi',
                'short_label' => 'Tagihan',
                'href' => route('platform.subscriptions'),
                'icon' => 'banknote',
                'badge_count' => $superadminPendingConfirmationsCount > 0 ? $superadminPendingConfirmationsCount : null,
                'active_routes' => ['platform.subscriptions*'],
            ],
            [
                'label' => 'Pencairan Dana (Payouts)',
                'short_label' => 'Pencairan',
                'href' => route('platform.payouts'),
                'icon' => 'wallet',
                'active_routes' => ['platform.payouts*'],
            ],
            [
                'label' => 'Penarikan Customer',
                'short_label' => 'Tarik Saldo',
                'href' => route('platform.withdrawals'),
                'icon' => 'arrow-up-right',
                'badge_count' => $pendingCustomerWithdrawalsCount > 0 ? $pendingCustomerWithdrawalsCount : null,
                'active_routes' => ['platform.withdrawals*'],
            ],
            [
                'label' => 'Monitoring Pendapatan',
                'short_label' => 'Pendapatan',
                'href' => route('platform.transactions'),
                'icon' => 'receipt',
                'active_routes' => ['platform.transactions*'],
            ],
            [
                'label' => 'Paket Langganan',
                'short_label' => 'Paket',
                'href' => route('platform.plans'),
                'icon' => 'coins',
                'active_routes' => ['platform.plans*'],
            ],

            // Komunikasi & Promosi
            ['is_section' => true, 'label' => 'Komunikasi & Promosi'],
            [
                'label' => 'Siaran Pengumuman',
                'short_label' => 'Pengumuman',
                'href' => route('platform.announcements'),
                'icon' => 'megaphone',
                'active_routes' => ['platform.announcements*'],
            ],
            [
                'label' => 'Iklan Produk',
                'short_label' => 'Iklan',
                'href' => route('platform.promotions'),
                'icon' => 'tag',
                'active_routes' => ['platform.promotions*'],
            ],

            // Sistem & Konfigurasi
            ['is_section' => true, 'label' => 'Sistem & Konfigurasi'],
            [
                'label' => 'Konfigurasi SaaS',
                'short_label' => 'Sistem',
                'href' => route('platform.settings'),
                'icon' => 'settings',
                'active_routes' => ['platform.settings*'],
            ],
        ],
        'admin', 'cashier' => [
            // Operasional Kasir
            ['is_section' => true, 'label' => 'Operasional Kasir'],
            [
                'label' => 'Papan Antrean & POS',
                'short_label' => 'Antrean POS',
                'href' => route('cashier.dashboard'),
                'icon' => 'ticket',
                'badge_type' => 'queue',
                'active_routes' => ['cashier.dashboard', 'cashier.walk-in', 'cashier.bookings*'],
            ],

            // Kinerja & Absensi
            ['is_section' => true, 'label' => 'Kinerja & Absensi'],
            [
                'label' => 'Absensi & Jadwal',
                'short_label' => 'Absen',
                'href' => route('cashier.attendance'),
                'icon' => 'clock',
                'attendance_status' => $staffAttendanceStatus,
                'active_routes' => ['cashier.attendance*'],
            ],
            [
                'label' => 'Pendapatan Kasir',
                'short_label' => 'Pendapatan',
                'href' => route('cashier.earnings'),
                'icon' => 'wallet',
                'active_routes' => ['cashier.earnings*'],
            ],

            // Akun Saya
            ['is_section' => true, 'label' => 'Akun Saya'],
            [
                'label' => 'Profil Kasir',
                'short_label' => 'Profil',
                'href' => route('cashier.profile'),
                'icon' => 'user',
                'active_routes' => ['cashier.profile*'],
            ],
        ],
        'barber' => [
            ['label' => 'Antrean Saya', 'short_label' => 'Antrean', 'href' => route('barber.dashboard'), 'icon' => 'scissors'],
        ],
        'customer' => [
            ['label' => 'Dashboard', 'short_label' => 'Dashboard', 'href' => route('customer.dashboard'), 'icon' => 'sparkles'],
            ['label' => 'Booking', 'short_label' => 'Booking', 'href' => route('customer.booking'), 'icon' => 'calendar'],
            ['label' => 'Riwayat', 'short_label' => 'Riwayat', 'href' => route('customer.history'), 'icon' => 'file-text'],
            ['label' => 'Profil', 'short_label' => 'Profil', 'href' => route('customer.profile'), 'icon' => 'users'],
        ],
        default => [
            // Operasional
            ['is_section' => true, 'label' => 'Operasional'],
            ['label' => 'Dashboard', 'short_label' => 'Beranda', 'href' => route('owner.dashboard'), 'icon' => 'chart', 'active_routes' => ['owner.dashboard']],
            [
                'label' => 'Kasir & Antrean',
                'short_label' => 'Kasir',
                'href' => route('cashier.dashboard'),
                'icon' => 'ticket',
                'badge_type' => 'queue',
                'active_routes' => ['cashier.dashboard', 'cashier.walk-in', 'cashier.bookings*'],
            ],
            ['label' => 'Transaksi', 'short_label' => 'Transaksi', 'href' => route('owner.transactions'), 'icon' => 'receipt', 'active_routes' => ['owner.transactions*']],
            ['label' => 'Customer', 'short_label' => 'Customer', 'href' => route('owner.customers'), 'icon' => 'user-check', 'active_routes' => ['owner.customers*']],

            // Manajemen Tim
            ['is_section' => true, 'label' => 'Manajemen Tim'],
            ['label' => 'Karyawan', 'short_label' => 'Tim', 'href' => route('owner.staff'), 'icon' => 'users', 'active_routes' => ['owner.staff*']],
            [
                'label' => 'Jadwal & Absensi',
                'short_label' => 'Jadwal',
                'href' => route('owner.schedules'),
                'icon' => 'calendar',
                'badge_dot' => $hasPendingTimeOffDot,
                'children' => [
                    ['label' => 'Jadwal Shift Kerja', 'href' => route('owner.schedules')],
                    ['label' => 'Scan Absen Karyawan', 'href' => route('owner.attendances.scanner')],
                    ['label' => 'Rekap & Log Absensi', 'href' => route('owner.attendances')],
                    [
                        'label' => 'Pengajuan & Izin Libur',
                        'href' => route('owner.time-offs'),
                        'badge_dot' => $hasPendingTimeOffDot,
                    ],
                    ['label' => 'Pengaturan & Toleransi', 'href' => route('owner.schedule-settings')],
                ],
            ],
            [
                'label' => 'Gaji & Komisi',
                'short_label' => 'Gaji',
                'href' => route('owner.payroll'),
                'icon' => 'banknote',
                'children' => [
                    ['label' => 'Rekap & Slip Payroll', 'href' => route('owner.payroll')],
                    ['label' => 'Gaji per Karyawan', 'href' => route('owner.payroll.staff')],
                    ['label' => 'Standar Gaji & Komisi', 'href' => route('owner.payroll.rules')],
                ],
            ],

            // Outlet & Layanan
            ['is_section' => true, 'label' => 'Outlet & Layanan'],
            ['label' => 'Cabang', 'short_label' => 'Cabang', 'href' => route('owner.branches'), 'icon' => 'store', 'active_routes' => ['owner.branches*']],
            [
                'label' => 'Layanan & Produk',
                'short_label' => 'Layanan',
                'href' => route('owner.services'),
                'icon' => 'scissors',
                'children' => [
                    ['label' => 'Daftar Layanan & Harga', 'href' => route('owner.services')],
                    [
                        'label' => 'Warna Rambut & Stok',
                        'href' => route('owner.colors'),
                        'badge_dot' => $hasLowColorStockDot,
                    ],
                ],
            ],

            // Keuangan & Pengaturan
            ['is_section' => true, 'label' => 'Keuangan & Pengaturan'],
            ['label' => 'Laporan', 'short_label' => 'Laporan', 'href' => route('owner.reports'), 'icon' => 'file-text', 'active_routes' => ['owner.reports*']],
            [
                'label' => 'Pencairan Saldo Booking',
                'short_label' => 'Pencairan',
                'href' => route('owner.payouts'),
                'icon' => 'wallet',
                'active_routes' => ['owner.payouts*'],
            ],
            ['label' => 'Langganan & Paket', 'short_label' => 'Paket', 'href' => route('owner.subscription'), 'icon' => 'sparkles', 'active_routes' => ['owner.subscription*']],
            ['label' => 'Pengaturan Barbershop', 'short_label' => 'Setting', 'href' => route('owner.settings'), 'icon' => 'settings', 'active_routes' => ['owner.settings*']],
        ],
    };

    $mobileNavMenus = $menus;
    if ($effectiveRole === 'owner') {
        $mobileNavMenus = [
            [
                'label' => 'Dashboard',
                'short_label' => 'Beranda',
                'href' => route('owner.dashboard'),
                'active_routes' => ['owner.dashboard'],
                'icon' => 'chart',
            ],
            [
                'label' => 'Antrean & Booking',
                'short_label' => 'Antrean',
                'href' => route('cashier.dashboard'),
                'active_routes' => ['cashier.*'],
                'icon' => 'ticket',
                'badge_dot' => $hasWalkinBookingDot,
            ],
            [
                'label' => 'Jadwal & Absensi',
                'short_label' => 'Jadwal',
                'href' => route('owner.schedules'),
                'active_routes' => ['owner.schedules*', 'owner.time-offs*', 'owner.attendances*', 'owner.schedule-settings*'],
                'icon' => 'calendar',
                'badge_dot' => $hasPendingTimeOffDot,
            ],
            [
                'label' => 'Transaksi',
                'short_label' => 'Transaksi',
                'href' => route('owner.transactions'),
                'active_routes' => ['owner.transactions*'],
                'icon' => 'receipt',
            ],
            [
                'label' => 'Menu Lengkap',
                'short_label' => 'Menu',
                'href' => '#mobile-menu-drawer',
                'is_drawer' => true,
                'active_routes' => ['owner.staff*', 'owner.payroll*', 'owner.branches*', 'owner.services*', 'owner.colors*', 'owner.customers*', 'owner.reports*', 'owner.settings*'],
                'icon' => 'grid',
                'badge_dot' => $hasLowColorStockDot,
            ],
        ];
    } elseif ($effectiveRole === 'superadmin') {
        $mobileNavMenus = [
            [
                'label' => 'Platform Overview',
                'short_label' => 'Overview',
                'href' => route('platform.dashboard'),
                'active_routes' => ['platform.dashboard'],
                'icon' => 'zap',
            ],
            [
                'label' => 'Tagihan & Konfirmasi',
                'short_label' => 'Tagihan',
                'href' => route('platform.subscriptions'),
                'active_routes' => ['platform.subscriptions*'],
                'icon' => 'banknote',
                'badge_count' => $superadminPendingConfirmationsCount > 0 ? $superadminPendingConfirmationsCount : null,
            ],
            [
                'label' => 'Siaran Pengumuman',
                'short_label' => 'Pengumuman',
                'href' => route('platform.announcements'),
                'active_routes' => ['platform.announcements*'],
                'icon' => 'megaphone',
            ],
            [
                'label' => 'Menu Lengkap',
                'short_label' => 'Menu',
                'href' => '#mobile-menu-drawer',
                'is_drawer' => true,
                'active_routes' => ['platform.plans*', 'platform.reports*', 'platform.promotions*', 'platform.settings*', 'platform.transactions*', 'platform.payouts*'],
                'icon' => 'grid',
            ],
        ];
    } elseif ($isCashierOrAdmin) {
        $mobileNavMenus = [
            [
                'label' => 'Papan Antrean & POS',
                'short_label' => 'Antrean POS',
                'href' => route('cashier.dashboard'),
                'active_routes' => ['cashier.dashboard', 'cashier.walk-in', 'cashier.bookings*'],
                'icon' => 'ticket',
                'badge_type' => 'queue',
            ],
            [
                'label' => 'Absensi & Jadwal',
                'short_label' => 'Absen',
                'href' => route('cashier.attendance'),
                'active_routes' => ['cashier.attendance'],
                'icon' => 'clock',
                'attendance_status' => $staffAttendanceStatus,
            ],
            [
                'label' => 'Pendapatan Kasir',
                'short_label' => 'Pendapatan',
                'href' => route('cashier.earnings'),
                'active_routes' => ['cashier.earnings'],
                'icon' => 'wallet',
            ],
            [
                'label' => 'Profil Kasir',
                'short_label' => 'Profil',
                'href' => route('cashier.profile'),
                'active_routes' => ['cashier.profile', 'cashier.profile.edit'],
                'icon' => 'user',
            ],
        ];
    }
@endphp

<!DOCTYPE html>
<html lang="id" @style(['--tenant-brand: ' . $brandColor])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $isSuperadminArea ? \App\Support\Platform\PlatformConfig::name() : ($tenant?->name ?? \App\Support\Platform\PlatformConfig::name()) }}</title>
    @php
        $activeFaviconUrl = ($isSuperadminArea || ! $tenant)
            ? \App\Support\Platform\PlatformConfig::faviconUrl()
            : $tenant->faviconUrl();
        $activeSvgFaviconUrl = ($isSuperadminArea || ! $tenant)
            ? (\App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null)
            : $tenant->svgFaviconUrl();
    @endphp
    <link rel="icon" type="image/x-icon" href="{{ $activeFaviconUrl }}">
    @if($activeSvgFaviconUrl)
        <link rel="icon" type="image/svg+xml" href="{{ $activeSvgFaviconUrl }}">
    @endif
    <x-ui.theme-styles :color="$brandColor" :tenant="$tenant" />
    <script>
        (function() {
            try {
                var w = window.innerWidth;
                if (w >= 768 && w < 1024) {
                    if (localStorage.getItem('tablet_sidebar_open') === 'true') {
                        document.documentElement.classList.add('tablet-sidebar-open');
                    }
                }
            } catch (e) {}
        })();

        window.toggleSidebar = function() {
            if (window.innerWidth >= 768 && window.innerWidth < 1024) {
                var html = document.documentElement;
                var willBeOpen = !html.classList.contains('tablet-sidebar-open');
                
                // 1. FLIP Technique: Catat posisi awal seluruh kartu responsif sebelum reflow (Semua Halaman)
                var cards = document.querySelectorAll(
                    '.schedules-stat-grid > *, .schemes-cards-grid > *, .sim-results-grid > *, .service-commissions-grid > *, .staff-salary-grid > *, [data-tablet-flip] > *'
                );
                var firstRects = [];
                cards.forEach(function(el) {
                    firstRects.push(el.getBoundingClientRect());
                    el.style.transition = 'none';
                });

                // 2. Toggle class state sidebar
                html.classList.toggle('tablet-sidebar-open', willBeOpen);
                
                // 3. Hitung delta posisi (First vs Last) dan pasang inverse transform secara instan
                cards.forEach(function(el, i) {
                    var first = firstRects[i];
                    if (!first) return;
                    var last = el.getBoundingClientRect();
                    var dx = first.left - last.left;
                    var dy = first.top - last.top;
                    
                    if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
                        el.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
                    } else {
                        el.style.transform = '';
                    }
                });
                
                // 4. Di frame berikutnya, kembalikan transform ke normal dengan transisi ease-in-out 0.5s
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        cards.forEach(function(el) {
                            el.style.transition = 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                            el.style.transform = '';
                        });
                    });
                });

                // 5. Bersihkan inline style setelah animasi 0.5s selesai agar efek hover kembali normal
                if (window.__sidebarFlipTimeout) clearTimeout(window.__sidebarFlipTimeout);
                window.__sidebarFlipTimeout = setTimeout(function() {
                    cards.forEach(function(el) {
                        el.style.transition = '';
                    });
                }, 520);

                try {
                    localStorage.setItem('tablet_sidebar_open', willBeOpen ? 'true' : 'false');
                } catch(e) {}
            }
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* ============================================================
           FIX TABLET & DESKTOP SIDEBAR: MURNI FIXED VIEWPORT
           Mencegah bug transform: translate3d yang membuat sidebar
           ikut ter-scroll dan meninggalkan space kosong di bagian bawah.
           ============================================================ */
        @media (min-width: 768px) and (max-width: 1023.98px) {
            #app-sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: -16rem !important;
                width: 16rem !important;
                height: auto !important;
                min-height: 100% !important;
                max-height: 100% !important;
                transform: none !important;
                z-index: 40 !important;
                transition: left 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
                overflow-y: hidden !important;
                will-change: left;
                backface-visibility: hidden;
            }

            #main-content-wrapper {
                padding-left: 0 !important;
                transition: padding-left 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
                will-change: padding-left;
            }

            #tablet-sidebar-toggle {
                position: fixed !important;
                top: 1.125rem !important;
                left: 0.5rem !important;
                width: 2rem !important;
                height: 2rem !important;
                transform: none !important;
                transition: left 0.5s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease, background-color 0.3s ease !important;
                z-index: 60 !important;
                will-change: left;
                backface-visibility: hidden;
            }

            header .md\:max-lg\:pl-9 {
                transition: padding-left 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            html.tablet-sidebar-open #app-sidebar {
                left: 0 !important;
                transform: none !important;
            }

            html.tablet-sidebar-open #main-content-wrapper {
                padding-left: 16rem !important;
            }

            html.tablet-sidebar-open #tablet-sidebar-toggle {
                left: 15rem !important; /* Tepat di garis batas antara sidebar (16rem) dan header */
                transform: none !important;
            }

            html.tablet-sidebar-open header .md\:max-lg\:pl-9 {
                padding-left: 1.5rem !important;
            }

            /* Transisi halus elemen layout konten saat sidebar dibuka/ditutup (0.5 detik ease-in-out di semua halaman) */
            .time-offs-filter-container,
            .time-offs-filter-form,
            .time-offs-filter-btn,
            .schemes-header-container,
            .staff-search-wrapper,
            .staff-filter-form,
            .staff-filter-controls,
            .staff-action-buttons,
            .schedules-stat-grid,
            .stat-cards-grid,
            .stat-cards-grid > *,
            .schemes-cards-grid,
            .sim-results-grid,
            .service-commissions-grid,
            .staff-salary-grid,
            .staff-salary-grid > *,
            .subscription-plans-grid,
            .subscription-plans-grid > *,
            .subscription-metrics-grid,
            .subscription-metrics-grid > *,
            main > .grid,
            main section > .grid,
            main [class*="grid-cols-"] {
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Hardware acceleration untuk elemen kartu agar pergeseran layout 60fps tanpa stutter */
            .schemes-cards-grid > *,
            .sim-results-grid > *,
            .stat-cards-grid > *,
            .schedules-stat-grid > *,
            .service-commissions-grid > *,
            .staff-salary-grid > *,
            .subscription-plans-grid > *,
            .subscription-metrics-grid > *,
            [data-tablet-flip] > *,
            main > .grid > *,
            main section > .grid > * {
                will-change: transform;
                backface-visibility: hidden;
            }

            /* Tablet: Skema Jabatan Header - jika sidebar aktif/terbuka, tata letak ditumpuk vertikal rapi agar judul & tombol leluasa */
            html.tablet-sidebar-open .schemes-header-container {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }

            html.tablet-sidebar-open .schemes-header-container > button {
                width: 100% !important;
            }

            /* Tablet: Kartu statistik (payroll staff, absensi, time-off) selalu 2 baris (2 kolom) baik sidebar aktif maupun nonaktif agar teks lega dan rapi */
            .stat-cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Khusus ringkasan shift jadwal, jika sidebar aktif 2 baris (2 kolom), jika sidebar nonaktif 4 kolom */
            html.tablet-sidebar-open .schedules-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .schedules-stat-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }

            /* Tablet: Skema Jabatan - jika sidebar aktif/terbuka 1 kolom (1 kartu per baris), jika tertutup 2 kolom */
            html.tablet-sidebar-open .schemes-cards-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .schemes-cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Hasil Simulasi Penggajian - jika sidebar aktif 1 kolom, jika tertutup 2 kolom */
            html.tablet-sidebar-open .sim-results-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .sim-results-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Komisi Layanan Barbershop - jika sidebar aktif 1 kolom agar lega & tidak berdepetan, jika tertutup 2 kolom */
            html.tablet-sidebar-open .service-commissions-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .service-commissions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Gaji per Karyawan - jika sidebar aktif 1 kolom agar lega & tidak berdepetan, jika tertutup 2 kolom */
            html.tablet-sidebar-open .staff-salary-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .staff-salary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Jam operasional & shift tetap rapi horizontal dan stabil tanpa lonjakan layout */
            .branch-hours-group {
                flex-direction: row !important;
                align-items: center !important;
                flex-wrap: wrap !important;
            }

            /* Tablet: Pengaturan Jam Operasional Cabang - jika sidebar aktif 1 kolom agar lega & input jam tidak wrap, jika tertutup 2 kolom */
            html.tablet-sidebar-open .branch-day-hours-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            html:not(.tablet-sidebar-open) .branch-day-hours-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Kehadiran Barber Cabang - jika sidebar aktif 1 kolom agar kartu & status jam hadir lega dan rapi, jika tertutup 2 kolom */
            html.tablet-sidebar-open .branch-barber-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            html:not(.tablet-sidebar-open) .branch-barber-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Monitor Kursi Barber - jika sidebar aktif 1 kolom agar kartu status kursi & antrean barber lega dan tidak bertumpuk vertikal, jika tertutup 2 kolom */
            html.tablet-sidebar-open .barber-chairs-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            html:not(.tablet-sidebar-open) .barber-chairs-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Monitor Kursi Barber Compact di Dashboard Kasir */
            html.tablet-sidebar-open .cashier-chairs-compact-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            html:not(.tablet-sidebar-open) .cashier-chairs-compact-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }

            /* Tablet: Form Toolbar Filter Kasir */
            html.tablet-sidebar-open .cashier-filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            html.tablet-sidebar-open #bottom-save-bar {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            html.tablet-sidebar-open #bottom-save-bar button {
                width: 100% !important;
            }

            /* Tablet: Jika sidebar aktif/terbuka, filter izin & libur disusun rapi 2 kolom dropdown + tombol full width */
            html.tablet-sidebar-open .time-offs-filter-container {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }

            html.tablet-sidebar-open .time-offs-filter-form {
                width: 100% !important;
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.625rem !important;
            }

            html.tablet-sidebar-open .time-offs-filter-form > div {
                width: 100% !important;
            }

            html.tablet-sidebar-open .time-offs-filter-btn {
                width: 100% !important;
            }

            /* Tablet: Jika sidebar aktif/terbuka, input search staff dibuat full kanan kiri dan filter diatur rapi */
            html.tablet-sidebar-open .staff-search-wrapper {
                width: 100% !important;
            }

            html.tablet-sidebar-open .staff-filter-form {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.625rem !important;
            }

            html.tablet-sidebar-open .staff-filter-controls {
                width: 100% !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                gap: 0.5rem !important;
            }

            html.tablet-sidebar-open .staff-filter-controls > [data-ui-select] {
                flex: 1 1 140px !important;
                width: auto !important;
                min-width: 130px !important;
            }

            html.tablet-sidebar-open .staff-action-buttons {
                flex: 0 0 auto !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.5rem !important;
                width: auto !important;
            }

            html.tablet-sidebar-open .staff-action-buttons > button,
            html.tablet-sidebar-open .staff-action-buttons > a {
                flex: 0 0 auto !important;
                width: auto !important;
                white-space: nowrap !important;
            }

            /* Tablet: Saat sidebar off (tertutup), input search dan seluruh filter/tombol wajib 1 baris saja */
            html:not(.tablet-sidebar-open) .staff-filter-form {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 0.5rem !important;
                width: 100% !important;
            }

            html:not(.tablet-sidebar-open) .staff-search-wrapper {
                flex: 1 1 0 !important;
                min-width: 130px !important;
                width: auto !important;
                max-width: none !important;
            }

            html:not(.tablet-sidebar-open) .staff-filter-controls {
                flex: 0 0 auto !important;
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 0.5rem !important;
                width: auto !important;
            }

            html:not(.tablet-sidebar-open) .staff-filter-controls > [data-ui-select] {
                flex: 0 0 auto !important;
                width: 8.5rem !important;
                min-width: 0 !important;
            }

            html:not(.tablet-sidebar-open) .staff-action-buttons {
                flex: 0 0 auto !important;
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 0.5rem !important;
                width: auto !important;
            }

            html:not(.tablet-sidebar-open) .staff-action-buttons > button,
            html:not(.tablet-sidebar-open) .staff-action-buttons > a {
                flex: 0 0 auto !important;
                white-space: nowrap !important;
                width: auto !important;
            }

            /* Tablet Mode: Laporan, Evaluasi Ulasan, & Riwayat Transaksi Owner */
            html.tablet-sidebar-open .report-review-header {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.875rem !important;
            }

            html.tablet-sidebar-open .report-review-badge {
                align-self: flex-start !important;
                width: 100% !important;
                justify-content: space-between !important;
            }

            html.tablet-sidebar-open .report-review-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.625rem !important;
            }

            html.tablet-sidebar-open .report-review-item-header {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.625rem !important;
            }

            html.tablet-sidebar-open .report-review-rating-row {
                width: 100% !important;
                border-top: 1px solid #f4f4f5 !important;
                padding-top: 0.5rem !important;
                justify-content: space-between !important;
            }

            html.tablet-sidebar-open .report-orders-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            html:not(.tablet-sidebar-open) .report-orders-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            /* Tablet: Pengumuman Global saat sidebar aktif/terbuka */
            html.tablet-sidebar-open .announcements-stat-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html.tablet-sidebar-open .announcements-urgency-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            html.tablet-sidebar-open .announcements-form-inputs-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }

            html.tablet-sidebar-open .announcements-header-container {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }

            html.tablet-sidebar-open .announcements-filter-form {
                width: 100% !important;
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.5rem !important;
            }
        }

        @media (min-width: 1024px) {
            #app-sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 16rem !important;
                height: auto !important;
                min-height: 100% !important;
                max-height: 100% !important;
                transform: none !important;
                z-index: 30 !important;
            }

            #main-content-wrapper {
                padding-left: 16rem !important;
            }
        }

        .sidebar-toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            will-change: transform;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            transform: rotate(0deg);
        }

        html.tablet-sidebar-open .sidebar-toggle-icon {
            transform: rotate(180deg);
        }

        /* Animasi Modal Dialog 0.5s Ease In-Out */
        dialog[open] {
            animation: modalEaseInOut 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        dialog::backdrop {
            animation: backdropEaseInOut 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes modalEaseInOut {
            from {
                opacity: 0;
                transform: scale(0.96) translateY(6px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes backdropEaseInOut {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body class="min-h-screen min-h-[100dvh] bg-zinc-100 text-zinc-900 antialiased flex flex-col">
    <!-- Sidebar: Collapsible di Tablet (md:), Fixed di Desktop (lg:), Hidden di Mobile -->
    <aside
        id="app-sidebar"
        class="hidden md:fixed md:inset-y-0 md:left-0 md:z-30 md:flex md:w-64 md:h-auto md:flex-col bg-zinc-950 text-zinc-400 p-4 shrink-0 border-r border-zinc-900"
    >
        <div class="flex items-center gap-2.5 px-2 py-1.5 border-b border-zinc-900 pb-3">
            @if($tenant?->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-9 h-9 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
            @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ \App\Support\Platform\PlatformConfig::name() }}" class="w-9 h-9 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
            @else
                <div class="w-9 h-9 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0">
                    <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-5 h-5" />
                </div>
            @endif
            <div class="leading-tight min-w-0">
                <div class="flex items-center gap-1.5 min-w-0">
                    <p class="text-white font-bold text-sm truncate">{{ $tenant?->name ?? \App\Support\Platform\PlatformConfig::name() }}</p>
                    @if($tenant && $effectiveRole !== 'superadmin')
                        <x-ui.plan-badge :code="$tenantPlanCode" />
                    @endif
                </div>
                <p class="text-xs text-amber-400 font-semibold uppercase tracking-wider">{{ $roleLabel }}</p>
            </div>
        </div>

        <nav class="mt-6 space-y-1 text-sm flex-1 overflow-y-auto no-scrollbar">
            @foreach($menus as $menu)
                @php
                    $isItemLocked = $isSubscriptionLocked && (!isset($menu['href']) || $menu['href'] !== route('owner.subscription'));
                @endphp
                @if(!empty($menu['is_section']))
                    <div class="pt-4 first:pt-1 pb-1 px-3 text-[10px] font-black uppercase tracking-wider text-zinc-500 select-none">
                        {{ $menu['label'] }}
                    </div>
                @elseif($isItemLocked)
                    <div class="flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-zinc-600 opacity-40 select-none cursor-not-allowed transition" title="Fitur dikunci: masa langganan berakhir">
                        <span class="flex items-center gap-3 min-w-0">
                            <x-ui.icon :name="$menu['icon']" class="w-5 h-5 shrink-0 text-zinc-600" />
                            <span class="truncate">{{ $menu['label'] }}</span>
                        </span>
                        <x-ui.icon name="lock" class="w-4 h-4 text-zinc-600 shrink-0" />
                    </div>
                @elseif(!empty($menu['children']))
                    @php
                        $childUrls = [];
                        $groupHasBadgeDot = false;
                        foreach ($menu['children'] as $c) {
                            $childUrls[] = strtok($c['href'], '#');
                            foreach ($c['active_urls'] ?? [] as $extraUrl) {
                                $childUrls[] = strtok($extraUrl, '#');
                            }
                            if (!empty($c['badge_dot'])) {
                                $groupHasBadgeDot = true;
                            }
                        }
                        $isGroupActive = in_array(request()->url(), $childUrls, true);
                    @endphp
                    <details name="sidebar-accordion" class="group rounded-xl {{ $isGroupActive ? 'bg-white/[0.04]' : '' }}" @if($isGroupActive) open @endif>
                        <summary class="list-none flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl font-semibold cursor-pointer select-none transition {{ $isGroupActive ? 'bg-white/10 text-white font-bold' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="flex items-center gap-3 min-w-0">
                                <x-ui.icon :name="$menu['icon']" class="w-5 h-5 shrink-0 {{ $isGroupActive ? 'text-amber-400' : 'text-zinc-400 group-hover:text-white' }}" />
                                <span class="truncate">{{ $menu['label'] }}</span>
                            </span>
                            <span class="flex items-center gap-1.5 shrink-0">
                                @if(!empty($menu['is_parent_queue']))
                                    <livewire:cashier-nav-badge type="parent_dot" context="sidebar_dot" :key="'badge-parent-dot-sidebar-' . $menu['label']" />
                                @elseif($groupHasBadgeDot)
                                    <span class="relative flex h-2 w-2 shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                    </span>
                                @endif
                                <x-ui.icon name="chevron-down" class="w-4 h-4 shrink-0 transition-transform duration-200 group-open:rotate-180 {{ $isGroupActive ? 'text-amber-400' : 'text-zinc-500' }}" />
                            </span>
                        </summary>
                        <div class="mt-1 ml-3.5 pl-3 border-l-2 border-zinc-800 space-y-1 py-1">
                            @foreach($menu['children'] as $child)
                                @php
                                    $matchUrls = array_merge([$child['href']], $child['active_urls'] ?? []);
                                    $childActive = in_array(request()->fullUrl(), $matchUrls, true) || in_array(request()->url(), $matchUrls, true);
                                @endphp
                                <a
                                    href="{{ $child['href'] }}"
                                    class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl text-xs transition {{ $childActive ? 'bg-amber-500 text-zinc-950 font-black shadow-xs' : 'text-zinc-400 hover:text-white hover:bg-white/5 font-semibold' }}"
                                >
                                    <span class="truncate">{{ $child['label'] }}</span>
                                    @if(!empty($child['badge_type']))
                                        <livewire:cashier-nav-badge :type="$child['badge_type']" context="sidebar" :key="'badge-' . $child['badge_type'] . '-sidebar'" />
                                    @elseif(!empty($child['badge_dot']))
                                        <span class="relative flex h-2.5 w-2.5 shrink-0" data-booking-indicator title="Pemberitahuan">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    @php
                        $active = false;
                        if (!empty($menu['active_routes'])) {
                            foreach ($menu['active_routes'] as $pattern) {
                                if (request()->routeIs($pattern)) {
                                    $active = true;
                                    break;
                                }
                            }
                        }
                        if (!$active && isset($menu['href'])) {
                            $active = request()->url() === $menu['href'];
                        }
                    @endphp
                    <a
                        href="{{ $menu['href'] }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-semibold transition {{ $active ? 'bg-amber-500 text-zinc-950' : 'hover:bg-white/10 hover:text-white' }}"
                    >
                        <x-ui.icon :name="$menu['icon']" class="w-5 h-5 shrink-0" />
                        <span class="truncate flex-1">{{ $menu['label'] }}</span>
                        @if(!empty($menu['attendance_status']))
                            <span class="flex items-center gap-1.5 shrink-0" title="{{ $menu['attendance_status'] === 'checked_in' ? 'Sudah Absen Masuk' : 'Belum Absen Masuk' }}">
                                @if($menu['attendance_status'] === 'checked_in')
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-500/20"></span>
                                @else
                                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 ring-2 ring-yellow-400/20 animate-pulse"></span>
                                @endif
                            </span>
                        @elseif(!empty($menu['badge_type']))
                            <livewire:cashier-nav-badge :type="$menu['badge_type']" context="sidebar" :key="'badge-' . $menu['badge_type'] . '-top-sidebar'" />
                        @elseif(!empty($menu['badge_count']))
                            <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-black {{ $active ? 'bg-zinc-950 text-white' : 'bg-rose-500 text-white shadow-xs animate-pulse' }}" title="{{ $menu['badge_count'] }} Menunggu Konfirmasi">
                                {{ $menu['badge_count'] }}
                            </span>
                        @endif
                    </a>
                @endif
            @endforeach

            @if($tenant)
                @if($effectiveRole === 'owner')
                    @php
                        $ownerTvBranches = $currentTenantCtx->activeAllowedBranches();
                    @endphp
                    @if($ownerTvBranches->count() > 0)
                        <div class="pt-4 pb-1 px-3 text-[10px] font-black uppercase tracking-wider text-zinc-500 select-none">
                            Layar Display
                        </div>
                    @endif
                    @if($ownerTvBranches->count() === 1)
                        @php $singleBranch = $ownerTvBranches->first(); @endphp
                        <a
                            href="{{ $tenant->tvDisplayUrl($singleBranch->code) }}"
                            target="_blank"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 hover:text-white text-zinc-300 transition"
                            title="Buka Layar TV Antrean Cabang {{ $singleBranch->name }}"
                        >
                            <x-ui.icon name="tv" class="w-5 h-5 shrink-0 text-amber-400" />
                            <span>Layar TV Antrean</span>
                        </a>
                    @elseif($ownerTvBranches->count() > 1)
                        <button
                            type="button"
                            onclick="document.getElementById('modal-owner-tv-branches').showModal()"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl hover:bg-white/10 hover:text-white text-zinc-300 transition text-left cursor-pointer group"
                            title="Pilih Cabang untuk Layar TV Antrean"
                        >
                            <div class="flex items-center gap-3">
                                <x-ui.icon name="tv" class="w-5 h-5 shrink-0 text-amber-400 group-hover:scale-105 transition" />
                                <span>Layar TV Antrean</span>
                            </div>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-zinc-800 text-zinc-400 group-hover:bg-amber-500/20 group-hover:text-amber-300 transition">
                                {{ $ownerTvBranches->count() }} Cabang
                            </span>
                        </button>
                    @endif
                @else
                    @php
                        $cashierBranch = $staffMembership?->branch;
                        $cashierBranchCode = $cashierBranch?->code ?: $currentTenantCtx->firstBranchCode();
                    @endphp
                    @if($cashierBranchCode)
                        <div class="pt-3 pb-1 px-3 text-[10px] font-black uppercase tracking-wider text-zinc-500 select-none">
                            Layar Display
                        </div>
                        <a
                            href="{{ $tenant->tvDisplayUrl($cashierBranchCode) }}"
                            target="_blank"
                            class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 hover:text-white text-zinc-300 transition group"
                            title="Buka Layar TV Antrean {{ $cashierBranch ? 'Cabang ' . $cashierBranch->name : '' }}"
                        >
                            <span class="flex items-center gap-3 min-w-0">
                                <x-ui.icon name="tv" class="w-5 h-5 shrink-0 text-amber-400" />
                                <span class="truncate">Layar TV Antrean</span>
                            </span>
                            @if($cashierBranch)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-zinc-800 text-zinc-400 shrink-0">
                                    {{ $cashierBranch->name }}
                                </span>
                            @else
                                <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-zinc-500 group-hover:text-amber-400 shrink-0" />
                            @endif
                        </a>
                    @endif
                @endif
            @endif
        </nav>

        <div class="mt-auto pt-4 border-t border-zinc-800 flex flex-col gap-3">
            @if($effectiveRole === 'owner' && $tenant)
                @php
                    $sidebarSub = $currentTenantCtx->subscription();
                    $isSubActive = $sidebarSub && in_array($sidebarSub->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Trialing, \App\Enums\SubscriptionStatus::PastDue], true);
                    $endsAt = $sidebarSub?->ends_at ?? $tenant?->trial_ends_at;
                    $diffDays = $endsAt ? (int) ceil(now()->floatDiffInDays($endsAt, false)) : null;
                    $isPastDue = $sidebarSub?->status === \App\Enums\SubscriptionStatus::PastDue;
                    $isNearExpiry = $diffDays !== null && $diffDays <= 7 && $diffDays >= 0;
                    $isExpired = $endsAt ? $endsAt->isPast() : false;
                    $isTenggang = ! $isSubscriptionLocked && ($isPastDue || $isNearExpiry || $isExpired);

                    if ($isSubscriptionLocked) {
                        $periodNotice = 'Habis';
                    } elseif ($diffDays !== null) {
                        if ($diffDays > 0) {
                            $periodNotice = 'Sisa ' . $diffDays . ' hari';
                        } elseif ($diffDays === 0) {
                            $periodNotice = 'Hari ini';
                        } else {
                            $periodNotice = 'Tenggang';
                        }
                    } else {
                        $periodNotice = 'Aktif';
                    }
                @endphp

                @if($isSubActive || $sidebarSub)
                    <!-- Status Langganan di Bagian Sidebar Bawah (Indikator Hijau/Kuning & Status Aktif/Tenggang) -->
                    <div class="p-2.5 rounded-xl bg-zinc-900 border {{ $isTenggang ? 'border-amber-500/40 bg-amber-500/5' : ($isSubscriptionLocked ? 'border-rose-500/40 bg-rose-500/5' : 'border-zinc-800/80') }} flex flex-col gap-2">
                        <!-- Baris 1: Paket & Status Badge -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($isSubscriptionLocked)
                                    <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0 animate-pulse" title="Masa Aktif Habis"></span>
                                @elseif($isTenggang)
                                    <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0 animate-pulse" title="Masa Tenggang"></span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" title="Langganan Aktif"></span>
                                @endif
                                <span class="text-xs font-bold text-white truncate">{{ $sidebarSub?->plan?->name ?? ($tenant?->plan()?->name ?? 'Starter') }}</span>
                            </div>

                            @if($isSubscriptionLocked)
                                <span class="text-[10px] font-extrabold uppercase text-rose-400 shrink-0">
                                    Terkunci
                                </span>
                            @elseif($isTenggang)
                                <span class="text-[10px] font-extrabold uppercase text-amber-400 shrink-0">
                                    Tenggang
                                </span>
                            @else
                                <span class="text-[10px] font-extrabold uppercase {{ $sidebarSub?->status->value === 'trialing' ? 'text-amber-400' : 'text-emerald-400' }} shrink-0">
                                    {{ $sidebarSub?->status->value === 'trialing' ? 'Trial' : 'Aktif' }}
                                </span>
                            @endif
                        </div>

                        <!-- Baris 2: Sisa Masa Aktif & Tombol Aksi -->
                        <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-zinc-800/70">
                            <span class="text-[11px] font-semibold truncate {{ $isTenggang ? 'text-amber-300' : ($isSubscriptionLocked ? 'text-rose-400' : 'text-zinc-400') }}" title="{{ $endsAt ? 'Sisa ' . ($diffDays ?? 0) . ' hari (s/d ' . $endsAt->format('d M Y') . ')' : $periodNotice }}">
                                {{ $periodNotice }}
                            </span>

                            @php
                                $sidebarPlanCode = strtolower((string) ($sidebarSub?->plan?->code ?? ($tenant?->plan()?->code ?? 'starter')));
                                $isBusinessPlan = $sidebarPlanCode === 'business';

                                if ($isTenggang || $isSubscriptionLocked) {
                                    $btnActionLabel = 'Perpanjang';
                                    $btnActionIcon = 'clock';
                                    $btnActionTitle = 'Perpanjang Langganan Sekarang';
                                } elseif ($isBusinessPlan) {
                                    $btnActionLabel = 'Kelola';
                                    $btnActionIcon = 'crown';
                                    $btnActionTitle = 'Kelola Langganan Business';
                                } else {
                                    $btnActionLabel = 'Upgrade';
                                    $btnActionIcon = 'crystal';
                                    $btnActionTitle = 'Upgrade Paket';
                                }
                            @endphp

                            <a
                                href="{{ route('owner.subscription') }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-zinc-950 font-extrabold text-[11px] shadow-xs transition shrink-0 cursor-pointer {{ $isTenggang || $isSubscriptionLocked ? 'bg-amber-400 hover:bg-amber-300 ring-2 ring-amber-400/20' : 'bg-amber-500 hover:bg-amber-400' }}"
                                title="{{ $btnActionTitle }}"
                            >
                                <x-ui.icon name="{{ $btnActionIcon }}" class="w-3 h-3 text-zinc-950 shrink-0" />
                                <span>{{ $btnActionLabel }}</span>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Belum Langganan: Tombol Berlangganan -->
                    <a
                        href="{{ route('owner.subscription') }}"
                        class="flex items-center justify-between w-full px-3 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 font-extrabold text-xs shadow-sm transition group cursor-pointer"
                    >
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="sparkles" class="w-4 h-4 text-zinc-950" />
                            <span>Berlangganan</span>
                        </div>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 text-zinc-950 group-hover:translate-x-0.5 transition-transform" />
                    </a>
                @endif
            @endif

            @if($effectiveRole === 'superadmin')
                <!-- Status Mode Superadmin di Bagian Bawah Sidebar -->
                <div class="p-2.5 rounded-xl bg-zinc-900 border border-zinc-800/80 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" title="Core Engine Aktif"></span>
                        <span class="text-xs font-bold text-white truncate">{{ \App\Support\Platform\PlatformConfig::name() }} Core</span>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-md border border-amber-400/20 shrink-0">
                        SaaS Mode
                    </span>
                </div>
            @endif

            <div class="flex items-center justify-between gap-2">
                @if($isCashierOrAdmin)
                    <a href="{{ route('cashier.profile') }}" class="min-w-0 flex-1 group hover:opacity-95 transition" title="Lihat Profil Kasir">
                        <p class="text-xs font-semibold text-white truncate group-hover:text-amber-400 flex items-center gap-1">
                            <span>{{ auth()->user()?->name }}</span>
                            <x-ui.icon name="chevron-right" class="w-3 h-3 text-zinc-500 group-hover:text-amber-400 group-hover:translate-x-0.5 transition" />
                        </p>
                        <p class="text-xs text-zinc-500 truncate">{{ auth()->user()?->email }}</p>
                    </a>
                @else
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-white truncate">{{ auth()->user()?->name }}</p>
                        <p class="text-xs text-zinc-500 truncate">{{ auth()->user()?->email }}</p>
                    </div>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-zinc-800 text-zinc-300 hover:bg-rose-600 hover:text-white transition cursor-pointer">
                        <x-ui.icon name="log-out" class="w-3.5 h-3.5" />
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div id="main-content-wrapper" class="flex-1 flex flex-col min-w-0 pb-20 md:pb-0 lg:pl-64">
        <!-- Topbar -->
        <header class="bg-zinc-950 text-white px-4 py-3 flex items-center justify-between md:bg-white md:text-zinc-900 md:border-b md:border-zinc-200 md:px-6 md:py-3.5 relative z-[60] md:z-[45]">
            <!-- Kotak Button Buka / Tutup Sidebar (Khusus Tablet, Tepat di Antara Sidebar dan Header) -->
            <button
                type="button"
                id="tablet-sidebar-toggle"
                onclick="toggleSidebar()"
                class="hidden md:flex lg:hidden items-center justify-center w-8 h-8 rounded-lg border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 shadow-md cursor-pointer z-[60] shrink-0"
                title="Buka / Tutup Sidebar"
                aria-label="Buka / Tutup Sidebar"
            >
                <span class="sidebar-toggle-icon">
                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-zinc-700" />
                </span>
            </button>

            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0 md:max-lg:pl-9">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-base sm:text-lg md:text-xl font-extrabold leading-tight truncate">{{ $title }}</h1>
                    </div>
                    @if($subtitle)
                        <p class="text-xs text-zinc-400 md:text-zinc-500 truncate">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline-flex text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-500/15 text-amber-600">
                    {{ now()->translatedFormat('l, d M Y') }}
                </span>
            </div>
        </header>

        <main class="flex-1 p-3.5 sm:p-5 md:p-6 {{ ($fullWidth || request()->routeIs('cashier.dashboard')) ? 'w-full max-w-none' : 'max-w-7xl w-full mx-auto' }}">
            @if(\App\Support\Platform\PlatformConfig::isMaintenance())
                <div class="mb-5 p-4 rounded-2xl bg-amber-500/15 border border-amber-500/40 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-amber-500 text-zinc-950 flex items-center justify-center shrink-0 shadow-sm font-black">
                            <x-ui.icon name="alert-triangle" class="w-5 h-5" />
                        </span>
                        <div>
                            <p class="font-black text-xs sm:text-sm text-zinc-900 flex items-center gap-2">
                                <span>Mode Pemeliharaan Platform Sedang Aktif</span>
                                @if($effectiveRole === 'superadmin')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900 border border-amber-300">Mode Superadmin (Akses Penuh)</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">Akses Dibatasi</span>
                                @endif
                            </p>
                            <p class="text-xs text-zinc-600 mt-0.5">
                                {{ \App\Support\Platform\PlatformConfig::announcementBanner() ?? 'Platform sedang dalam pemeliharaan berkala untuk peningkatan infrastruktur. Portal login publik & kasir dibatasi.' }}
                            </p>
                        </div>
                    </div>
                    @if($effectiveRole === 'superadmin')
                        <a href="{{ route('platform.settings') }}" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shrink-0 self-start sm:self-auto transition shadow-xs">
                            Kelola Pengaturan
                        </a>
                    @endif
                </div>
            @endif

            @if($isSubscriptionLocked && !request()->routeIs('owner.subscription*'))
                <div class="mb-5 p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-rose-600 via-rose-700 to-amber-600 text-white shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-rose-500">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0 shadow-inner">
                            <x-ui.icon name="lock" class="w-5 h-5 text-white" />
                        </span>
                        <div>
                            <p class="font-black text-sm sm:text-base">Masa Aktif Paket Langganan Telah Berakhir</p>
                            <p class="text-xs text-rose-100 leading-relaxed">
                                Seluruh fitur operasional barbershop terkunci sementara. Selesaikan pembayaran perpanjangan agar sistem dapat diaktifkan kembali.
                            </p>
                        </div>
                    </div>
                    <a
                        href="{{ route('owner.subscription') }}"
                        class="px-4 py-2.5 rounded-xl bg-white text-zinc-950 hover:bg-zinc-100 font-black text-xs shrink-0 self-start sm:self-auto shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <span>Perpanjang Langganan</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            @endif

            <x-ui.flash-alert />

            {{ $slot }}
        </main>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="fixed bottom-0 inset-x-0 px-2.5 pb-2.5 md:hidden z-40">
        <div class="{{ count($mobileNavMenus) > 5 ? 'flex items-center overflow-x-auto no-scrollbar justify-start px-2 py-2 gap-1.5' : 'grid grid-flow-col auto-cols-fr items-center px-1.5 py-2' }} bg-zinc-950 text-zinc-400 rounded-2xl shadow-2xl border border-zinc-800">
            @foreach($mobileNavMenus as $menu)
                @php
                    $matchUrls = array_merge(
                        [strtok($menu['href'], '#')],
                        array_map(fn ($u) => strtok($u, '#'), $menu['active_urls'] ?? [])
                    );
                    $active = in_array(request()->url(), $matchUrls, true);
                    if (!empty($menu['active_routes'])) {
                        foreach ($menu['active_routes'] as $rPattern) {
                            if (request()->routeIs($rPattern)) {
                                $active = true;
                                break;
                            }
                        }
                    }
                    $mobileHasDot = ! empty($menu['badge_dot']);
                    if (! empty($menu['children'])) {
                        $childUrls = [];
                        foreach ($menu['children'] as $c) {
                            $childUrls[] = strtok($c['href'], '#');
                            foreach ($c['active_urls'] ?? [] as $extraUrl) {
                                $childUrls[] = strtok($extraUrl, '#');
                            }
                            if (! empty($c['badge_dot'])) {
                                $mobileHasDot = true;
                            }
                        }
                        if (! $active) {
                            $active = in_array(request()->url(), $childUrls, true);
                        }
                    }
                @endphp
                @if(!empty($menu['is_drawer']))
                    <button
                        type="button"
                        onclick="toggleMobileNavDrawer()"
                        class="relative flex flex-col items-center justify-center gap-1 py-1 px-2.5 rounded-xl text-[11px] font-semibold leading-none transition cursor-pointer select-none {{ count($mobileNavMenus) > 5 ? 'shrink-0 min-w-[64px]' : '' }} {{ $active ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                    >
                        <span class="relative inline-flex">
                            <x-ui.icon :name="$menu['icon']" class="w-5 h-5 shrink-0" />
                            @if(!empty($menu['badge_count']))
                                <span class="absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full text-[9px] leading-none bg-rose-500 text-white font-black flex items-center justify-center num shadow-xs">
                                    {{ $menu['badge_count'] }}
                                </span>
                            @elseif(!empty($menu['badge_type']))
                                <livewire:cashier-nav-badge :type="$menu['badge_type']" context="mobile" :key="'badge-' . $menu['badge_type'] . '-btn-bottomnav'" />
                            @elseif(!empty($menu['is_parent_queue']))
                                <livewire:cashier-nav-badge type="parent_dot" context="mobile" :key="'badge-dot-btn-bottomnav'" />
                            @elseif($mobileHasDot)
                                <span class="absolute -top-0.5 -right-1 flex h-2 w-2" data-booking-indicator>
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                            @endif
                        </span>
                        <span class="truncate max-w-full text-center" title="{{ $menu['label'] }}">{{ $menu['short_label'] ?? $menu['label'] }}</span>
                    </button>
                @else
                    <a
                        href="{{ $menu['href'] }}"
                        class="relative flex flex-col items-center justify-center gap-1 py-1 px-2.5 rounded-xl text-[11px] font-semibold leading-none transition {{ count($mobileNavMenus) > 5 ? 'shrink-0 min-w-[64px]' : '' }} {{ $active ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                    >
                        <span class="relative inline-flex">
                            <x-ui.icon :name="$menu['icon']" class="w-5 h-5 shrink-0" />
                            @if(!empty($menu['attendance_status']))
                                <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5 shrink-0" title="{{ $menu['attendance_status'] === 'checked_in' ? 'Sudah Absen Masuk' : 'Belum Absen Masuk' }}">
                                    @if($menu['attendance_status'] === 'checked_in')
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 ring-2 ring-zinc-950"></span>
                                    @else
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-400 ring-2 ring-zinc-950"></span>
                                    @endif
                                </span>
                            @elseif(!empty($menu['badge_count']))
                                <span class="absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full text-[9px] leading-none bg-rose-500 text-white font-black flex items-center justify-center num shadow-xs">
                                    {{ $menu['badge_count'] }}
                                </span>
                            @elseif(!empty($menu['badge_type']))
                                <livewire:cashier-nav-badge :type="$menu['badge_type']" context="mobile" :key="'badge-' . $menu['badge_type'] . '-link-bottomnav'" />
                            @elseif(!empty($menu['is_parent_queue']))
                                <livewire:cashier-nav-badge type="parent_dot" context="mobile" :key="'badge-dot-link-bottomnav'" />
                            @elseif($mobileHasDot)
                                <span class="absolute -top-0.5 -right-1 flex h-2 w-2" data-booking-indicator>
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                            @endif
                        </span>
                        <span class="truncate max-w-full text-center" title="{{ $menu['label'] }}">{{ $menu['short_label'] ?? $menu['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    @if(in_array($effectiveRole, ['owner', 'superadmin'], true))
        <!-- Mobile Menu Drawer (Akses Lengkap Semua Fitur di Smartphone) -->
        <div id="mobile-nav-drawer" class="fixed inset-0 z-[70] pointer-events-none md:hidden transition-all duration-300">
            <!-- Backdrop Overlay -->
            <div
                id="mobile-drawer-backdrop"
                onclick="toggleMobileNavDrawer(false)"
                class="absolute inset-0 bg-black/70 backdrop-blur-xs opacity-0 transition-opacity duration-300 cursor-pointer"
            ></div>

            <!-- Bottom Sheet Panel -->
            <div
                id="mobile-drawer-sheet"
                class="absolute inset-x-0 bottom-0 max-h-[85vh] bg-zinc-950 text-zinc-200 rounded-t-3xl border-t border-zinc-800 shadow-2xl flex flex-col translate-y-full transition-transform duration-300 ease-out"
            >
                <!-- Drag Handle Bar -->
                <div class="py-2.5 flex justify-center cursor-pointer" onclick="toggleMobileNavDrawer(false)">
                    <div class="w-12 h-1 bg-zinc-700 rounded-full"></div>
                </div>

                <!-- Drawer Header -->
                <div class="px-5 pb-3 border-b border-zinc-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        @if($effectiveRole === 'superadmin')
                            <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 font-black text-xs shadow-sm">
                                <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <p class="text-white font-extrabold text-sm truncate">{{ \App\Support\Platform\PlatformConfig::name() }}</p>
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-400 text-zinc-950 tracking-wider">Superadmin</span>
                                </div>
                                <p class="text-[11px] text-zinc-400 font-semibold">Semua Modul Platform SaaS</p>
                            </div>
                        @else
                            @if($tenant?->logo_url)
                                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-8 h-8 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
                            @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                                <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ \App\Support\Platform\PlatformConfig::name() }}" class="w-8 h-8 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
                            @else
                                <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 font-black text-xs">
                                    <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-4 h-4" />
                                </div>
                            @endif
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <p class="text-white font-extrabold text-sm truncate">{{ $tenant?->name ?? \App\Support\Platform\PlatformConfig::name() }}</p>
                                    @if($tenant && $effectiveRole !== 'superadmin')
                                        <x-ui.plan-badge :code="$tenantPlanCode" />
                                    @endif
                                </div>
                                <p class="text-[11px] text-amber-400 font-semibold">Semua Fitur Barbershop</p>
                            </div>
                        @endif
                    </div>
                    <button
                        type="button"
                        onclick="toggleMobileNavDrawer(false)"
                        class="w-8 h-8 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white flex items-center justify-center transition cursor-pointer"
                        aria-label="Tutup Menu"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Drawer Body: Grid Semua Fitur Lengkap -->
                <div class="p-5 overflow-y-auto space-y-4 flex-1 overscroll-contain">
                    @if($effectiveRole === 'superadmin')
                        <!-- 1. Kategori: Monitoring & Analitik -->
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Monitoring &amp; Analitik</p>
                            <div class="grid grid-cols-2 gap-2.5">
                                <a
                                    href="{{ route('platform.dashboard') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.dashboard', 'platform.tenants.*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="zap" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Platform Overview</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Pusat kendali tenant</span>
                                    </div>
                                </a>

                                <a
                                    href="{{ route('platform.reports') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.reports*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="trending-up" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Laporan Platform</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Statistik &amp; ekspor</span>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 2. Kategori: Langganan & Keuangan -->
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Langganan &amp; Keuangan</p>
                            <div class="grid grid-cols-2 gap-2.5">
                                <a
                                    href="{{ route('platform.subscriptions') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.subscriptions*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="banknote" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="block truncate">Tagihan</span>
                                            @if($superadminPendingConfirmationsCount > 0)
                                                <span class="px-1.5 py-0.2 rounded-full text-[9px] bg-rose-500 text-white font-black num">
                                                    {{ $superadminPendingConfirmationsCount }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Konfirmasi bayar</span>
                                    </div>
                                </a>

                                <a
                                    href="{{ route('platform.plans') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.plans*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="coins" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Paket Langganan</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Kelola paket harga</span>
                                    </div>
                                </a>

                                <a
                                    href="{{ route('platform.transactions') }}"
                                    class="col-span-2 p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.transactions*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="receipt" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Monitoring Pendapatan</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Rekap &amp; riwayat pembayaran invoice masuk</span>
                                    </div>
                                </a>

                                <a
                                    href="{{ route('platform.payouts') }}"
                                    class="col-span-2 p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.payouts*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="wallet" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Pencairan Dana (Payouts)</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Pencairan saldo booking ke rekening owner</span>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 3. Kategori: Komunikasi & Promosi -->
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Komunikasi &amp; Promosi</p>
                            <div class="grid grid-cols-2 gap-2.5">
                                <a
                                    href="{{ route('platform.announcements') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.announcements*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="megaphone" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Pengumuman</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Siaran popup</span>
                                    </div>
                                </a>

                                <a
                                    href="{{ route('platform.promotions') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.promotions*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="tag" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Iklan Produk</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Slide &amp; galeri banner</span>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 4. Kategori: Sistem & Konfigurasi -->
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Sistem &amp; Konfigurasi</p>
                            <div>
                                <a
                                    href="{{ route('platform.settings') }}"
                                    class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('platform.settings*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                                >
                                    <span class="w-8 h-8 rounded-xl bg-zinc-500/10 text-zinc-300 flex items-center justify-center shrink-0">
                                        <x-ui.icon name="settings" class="w-5 h-5 shrink-0" />
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block truncate">Konfigurasi SaaS</span>
                                        <span class="text-[10px] text-zinc-500 font-medium block">Branding, rekening, &amp; sistem</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @else
                    <!-- Kategori: Tim, Karyawan & Payroll -->
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Manajemen Tim &amp; Payroll</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <a
                                href="{{ route('owner.staff') }}"
                                class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('owner.staff*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="users" class="w-5 h-5 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block truncate">Karyawan</span>
                                    <span class="text-[10px] text-zinc-500 font-medium block">Daftar staff</span>
                                </div>
                            </a>

                            <a
                                href="{{ route('owner.payroll') }}"
                                class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('owner.payroll*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="banknote" class="w-5 h-5 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block truncate">Gaji &amp; Komisi</span>
                                    <span class="text-[10px] text-zinc-500 font-medium block">Slip payroll</span>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Kategori: Outlet & Layanan -->
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Outlet &amp; Layanan</p>
                        <div class="grid grid-cols-3 gap-2">
                            <a
                                href="{{ route('owner.branches') }}"
                                class="p-2.5 rounded-2xl border transition flex flex-col items-center justify-center gap-1.5 text-center text-xs font-bold {{ request()->routeIs('owner.branches*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="store" class="w-5 h-5 shrink-0" />
                                </span>
                                <span class="truncate w-full text-[11px]">Cabang</span>
                            </a>

                            <a
                                href="{{ route('owner.services') }}"
                                class="p-2.5 rounded-2xl border transition flex flex-col items-center justify-center gap-1.5 text-center text-xs font-bold {{ request()->routeIs('owner.services*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="scissors" class="w-5 h-5 shrink-0" />
                                </span>
                                <span class="truncate w-full text-[11px]">Layanan</span>
                            </a>

                            <a
                                href="{{ route('owner.colors') }}"
                                class="relative p-2.5 rounded-2xl border transition flex flex-col items-center justify-center gap-1.5 text-center text-xs font-bold {{ request()->routeIs('owner.colors*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="sparkles" class="w-5 h-5 shrink-0" />
                                </span>
                                <span class="truncate w-full text-[11px]">Stok Warna</span>
                                @if($hasLowColorStockDot)
                                    <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                    <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-rose-500"></span>
                                @endif
                            </a>
                        </div>
                    </div>

                    <!-- Kategori: Customer CRM & Laporan Analitik -->
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Pelanggan &amp; Analitik</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <a
                                href="{{ route('owner.customers') }}"
                                class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('owner.customers*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="user-check" class="w-5 h-5 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block truncate">Database Customer</span>
                                    <span class="text-[10px] text-zinc-500 font-medium block">Riwayat pelanggan</span>
                                </div>
                            </a>

                            <a
                                href="{{ route('owner.reports') }}"
                                class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('owner.reports*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="file-text" class="w-5 h-5 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block truncate">Laporan Keuangan</span>
                                    <span class="text-[10px] text-zinc-500 font-medium block">Ekspor &amp; rekap omzet</span>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Kategori: Pengaturan & Display TV Antrean -->
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-2 px-1">Sistem &amp; Pengaturan</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <a
                                href="{{ route('owner.settings') }}"
                                class="p-3 rounded-2xl border transition flex items-center gap-2.5 text-xs font-bold {{ request()->routeIs('owner.settings*') ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-zinc-900/80 hover:bg-zinc-800/80 border-zinc-800 text-zinc-200' }}"
                            >
                                <span class="w-8 h-8 rounded-xl bg-zinc-500/10 text-zinc-300 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="settings" class="w-5 h-5 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block truncate">Setting Barbershop</span>
                                    <span class="text-[10px] text-zinc-500 font-medium block">Identitas &amp; outlet</span>
                                </div>
                            </a>

                            @if($tenant)
                                @php
                                    $ownerTvBranchesMobile = $currentTenantCtx->activeAllowedBranches();
                                @endphp
                                @if($ownerTvBranchesMobile->count() === 1)
                                    @php $singleBranchMobile = $ownerTvBranchesMobile->first(); @endphp
                                    <a
                                        href="{{ $tenant->tvDisplayUrl($singleBranchMobile->code) }}"
                                        target="_blank"
                                        class="p-3 rounded-2xl bg-zinc-900/80 hover:bg-zinc-800/80 border border-zinc-800 text-zinc-200 transition flex items-center gap-2.5 text-xs font-bold"
                                        title="Buka Layar TV Cabang {{ $singleBranchMobile->name }}"
                                    >
                                        <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                            <x-ui.icon name="tv" class="w-5 h-5 shrink-0" />
                                        </span>
                                        <div class="min-w-0">
                                            <span class="block truncate">Layar TV Antrean</span>
                                            <span class="text-[10px] text-zinc-500 font-medium block">{{ $singleBranchMobile->name }}</span>
                                        </div>
                                    </a>
                                @elseif($ownerTvBranchesMobile->count() > 1)
                                    <button
                                        type="button"
                                        onclick="if (typeof window.toggleMobileNavDrawer === 'function') { window.toggleMobileNavDrawer(false); } document.getElementById('modal-owner-tv-branches').showModal()"
                                        class="p-3 rounded-2xl bg-zinc-900/80 hover:bg-zinc-800/80 border border-zinc-800 text-zinc-200 transition flex items-center gap-2.5 text-xs font-bold text-left cursor-pointer"
                                    >
                                        <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                                            <x-ui.icon name="tv" class="w-5 h-5 shrink-0" />
                                        </span>
                                        <div class="min-w-0">
                                            <span class="block truncate">Layar TV Antrean</span>
                                            <span class="text-[10px] text-amber-400 font-medium block">Pilih cabang ({{ $ownerTvBranchesMobile->count() }})</span>
                                        </div>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Drawer Footer: Profile, Subscription & Logout -->
                <div class="p-4 bg-zinc-900/90 border-t border-zinc-800 flex flex-col gap-3">
                    @if($effectiveRole === 'owner' && $tenant)
                        @php
                            $drawerSub = $currentTenantCtx->subscription();
                            $isDrawerSubActive = $drawerSub && in_array($drawerSub->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Trialing, \App\Enums\SubscriptionStatus::PastDue], true);
                            $drawerEndsAt = $drawerSub?->ends_at ?? $tenant?->trial_ends_at;
                            $drawerDiffDays = $drawerEndsAt ? (int) ceil(now()->floatDiffInDays($drawerEndsAt, false)) : null;
                            $drawerIsPastDue = $drawerSub?->status === \App\Enums\SubscriptionStatus::PastDue;
                            $drawerIsNearExpiry = $drawerDiffDays !== null && $drawerDiffDays <= 7 && $drawerDiffDays >= 0;
                            $drawerIsExpired = $drawerEndsAt ? $drawerEndsAt->isPast() : false;
                            $drawerIsTenggang = ! $isSubscriptionLocked && ($drawerIsPastDue || $drawerIsNearExpiry || $drawerIsExpired);

                            if ($isSubscriptionLocked) {
                                $drawerPeriodNotice = 'Habis';
                            } elseif ($drawerDiffDays !== null) {
                                if ($drawerDiffDays > 0) {
                                    $drawerPeriodNotice = 'Sisa ' . $drawerDiffDays . ' hari';
                                } elseif ($drawerDiffDays === 0) {
                                    $drawerPeriodNotice = 'Hari ini';
                                } else {
                                    $drawerPeriodNotice = 'Tenggang';
                                }
                            } else {
                                $drawerPeriodNotice = 'Aktif';
                            }
                        @endphp

                        @if($isDrawerSubActive || $drawerSub)
                            <!-- Status Langganan di Mobile Drawer Bawah (Indikator Hijau/Kuning & Status Aktif/Tenggang) -->
                            <div class="p-2.5 rounded-xl bg-zinc-950 border {{ $drawerIsTenggang ? 'border-amber-500/40 bg-amber-500/5' : ($isSubscriptionLocked ? 'border-rose-500/40 bg-rose-500/5' : 'border-zinc-800') }} flex flex-col gap-2">
                                <!-- Baris 1: Paket & Status Badge -->
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        @if($isSubscriptionLocked)
                                            <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0 animate-pulse" title="Masa Aktif Habis"></span>
                                        @elseif($drawerIsTenggang)
                                            <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0 animate-pulse" title="Masa Tenggang"></span>
                                        @else
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" title="Langganan Aktif"></span>
                                        @endif
                                        <span class="text-xs font-bold text-white truncate">{{ $drawerSub?->plan?->name ?? ($tenant?->plan()?->name ?? 'Starter') }}</span>
                                    </div>

                                    @if($isSubscriptionLocked)
                                        <span class="text-[10px] font-extrabold uppercase text-rose-400 shrink-0">
                                            Terkunci
                                        </span>
                                    @elseif($drawerIsTenggang)
                                        <span class="text-[10px] font-extrabold uppercase text-amber-400 shrink-0">
                                            Tenggang
                                        </span>
                                    @else
                                        <span class="text-[10px] font-extrabold uppercase {{ $drawerSub?->status->value === 'trialing' ? 'text-amber-400' : 'text-emerald-400' }} shrink-0">
                                            {{ $drawerSub?->status->value === 'trialing' ? 'Trial' : 'Aktif' }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Baris 2: Sisa Masa Aktif & Tombol Aksi -->
                                <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-zinc-800/70">
                                    <span class="text-[11px] font-semibold truncate {{ $drawerIsTenggang ? 'text-amber-300' : ($isSubscriptionLocked ? 'text-rose-400' : 'text-zinc-400') }}" title="{{ $drawerEndsAt ? 'Sisa ' . ($drawerDiffDays ?? 0) . ' hari (s/d ' . $drawerEndsAt->format('d M Y') . ')' : $drawerPeriodNotice }}">
                                        {{ $drawerPeriodNotice }}
                                    </span>

                                    @php
                                        $drawerPlanCode = strtolower((string) ($drawerSub?->plan?->code ?? ($tenant?->plan()?->code ?? 'starter')));
                                        $drawerIsBusiness = $drawerPlanCode === 'business';

                                        if ($drawerIsTenggang || $isSubscriptionLocked) {
                                            $drawerBtnLabel = 'Perpanjang';
                                            $drawerBtnIcon = 'clock';
                                            $drawerBtnTitle = 'Perpanjang Langganan Sekarang';
                                        } elseif ($drawerIsBusiness) {
                                            $drawerBtnLabel = 'Kelola';
                                            $drawerBtnIcon = 'crown';
                                            $drawerBtnTitle = 'Kelola Langganan Business';
                                        } else {
                                            $drawerBtnLabel = 'Upgrade';
                                            $drawerBtnIcon = 'crystal';
                                            $drawerBtnTitle = 'Upgrade Paket';
                                        }
                                    @endphp

                                    <a
                                        href="{{ route('owner.subscription') }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg {{ $drawerIsTenggang || $isSubscriptionLocked ? 'bg-amber-400 hover:bg-amber-300 text-zinc-950 font-black ring-2 ring-amber-400/20' : 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-extrabold' }} text-[11px] shadow-sm transition shrink-0"
                                        title="{{ $drawerBtnTitle }}"
                                    >
                                        <x-ui.icon name="{{ $drawerBtnIcon }}" class="w-3.5 h-3.5 text-zinc-950 shrink-0" />
                                        <span>{{ $drawerBtnLabel }}</span>
                                    </a>
                                </div>
                            </div>
                        @else
                            <!-- Belum Langganan: Tombol Berlangganan (Mobile) -->
                            <a
                                href="{{ route('owner.subscription') }}"
                                class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-zinc-950 font-extrabold text-xs shadow-sm transition"
                            >
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="sparkles" class="w-4 h-4 text-zinc-950" />
                                    <span>Berlangganan Sekarang</span>
                                </div>
                                <x-ui.icon name="arrow-right" class="w-4 h-4 text-zinc-950" />
                            </a>
                        @endif
                    @endif

                    <div class="flex items-center justify-between gap-3">
                        @if($isCashierOrAdmin)
                            <a href="{{ route('cashier.profile') }}" class="min-w-0 flex-1 group hover:opacity-95 transition" title="Lihat Profil Kasir">
                                <p class="text-xs font-bold text-white truncate group-hover:text-amber-400 flex items-center gap-1">
                                    <span>{{ auth()->user()?->name }}</span>
                                    <x-ui.icon name="chevron-right" class="w-3 h-3 text-zinc-500 group-hover:text-amber-400 group-hover:translate-x-0.5 transition" />
                                </p>
                                <p class="text-[11px] text-zinc-400 truncate">{{ auth()->user()?->email }}</p>
                            </a>
                        @else
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-white truncate">{{ auth()->user()?->name }}</p>
                                <p class="text-[11px] text-zinc-400 truncate">{{ auth()->user()?->email }}</p>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl bg-rose-500/15 text-rose-400 hover:bg-rose-600 hover:text-white transition cursor-pointer">
                                <x-ui.icon name="log-out" class="w-3.5 h-3.5" />
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        window.toggleMobileNavDrawer = function(forceOpen) {
            var drawer = document.getElementById('mobile-nav-drawer');
            var backdrop = document.getElementById('mobile-drawer-backdrop');
            var sheet = document.getElementById('mobile-drawer-sheet');
            if (!drawer || !backdrop || !sheet) return;

            var isOpen = !drawer.classList.contains('pointer-events-none');
            var willOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;

            if (willOpen) {
                drawer.classList.remove('pointer-events-none');
                drawer.classList.add('pointer-events-auto');
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                sheet.classList.remove('translate-y-full');
                sheet.classList.add('translate-y-0');
                document.body.classList.add('overflow-hidden');
            } else {
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
                sheet.classList.remove('translate-y-0');
                sheet.classList.add('translate-y-full');
                document.body.classList.remove('overflow-hidden');
                setTimeout(function() {
                    drawer.classList.remove('pointer-events-auto');
                    drawer.classList.add('pointer-events-none');
                }, 300);
            }
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (typeof window.toggleMobileNavDrawer === 'function') {
                    window.toggleMobileNavDrawer(false);
                }
            }
        });

        // =========================================================================
        // GLOBAL SMOOTH PAGINATION (Mencegah Halaman Merefresh Kembali ke Atas)
        // =========================================================================
        (function() {
            function getPaginationContainer(el) {
                if (!el) return null;
                return el.closest('[data-pagination-container]')
                    || el.closest('x-ui\\:card')
                    || el.closest('.card')
                    || el.closest('div.bg-white.rounded-3xl')
                    || el.closest('div.bg-white.rounded-2xl')
                    || (el.closest('nav[role="navigation"]') ? el.closest('nav[role="navigation"]').parentElement : null);
            }

            // 1. Intercept pagination click: AJAX swap agar tidak refresh ke atas
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a');
                if (!link) return;

                var href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:')) return;

                var nav = link.closest('nav[role="navigation"]') || link.closest('.pagination');
                var isPageLink = nav !== null || /[?&](?:page|reviews_page)=\d+/.test(href);
                if (!isPageLink) return;

                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) {
                    return;
                }

                var container = getPaginationContainer(link);
                var currentScrollY = window.scrollY;

                if (!container) {
                    sessionStorage.setItem('pagination_scroll_y', currentScrollY.toString());
                    sessionStorage.setItem('pagination_scroll_url', window.location.pathname);
                    return;
                }

                e.preventDefault();

                var containerKey = container.getAttribute('data-pagination-container') || container.id || null;

                container.style.transition = 'opacity 0.15s ease';
                container.style.opacity = '0.35';
                container.style.pointerEvents = 'none';

                fetch(href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    if (!res.ok) throw new Error('Status ' + res.status);
                    return res.text();
                })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    var newContainer = null;
                    if (containerKey) {
                        newContainer = doc.querySelector('[data-pagination-container="' + containerKey + '"]')
                            || doc.getElementById(containerKey);
                    }

                    if (!newContainer) {
                        var newNav = doc.querySelector('nav[role="navigation"]');
                        if (newNav) {
                            newContainer = getPaginationContainer(newNav);
                        }
                    }

                    if (newContainer && container.parentNode) {
                        container.parentNode.replaceChild(newContainer, container);
                        window.history.pushState({ paginationHref: href }, '', href);
                        // Kunci scroll tetap pada posisi saat ini tanpa melompat ke atas
                        window.scrollTo({ top: currentScrollY, behavior: 'instant' });
                    } else {
                        sessionStorage.setItem('pagination_scroll_y', currentScrollY.toString());
                        sessionStorage.setItem('pagination_scroll_url', window.location.pathname);
                        window.location.href = href;
                    }
                })
                .catch(function(err) {
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                    sessionStorage.setItem('pagination_scroll_y', currentScrollY.toString());
                    sessionStorage.setItem('pagination_scroll_url', window.location.pathname);
                    window.location.href = href;
                });
            }, true);

            // 2. Failsafe: Restorasi posisi scroll jika reload halaman biasa
            function restorePaginationScroll() {
                var savedY = sessionStorage.getItem('pagination_scroll_y');
                var savedUrl = sessionStorage.getItem('pagination_scroll_url');

                if (savedY !== null && savedUrl === window.location.pathname) {
                    sessionStorage.removeItem('pagination_scroll_y');
                    sessionStorage.removeItem('pagination_scroll_url');

                    var targetY = parseInt(savedY, 10);
                    if (!isNaN(targetY)) {
                        window.scrollTo({ top: targetY, behavior: 'instant' });
                        requestAnimationFrame(function() {
                            window.scrollTo({ top: targetY, behavior: 'instant' });
                        });
                        setTimeout(function() {
                            window.scrollTo({ top: targetY, behavior: 'instant' });
                        }, 50);
                    }
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restorePaginationScroll);
            } else {
                restorePaginationScroll();
            }

            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.paginationHref) {
                    window.location.reload();
                }
            });
        })();
    </script>

    @if($effectiveRole === 'owner' && $tenant)
        @php
            $ownerTvBranchesList = $currentTenantCtx->activeAllowedBranches();
            $isBusinessPlan = $tenant->hasFeature('white_label');
        @endphp
        @if($ownerTvBranchesList->count() > 1)
            <!-- Dialog Modal: Pilih Layar TV Antrean Cabang (Owner Multi-Cabang) -->
            <dialog id="modal-owner-tv-branches" class="rounded-3xl p-0 shadow-2xl backdrop:bg-zinc-950/70 backdrop:backdrop-blur-xs m-auto fixed inset-0 w-[calc(100%-2rem)] max-w-md overflow-hidden border border-zinc-200 bg-white">
                <div class="p-5 sm:p-6 space-y-4">
                    <div class="flex items-start justify-between gap-3 border-b border-zinc-100 pb-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 flex items-center justify-center shrink-0">
                                <x-ui.icon name="tv" class="w-5 h-5 text-amber-600" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-sm sm:text-base text-zinc-900">Pilih Layar TV Cabang</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Pilih outlet yang ingin Anda buka di Layar TV Antrean:</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onclick="document.getElementById('modal-owner-tv-branches').close()"
                            class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 hover:text-zinc-800 grid place-items-center transition shrink-0 cursor-pointer"
                            aria-label="Tutup"
                        >
                            <x-ui.icon name="x" class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        @forelse($ownerTvBranchesList as $b)
                            @php
                                $tvUrl = $tenant->tvDisplayUrl($b->code);
                            @endphp
                            <a
                                href="{{ $tvUrl }}"
                                target="_blank"
                                onclick="document.getElementById('modal-owner-tv-branches').close()"
                                class="group flex items-center justify-between p-3.5 rounded-2xl border border-zinc-200/80 bg-zinc-50/70 hover:bg-amber-50/40 hover:border-amber-300 transition shadow-2xs"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-white border border-zinc-200 flex items-center justify-center shrink-0 text-zinc-700 group-hover:text-amber-600 group-hover:border-amber-300 shadow-2xs transition">
                                        <x-ui.icon name="store" class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs sm:text-sm text-zinc-900 truncate group-hover:text-amber-700 transition">
                                                {{ $b->name }}
                                            </h4>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-zinc-200 text-zinc-700">
                                                {{ $b->code }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-zinc-500 truncate mt-0.5">
                                            {{ $b->address ?: 'Outlet aktif barbershop' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 pl-2">
                                    @if($isBusinessPlan)
                                        <span class="hidden sm:inline-block px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Bisnis
                                        </span>
                                    @endif
                                    <span class="w-7 h-7 rounded-lg bg-zinc-900 group-hover:bg-amber-500 text-white group-hover:text-zinc-950 flex items-center justify-center transition shadow-2xs">
                                        <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="text-center py-6 text-zinc-400">
                                <x-ui.icon name="store" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-xs font-semibold">Belum ada cabang aktif yang terdaftar.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="pt-2 border-t border-zinc-100 flex items-center justify-between text-xs text-zinc-400">
                        <span class="text-[11px]">Buka di tab baru (mendukung Smart TV)</span>
                        <button
                            type="button"
                            onclick="document.getElementById('modal-owner-tv-branches').close()"
                            class="px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-bold text-xs transition cursor-pointer"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </dialog>
            <script>
                (function() {
                    var modalTv = document.getElementById('modal-owner-tv-branches');
                    if (modalTv) {
                        modalTv.addEventListener('click', function(e) {
                            if (e.target === modalTv) modalTv.close();
                        });
                    }
                })();
            </script>
        @endif
    @endif

    <x-platform-announcement-popup :role="$effectiveRole" />

    @livewireScripts
</body>
</html>
