@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-amber-500 hover:bg-amber-400 text-zinc-950 font-bold shadow-md shadow-zinc-950/10',
        'secondary' => 'bg-zinc-900 hover:bg-zinc-800 text-white font-semibold shadow-md shadow-zinc-950/10',
        'ghost' => 'bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold',
        'outline' => 'border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-700 font-bold',
        'danger' => 'bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold border border-rose-200',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs rounded-xl min-h-[36px]',
        'md' => 'px-4 py-2.5 text-sm rounded-xl min-h-[44px]',
        'lg' => 'px-5 py-3.5 text-base rounded-2xl min-h-[48px]',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
