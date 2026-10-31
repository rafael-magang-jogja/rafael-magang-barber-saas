@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'hint' => null,
])

@php
    $isRequired = $attributes->has('required') || ($label !== null && str_ends_with(trim((string) $label), '*'));
    $cleanLabel = $label !== null ? rtrim(trim((string) $label), " *") : null;
    $isPassword = ($type === 'password');
@endphp

<div>
    @if($cleanLabel)
        <label @if($name) for="{{ $name }}" @endif class="block text-xs font-semibold text-zinc-700 mb-1.5">
            {{ $cleanLabel }}@if($isRequired)<span class="text-rose-500 font-bold ml-0.5">*</span>@endif
        </label>
    @endif

    <div class="relative flex items-center">
        <input
            type="{{ $type }}"
            @if($name) id="{{ $name }}" name="{{ $name }}" @endif
            {{ $attributes->merge([
                'class' => 'w-full rounded-xl border border-zinc-200 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 min-h-[44px]' . ($isPassword ? ' pr-11' : ''),
            ]) }}
        />

        @if($isPassword)
            <button
                type="button"
                data-password-toggle
                tabindex="-1"
                aria-label="Tampilkan atau sembunyikan kata sandi"
                class="absolute right-2.5 p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer flex items-center justify-center"
            >
                <span data-icon-show class="block pointer-events-none">
                    <x-ui.icon name="eye" class="w-4 h-4 text-zinc-500" />
                </span>
                <span data-icon-hide class="hidden pointer-events-none">
                    <x-ui.icon name="eye-off" class="w-4 h-4 text-amber-600" />
                </span>
            </button>
        @endif
    </div>

    @if($hint)
        <p class="mt-1 text-xs text-zinc-400">{{ $hint }}</p>
    @endif

    @if($name)
        @error($name)
            <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
        @enderror
    @endif
</div>

@if($isPassword)
    @once
        <script>
            (function () {
                if (window.__barberOSPasswordToggleInitialized) return;
                window.__barberOSPasswordToggleInitialized = true;

                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-password-toggle]');
                    if (!btn) return;
                    if (e.__pwToggled) return;
                    e.__pwToggled = true;
                    e.preventDefault();
                    const container = btn.closest('.relative');
                    const input = container ? container.querySelector('input') : null;
                    if (!input) return;

                    const isCurrentlyPassword = input.type === 'password';
                    input.type = isCurrentlyPassword ? 'text' : 'password';

                    const iconShow = btn.querySelector('[data-icon-show]');
                    const iconHide = btn.querySelector('[data-icon-hide]');
                    if (iconShow && iconHide) {
                        iconShow.classList.toggle('hidden', isCurrentlyPassword);
                        iconHide.classList.toggle('hidden', !isCurrentlyPassword);
                    }
                });
            })();
        </script>
    @endonce
@endif
