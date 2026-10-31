@props([
    'padding' => 'p-4 sm:p-5',
    'dark' => false,
])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl border ' . ($dark
        ? 'bg-zinc-900 text-white border-zinc-800'
        : 'bg-white text-zinc-900 border-zinc-200/80 shadow-sm') . ' ' . $padding,
]) }}>
    @isset($header)
        <div class="mb-3 flex items-center justify-between gap-2">
            {{ $header }}
        </div>
    @endisset

    {{ $slot }}

    @isset($footer)
        <div class="mt-4 pt-3 border-t border-zinc-100">
            {{ $footer }}
        </div>
    @endisset
</div>
