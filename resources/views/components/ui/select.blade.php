@props([
    'label' => null,
    'name' => '',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'icon' => null,
    'size' => 'md',
    'direction' => 'down',
    'align' => 'left',
    'multiple' => false,
    'searchThreshold' => 5,
    'searchable' => null,
    'searchPlaceholder' => 'Cari pilihan...',
    'autoSubmit' => false,
])

@php
    $normalized = [];

    if ($placeholder !== null && ! $multiple) {
        $normalized[] = [
            'value' => '',
            'label' => (string) $placeholder,
            'sublabel' => null,
            'color' => null,
        ];
    }

    foreach ($options as $key => $option) {
        if (is_array($option)) {
            $normalized[] = [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
                'sublabel' => isset($option['sublabel']) ? (string) $option['sublabel'] : null,
                'color' => isset($option['color']) ? (string) $option['color'] : (isset($option['hex']) ? (string) $option['hex'] : null),
                'branch_id' => isset($option['branch_id']) ? (string) $option['branch_id'] : null,
            ];
        } else {
            $normalized[] = [
                'value' => (string) $key,
                'label' => (string) $option,
                'sublabel' => null,
                'color' => null,
                'branch_id' => null,
            ];
        }
    }

    $lookupName = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
    $oldVal = $lookupName !== '' ? old($lookupName) : null;

    if ($multiple) {
        if (is_array($oldVal)) {
            $selectedValues = array_values(array_map('strval', $oldVal));
        } elseif (is_array($value)) {
            $selectedValues = array_values(array_map('strval', $value));
        } elseif ($value !== null && $value !== '') {
            $selectedValues = [(string) $value];
        } else {
            $selectedValues = isset($normalized[0]['value']) ? [(string) $normalized[0]['value']] : [];
        }

        $selectedItems = collect($normalized)->whereIn('value', $selectedValues)->values();
        if ($selectedItems->isEmpty() && isset($normalized[0])) {
            $selectedItems = collect([$normalized[0]]);
            $selectedValues = [(string) $normalized[0]['value']];
        }

        $triggerLabel = $selectedItems->count() > 1
            ? $selectedItems->pluck('label')->join(', ')
            : ($selectedItems->first()['label'] ?? ($placeholder ?? 'Pilih...'));

        $triggerSublabel = $selectedItems->count() > 1
            ? $selectedItems->count() . ' dipilih'
            : null;
    } else {
        if (is_array($oldVal)) {
            $oldVal = $oldVal[0] ?? null;
        }

        $selectedValue = $oldVal !== null
            ? (string) $oldVal
            : ($value !== null ? (string) $value : (string) ($normalized[0]['value'] ?? ''));

        $selectedOption = collect($normalized)->firstWhere('value', $selectedValue)
            ?? ($normalized[0] ?? ['value' => '', 'label' => 'Pilih...', 'sublabel' => null]);

        $selectedValue = (string) $selectedOption['value'];
        $selectedValues = [$selectedValue];
        $triggerLabel = $selectedOption['label'];
        $triggerSublabel = null;
    }

    $showSearch = $searchable !== null
        ? (bool) $searchable
        : count($normalized) >= (int) $searchThreshold;

    $triggerSizeClasses = match ($size) {
        'sm' => 'min-h-[36px] px-2.5 py-1.5 text-xs rounded-xl',
        default => 'min-h-[44px] px-3.5 py-2.5 text-sm rounded-xl',
    };

    $panelPositionClasses = ($direction === 'up' ? 'bottom-full mb-1.5 ' : 'top-full mt-1.5 ')
        . ($align === 'right' ? 'right-0 left-auto min-w-[220px]' : 'left-0 right-0 min-w-[210px]');
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    data-ui-select
    data-multiple="{{ $multiple ? 'true' : 'false' }}"
    data-input-name="{{ $name }}"
    data-placeholder="{{ $placeholder ?? 'Pilih...' }}"
    data-auto-submit="{{ $autoSubmit ? 'true' : 'false' }}"
