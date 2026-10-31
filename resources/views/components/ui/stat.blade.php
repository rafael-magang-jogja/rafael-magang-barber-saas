@props([
    'label',
    'value',
    'trend' => null,
    'highlight' => false,
])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl p-4 relative overflow-hidden ' . ($highlight
        ? 'bg-zinc-900 text-white grain'
        : 'bg-white text-zinc-900 border border-zinc-200/80 shadow-sm'),
]) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-medium {{ $highlight ? 'text-zinc-400' : 'text-zinc-500' }}">{{ $label }}</p>
        @isset($icon)
            <span class="w-8 h-8 rounded-lg grid place-items-center {{ $highlight ? 'bg-amber-500 text-zinc-950' : 'bg-zinc-100 text-zinc-700' }}">
                {{ $icon }}
            </span>
        @endisset
    </div>
    <p class="text-2xl font-extrabold num mt-2">{{ $value }}</p>
    @if($trend)
        <p class="text-xs mt-1 {{ $highlight ? 'text-emerald-400' : 'text-emerald-600' }} font-medium">{{ $trend }}</p>
    @endif
</div>
