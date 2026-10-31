@props([
    'title' => 'Staff Barber',
    'barberMembership' => null,
])

@php
    $tenant = $barberMembership?->tenant;
    $branch = $barberMembership?->branch;
    $workStatus = $barberMembership?->work_status?->value ?? 'active';
@endphp

<!DOCTYPE html>
<html lang="id" style="--tenant-brand: {{ $tenant?->brand_color ?? '#D97706' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $title }} — Staff BarberOS</title>
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
        <!-- Header Mobile Topbar Barber -->
        <header class="bg-zinc-950 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-30 shadow-md border-b border-zinc-800/80">
            <a href="{{ route('barber.profile') }}" class="flex items-center gap-2.5 min-w-0 group hover:opacity-90 transition">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-zinc-950 grid place-items-center shrink-0 shadow-sm font-black text-xs group-hover:scale-105 transition">
                    {{ strtoupper(substr($barberMembership?->display_name ?? 'B', 0, 1)) }}
                </div>
                <div class="min-w-0 leading-tight">
                    <p class="font-extrabold text-sm truncate text-white group-hover:text-amber-400 transition">
                        {{ $barberMembership?->display_name ?? auth()->user()?->name }}
                    </p>
                    <p class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider truncate">
                        {{ $tenant?->name ?? 'BarberOS' }} · {{ $branch?->name ?? 'Staff Pencukur' }}
                    </p>
                </div>
            </a>

            <div class="flex items-center gap-2 shrink-0">
                <!-- Status Kerja Badge Ringkas (Indikator Saja) -->
                @php
                    $statusBadgeClass = match($workStatus) {
                        'active' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40',
                        'break' => 'bg-amber-500/20 text-amber-400 border-amber-500/40',
                        default => 'bg-zinc-700 text-zinc-300 border-zinc-600',
                    };
                    $statusDotClass = match($workStatus) {
                        'active' => 'bg-emerald-400',
                        'break' => 'bg-amber-400',
                        default => 'bg-zinc-400',
                    };
                    $statusLabel = match($workStatus) {
                        'active' => 'Ready',
                        'break' => 'Istirahat',
                        default => 'Off',
                    };
                @endphp
                <a
                    href="{{ route('barber.attendance') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-[11px] font-extrabold select-none transition hover:opacity-80 {{ $statusBadgeClass }}"
                    title="Ubah status kerja di menu Absen & Jadwal"
                >
                    <span class="w-2 h-2 rounded-full {{ $statusDotClass }} shrink-0"></span>
                    <span>{{ $statusLabel }}</span>
                </a>

                <!-- Link Cepat ke Profil Staff -->
                <a
                    href="{{ route('barber.profile') }}"
                    class="w-8 h-8 rounded-xl {{ request()->routeIs('barber.profile*') ? 'bg-amber-500 text-zinc-950 font-bold' : 'bg-white/10 hover:bg-white/20 text-zinc-300' }} grid place-items-center transition cursor-pointer select-none"
                    title="Buka Profil Staff"
                >
                    <x-ui.icon name="user" class="w-4 h-4" />
                </a>
            </div>
        </header>

        <!-- Main Body Content Mobile -->
        <main class="flex-1 p-4 space-y-4">
            {{ $slot }}
        </main>

        <!-- Bottom Navigation Mobile Barber (4 Menu: Antrean, Penghasilan, Absen & Jadwal, Profil Staff) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md px-2.5 pb-2.5 z-40">
            <div class="grid grid-cols-4 items-center px-1 py-1.5 bg-zinc-950 text-zinc-400 rounded-2xl shadow-2xl border border-zinc-800">
                <a
                    href="{{ route('barber.dashboard') }}"
                    class="relative flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-[10px] font-semibold leading-none transition select-none {{ request()->routeIs('barber.dashboard') ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                >
                    <span class="relative inline-flex">
                        <x-ui.icon name="scissors" class="w-4 h-4 {{ request()->routeIs('barber.dashboard') ? 'text-amber-400' : 'text-zinc-400' }}" />
                    </span>
                    <span class="truncate max-w-full text-center">Antrean</span>
                </a>

                <a
                    href="{{ route('barber.earnings') }}"
                    class="relative flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-[10px] font-semibold leading-none transition select-none {{ request()->routeIs('barber.earnings*') ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                >
                    <span class="relative inline-flex">
                        <x-ui.icon name="wallet" class="w-4 h-4 {{ request()->routeIs('barber.earnings*') ? 'text-amber-400' : 'text-zinc-400' }}" />
                    </span>
                    <span class="truncate max-w-full text-center">Penghasilan</span>
                </a>

                <a
                    href="{{ route('barber.attendance') }}"
                    class="relative flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-[10px] font-semibold leading-none transition select-none {{ request()->routeIs('barber.attendance') ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                >
                    <span class="relative inline-flex">
                        <x-ui.icon name="clock" class="w-4 h-4 {{ request()->routeIs('barber.attendance') ? 'text-amber-400' : 'text-zinc-400' }}" />
                    </span>
                    <span class="truncate max-w-full text-center">Absen</span>
                </a>

                <a
                    href="{{ route('barber.profile') }}"
                    class="relative flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-[10px] font-semibold leading-none transition select-none {{ request()->routeIs('barber.profile*') ? 'text-amber-400 bg-white/5 font-bold' : 'hover:text-zinc-200' }}"
                >
                    <span class="relative inline-flex">
                        <x-ui.icon name="user" class="w-4 h-4 {{ request()->routeIs('barber.profile*') ? 'text-amber-400' : 'text-zinc-400' }}" />
                    </span>
                    <span class="truncate max-w-full text-center">Profil</span>
                </a>
            </div>
        </nav>
    </div>
    @livewireScripts
</body>
</html>
