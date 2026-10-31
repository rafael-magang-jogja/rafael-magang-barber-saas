@props([
    'title' => 'Belum ada data',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-zinc-200 bg-white p-8 text-center']) }}>
    <div class="mx-auto w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 grid place-items-center">
        <x-ui.icon name="scissors" class="w-6 h-6" />
    </div>
    <p class="mt-3 font-bold text-sm text-zinc-900">{{ $title }}</p>
    @if($description)
        <p class="mt-1 text-xs text-zinc-500 max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @if(! $slot->isEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
