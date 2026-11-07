@props([
    'title' => 'Barbershop App',
    'subtitle' => null,
    'tenant' => null,
    'showNav' => true,
    'showHeader' => true,
])

@php
    $tenant = $tenant ?? app(\App\Support\Tenancy\CurrentTenant::class)->get();
    $hasCustomTheme = $tenant && $tenant->hasFeature('brand_color');
    $hasCustomLogo = $tenant && $tenant->hasFeature('custom_logo');
    $isWhiteLabel = $tenant && $tenant->hasFeature('white_label');
    $planCode = $tenant?->planCode() ?? 'starter';
    $isPro = $planCode === 'pro';

    $activeThemeColor = $tenant ? ($tenant->brand_color ?? \App\Support\Platform\PlatformConfig::themeColor()) : \App\Support\Platform\PlatformConfig::themeColor();
    $activeFaviconUrl = $tenant ? $tenant->faviconUrl() : \App\Support\Platform\PlatformConfig::faviconUrl();
    $activeSvgFaviconUrl = $tenant ? $tenant->svgFaviconUrl() : (\App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null);

    $activeTicketId = null;
    $todayStr = now()->toDateString();
    if (auth()->check()) {
        $activeTicketId = \App\Models\QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->whereDate('date', $todayStr)
            ->whereIn('status', [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called, \App\Enums\QueueStatus::InService])
            ->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', auth()->id()))
            ->value('id');
    } elseif (request()->hasSession()) {
        $guestTicketIds = (array) request()->session()->get('guest_ticket_ids', []);
        if (! empty($guestTicketIds)) {
            $activeTicketId = \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->whereDate('date', $todayStr)
                ->whereIn('status', [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called, \App\Enums\QueueStatus::InService])
                ->whereIn('id', $guestTicketIds)
                ->latest('id')
                ->value('id');
        }
    }

    if ($activeTicketId === null && request()->routeIs('customer.tickets.*')) {
        $routeTicket = request()->route('ticket');
        $routeTicketId = is_object($routeTicket) ? $routeTicket->id : $routeTicket;
        if ($routeTicketId) {
            $activeTicketId = \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($routeTicketId)
                ->whereIn('status', [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called, \App\Enums\QueueStatus::InService])
                ->value('id');
        }
    }
@endphp

