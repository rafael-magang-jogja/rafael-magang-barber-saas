@props([
    'title' => 'Layar Antrean',
    'tenant' => null,
])

@php
    $activeFaviconUrl = $tenant ? $tenant->faviconUrl() : \App\Support\Platform\PlatformConfig::faviconUrl();
    $activeSvgFaviconUrl = $tenant ? $tenant->svgFaviconUrl() : (\App\Support\Platform\PlatformConfig::hasSvgFavicon() ? \App\Support\Platform\PlatformConfig::svgFaviconUrl() : null);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/x-icon" href="{{ $activeFaviconUrl }}">
    @if($activeSvgFaviconUrl)
        <link rel="icon" type="image/svg+xml" href="{{ $activeSvgFaviconUrl }}">
    @endif
    <x-ui.theme-styles :tenant="$tenant" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen lg:h-screen lg:overflow-hidden bg-zinc-950 text-white antialiased flex flex-col grain">
    {{ $slot }}
    @livewireScripts
</body>
</html>
