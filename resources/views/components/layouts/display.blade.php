@props([
    'title' => 'Layar Antrean',
])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <x-ui.theme-styles />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen lg:h-screen lg:overflow-hidden bg-zinc-950 text-white antialiased flex flex-col grain">
    {{ $slot }}
    @livewireScripts
</body>
</html>
