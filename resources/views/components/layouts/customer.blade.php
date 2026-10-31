@props([
    'title' => 'Barbershop App',
    'subtitle' => null,
    'tenant' => null,
    'showNav' => true,
    'showHeader' => true,
])

<!DOCTYPE html>
<html lang="id" style="--tenant-brand: {{ $tenant?->brand_color ?? '#D97706' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $title }} — {{ $tenant?->name ?? config('app.name', 'BarberOS') }}</title>
    @php
        $activeFaviconUrl = $tenant ? $tenant->faviconUrl() : \App\Support\Platform\PlatformConfig::faviconUrl();
        $activeSvgFaviconUrl = $tenant ? $tenant->svgFaviconUrl() : (\App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null);
    @endphp
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
            <header class="bg-zinc-950 text-white px-4 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-md border-b border-zinc-800/80">
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
                        <p class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider truncate">
                            {{ $subtitle ?? ($tenant && $tenant->name !== $title ? $tenant->name : 'BarberOS Pelanggan') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @auth
                        <a
                            href="{{ route('customer.profile') }}"
                            class="w-8 h-8 rounded-xl bg-white/10 hover:bg-amber-500 hover:text-zinc-950 text-zinc-200 grid place-items-center text-xs font-extrabold transition"
                            title="Profil Saya ({{ auth()->user()->name }})"
                        >
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </a>
                    @else
                        <a
                            href="{{ $tenant ? route('tenant.login', $tenant->slug) : route('login') }}"
                            class="text-xs font-extrabold px-3 py-1.5 rounded-xl text-zinc-950 transition shadow-2xs hover:brightness-110"
                            style="background-color: {{ $tenant?->brand_color ?? '#F59E0B' }};"
                        >
                            Masuk
                        </a>
                    @endauth
                </div>
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
                        <a
                            href="{{ $tenant && !auth()->check() ? route('customer.portal', $tenant->slug) : route('customer.dashboard') }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.dashboard') || request()->routeIs('customer.portal') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="sparkles" class="w-4 h-4" />
                            <span>{{ $tenant && !auth()->check() ? 'Beranda' : 'Dashboard' }}</span>
                        </a>

                        <!-- 2. Booking -->
                        <a
                            href="{{ route('customer.booking') }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.booking') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="calendar" class="w-4 h-4" />
                            <span>Booking</span>
                        </a>

                        <!-- 3. Riwayat -->
                        <a
                            href="{{ auth()->check() ? route('customer.history') : ($tenant ? route('tenant.login', $tenant->slug) : route('login')) }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.history') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="file-text" class="w-4 h-4" />
                            <span>Riwayat</span>
                        </a>

                        <!-- 4. Profil / Masuk -->
                        <a
                            href="{{ auth()->check() ? route('customer.profile') : ($tenant ? route('tenant.login', $tenant->slug) : route('login')) }}"
                            class="flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-[10px] font-semibold leading-none transition {{ request()->routeIs('customer.profile') ? 'text-amber-400 bg-white/10 font-extrabold' : 'hover:text-zinc-200' }}"
                        >
                            <x-ui.icon name="users" class="w-4 h-4" />
                            <span>{{ auth()->check() ? 'Profil' : 'Masuk' }}</span>
                        </a>
                    </div>
                </div>
            </nav>
        @endif
    </div>

    @livewireScripts
</body>
</html>