>
    @if($label)
        @php
            $isRequired = $attributes->has('required') || str_contains((string) $label, '*');
            $cleanLabel = trim(str_replace('*', '', (string) $label));
        @endphp
        <label class="block text-xs font-semibold text-zinc-700 mb-1.5">
            {{ $cleanLabel }}@if($isRequired)<span class="text-rose-500 font-bold ml-0.5">*</span>@endif
        </label>
    @endif

    <div data-select-inputs>
        @foreach($selectedValues as $val)
            <input
                type="hidden"
                name="{{ $name }}"
                value="{{ $val }}"
                data-select-input
            />
        @endforeach
    </div>

    <button
        type="button"
        data-select-trigger
        aria-haspopup="listbox"
        aria-expanded="false"
        class="w-full {{ $triggerSizeClasses }} border border-zinc-200 bg-white text-zinc-900 font-medium flex items-center justify-between gap-2 text-left transition hover:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-amber-500/25 focus:border-amber-500 cursor-pointer shadow-2xs"
    >
        <span class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
            @if(!empty($selectedOption['color']))
                <span
                    data-select-color-swatch
                    class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0 shadow-2xs"
                    @style(['background-color: ' . $selectedOption['color']])
                ></span>
            @else
                <span
                    data-select-color-swatch
                    class="hidden w-3.5 h-3.5 rounded-full border border-black/20 shrink-0 shadow-2xs"
                ></span>
            @endif
            @if($icon)
                <x-ui.icon
                    :name="$icon"
                    class="w-4 h-4 text-zinc-400 shrink-0 {{ !empty($selectedOption['color']) ? 'hidden' : '' }}"
                    data-select-trigger-icon
                />
            @endif
            <span class="truncate font-semibold text-zinc-900 block flex-1" data-select-label>{{ $triggerLabel }}</span>
            <span
                data-select-sublabel
                class="text-[11px] font-semibold px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-700 shrink-0 {{ empty($triggerSublabel) ? 'hidden' : '' }}"
            >{{ $triggerSublabel }}</span>
        </span>

        <span class="w-6 h-6 rounded-lg bg-zinc-100 text-zinc-500 grid place-items-center shrink-0 transition" data-select-chevron-box>
            <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200 {{ $direction === 'up' ? 'rotate-180' : '' }}" data-select-chevron />
        </span>
    </button>

    <!-- Dropdown Panel -->
    <div
        data-select-panel
        role="listbox"
        aria-multiselectable="{{ $multiple ? 'true' : 'false' }}"
        class="hidden absolute {{ $panelPositionClasses }} rounded-2xl bg-white border border-zinc-200/90 shadow-2xl z-50 overflow-hidden"
    >
        @if($showSearch)
            <div class="p-2 border-b border-zinc-100 bg-zinc-50/70">
                <div class="relative flex items-center">
                    <x-ui.icon name="search" class="w-3.5 h-3.5 text-zinc-400 absolute left-3 pointer-events-none" />
                    <input
                        type="text"
                        data-select-search
                        placeholder="{{ $searchPlaceholder }}"
                        autocomplete="off"
                        class="w-full rounded-xl border border-zinc-200 bg-white pl-8 pr-8 py-1.5 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500"
                    />
                    <button
                        type="button"
                        data-select-clear
                        class="hidden absolute right-2 w-5 h-5 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 grid place-items-center cursor-pointer"
                        title="Bersihkan pencarian"
                    >
                        <x-ui.icon name="x" class="w-3 h-3" />
                    </button>
                </div>
            </div>
        @endif

        @if($multiple)
            <div class="px-3 py-1.5 bg-amber-50/60 border-b border-amber-100/80 flex items-center justify-between text-[11px] text-amber-800 font-semibold">
                <span>Bisa pilih lebih dari 1 layanan</span>
                <span data-select-count>{{ count($selectedValues) }} dipilih</span>
            </div>
        @endif

        <div class="max-h-60 overflow-y-auto no-scrollbar [scrollbar-width:none] [&::-webkit-scrollbar]:hidden p-1.5 space-y-0.5" data-select-list>
            @foreach($normalized as $item)
                @php
                    $isSelected = in_array($item['value'], $selectedValues, true);
                    $searchText = mb_strtolower(trim($item['label'] . ' ' . ($item['sublabel'] ?? '')));
                @endphp
                <button
                    type="button"
                    role="option"
                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                    data-select-option
                    data-value="{{ $item['value'] }}"
                    data-label="{{ $item['label'] }}"
                    data-sublabel="{{ $item['sublabel'] ?? '' }}"
                    data-color="{{ $item['color'] ?? '' }}"
                    data-search="{{ $searchText }}"
                    data-branch-id="{{ $item['branch_id'] ?? '' }}"
                    class="w-full px-3 py-2 rounded-xl text-left text-xs flex items-center justify-between gap-2 transition cursor-pointer {{ $isSelected ? 'bg-amber-500/15 text-zinc-950 font-bold' : 'text-zinc-700 hover:bg-zinc-100 font-medium' }}"
                >
                    <span class="flex items-center gap-2.5 min-w-0 flex-1">
                        @if(!empty($item['color']))
                            <span
                                class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0 shadow-2xs"
                                @style(['background-color: ' . $item['color']])
                            ></span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $item['label'] }}</span>
                            @if(!empty($item['sublabel']))
                                <span class="block text-[11px] text-zinc-400 truncate font-normal">{{ $item['sublabel'] }}</span>
                            @endif
                        </span>
                    </span>

                    <span data-select-check class="text-amber-600 shrink-0 {{ $isSelected ? '' : 'hidden' }}">
                        <x-ui.icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>
            @endforeach
        </div>

        <div data-select-empty class="hidden px-3 py-5 text-center text-xs text-zinc-400">
            Pilihan tidak ditemukan
        </div>
    </div>
</div>