<!DOCTYPE html>
<html lang="id" @style(['--tenant-brand: ' . $activeThemeColor])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $title }} — {{ $tenant ? $tenant->name : config('app.name', 'BarberOS') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ $activeFaviconUrl }}">
    @if($activeSvgFaviconUrl)
        <link rel="icon" type="image/svg+xml" href="{{ $activeSvgFaviconUrl }}">
    @endif
    <x-ui.theme-styles :tenant="$tenant" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-900 antialiased flex justify-center selection:bg-amber-500 selection:text-zinc-950 font-sans">
    <!-- Frame Full Mobile Mode (Tampilan Mobile App Centered di Desktop, Full di HP) -->
    <div class="w-full max-w-md min-h-screen bg-zinc-100 flex flex-col relative shadow-2xl border-x border-zinc-900/40 pb-20">
        <!-- Header Mobile Topbar -->
        @if($showHeader)
            <header class="bg-zinc-950 text-white px-4 py-3.5 flex items-center justify-between sticky top-0 z-[60] shadow-md border-b border-zinc-800/80">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if($tenant?->logo_url)
                        <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="w-8 h-8 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
                    @elseif(\App\Support\Platform\PlatformConfig::logoType() === 'image' && \App\Support\Platform\PlatformConfig::logoImageUrl())
                        <img src="{{ \App\Support\Platform\PlatformConfig::logoImageUrl() }}" alt="{{ \App\Support\Platform\PlatformConfig::name() }}" class="w-8 h-8 rounded-xl object-cover shrink-0 border border-white/10 shadow-sm" />
                    @else
                        <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 shadow-sm font-black">
                            <x-ui.icon name="{{ \App\Support\Platform\PlatformConfig::logoIcon() }}" class="w-4 h-4" />
                        </div>
                    @endif
                    <div class="min-w-0 leading-tight">
                        <p class="font-extrabold text-sm truncate text-white">{{ $title }}</p>
                        @if($isWhiteLabel)
                            <p class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider truncate">
                                {{ $subtitle ?? $tenant->name }}
                            </p>
                        @elseif($tenant && $isPro && $subtitle !== 'BarberOS Pelanggan')
                            <p class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider truncate flex items-center gap-1">
                                <span>{{ $subtitle ?? $tenant->name }}</span>
                                <span class="opacity-60 text-[9px] font-normal normal-case">&bull; Powered by BarberOS</span>
                            </p>
                        @else
                            <p class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider truncate">
                                {{ $subtitle ?? $tenant?->name ?? 'BarberOS Pelanggan' }}
                            </p>
                        @endif
                    </div>
                </div>

                @auth
                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            href="{{ route('customer.profile') }}"
                            class="w-8 h-8 rounded-xl bg-white/10 hover:bg-amber-500 hover:text-zinc-950 text-zinc-200 grid place-items-center text-xs font-extrabold transition"
                            title="Profil Saya ({{ auth()->user()->name }})"
                        >
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </a>
                    </div>
                @endauth
            </header>
        @endif

        <!-- Main Body Content -->
        <main class="flex-1 p-4 space-y-4">
            <x-ui.flash-alert />

            {{ $slot }}
        </main>

        <!-- Mobile Bottom Navigation Bar (Docked at Bottom of Mobile Viewport) -->
        @if($showNav)
            <nav class="fixed bottom-0 inset-x-0 z-40 flex justify-center pointer-events-none px-3 pb-3">
                <div class="w-full max-w-md pointer-events-auto">
                    <div class="grid grid-cols-4 items-center bg-zinc-950/95 backdrop-blur-md text-zinc-400 rounded-2xl px-1.5 py-2 shadow-2xl border border-zinc-800">
                        <!-- 1. Dashboard / Beranda -->
                        @if(auth()->check())
                            <a
                                href="{{ route('customer.dashboard') }}"
                                class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.dashboard') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                            >
                                <x-ui.icon name="sparkles" class="w-5 h-5 shrink-0" />
                                <span>Dashboard</span>
                            </a>
                        @else
                            <a
                                href="{{ $tenant ? $tenant->customerPortalUrl() : route('customer.dashboard') }}"
                                class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ ((request()->routeIs('customer.portal') || ($tenant && request()->routeIs('home'))) && request('tab') !== 'booking') || request()->routeIs('customer.dashboard') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                            >
                                <x-ui.icon name="sparkles" class="w-5 h-5 shrink-0" />
                                <span>{{ $tenant ? 'Beranda' : 'Dashboard' }}</span>
                            </a>
                        @endif

                        <!-- 2. Booking -->
                        <a
                            href="{{ $tenant ? $tenant->customerPortalUrl(null, ['tab' => 'booking']) : route('customer.booking') }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.booking') || ((request()->routeIs('customer.portal') || ($tenant && request()->routeIs('home'))) && request('tab') === 'booking') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="calendar" class="w-5 h-5 shrink-0" />
                            <span>Booking</span>
                        </a>

                        <!-- 3. Tiket Aktif / Riwayat -->
                        @if($activeTicketId)
                            <a
                                href="{{ route('customer.tickets.show', $activeTicketId) }}"
                                class="relative flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.tickets.*') ? 'text-amber-400 bg-white/10 font-extrabold' : 'text-amber-400 hover:text-amber-300 font-bold' }}"
                            >
                                <span class="relative">
                                    <x-ui.icon name="ticket" class="w-5 h-5 shrink-0" />
                                    <span class="absolute -top-1 -right-1 flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </span>
                                </span>
                                <span>Tiket Aktif</span>
                            </a>
                        @else
                            <a
                                href="{{ auth()->check() ? route('customer.history') : ($tenant ? $tenant->loginUrl() : route('login')) }}"
                                class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.history') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                            >
                                <x-ui.icon name="file-text" class="w-5 h-5 shrink-0" />
                                <span>Riwayat</span>
                            </a>
                        @endif

                        <!-- 4. Profil / Masuk -->
                        <a
                            href="{{ auth()->check() ? route('customer.profile') : ($tenant ? $tenant->loginUrl() : route('login')) }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.profile') || request()->routeIs('tenant.login') || request()->routeIs('login') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="users" class="w-5 h-5 shrink-0" />
                            <span>{{ auth()->check() ? 'Profil' : 'Masuk' }}</span>
                        </a>
                    </div>
                </div>
            </nav>
        @endif
    </div>

    @auth
        <livewire:platform-announcement-popup role="customer" />
    @endauth

    @livewireScripts
</body>
</html>
