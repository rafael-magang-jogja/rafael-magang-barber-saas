@props([
    'code' => 'starter',
    'showLabel' => false,
    'size' => 'sm',
])

@php
    $planCode = strtolower((string) $code);
    $dimClass = match($size) {
        'xs' => 'w-3.5 h-4',
        'lg' => 'w-6 h-7',
        'md' => 'w-5 h-6',
        default => 'w-4 h-5',
    };
    $crownDimClass = match($size) {
        'xs' => 'w-4 h-4',
        'lg' => 'w-7 h-7',
        'md' => 'w-6 h-6',
        default => 'w-5 h-5',
    };
@endphp

@if($planCode === 'business')
    <!-- Business: Mahkota Emas (Gold Crown) -->
    <span
        class="inline-flex items-center gap-1.5 text-amber-400 select-none shrink-0"
        title="Paket Business (Mahkota Emas)"
    >
        <svg viewBox="0 0 24 24" class="{{ $crownDimClass }} shrink-0 drop-shadow-[0_2px_4px_rgba(245,158,11,0.5)]" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Body Belakang Crown -->
            <path d="M3 17L2 7L7.5 11.5L12 4L16.5 11.5L22 7L21 17H3Z" fill="#F59E0B" stroke="#78350F" stroke-width="1.2" stroke-linejoin="round"/>
            <!-- Facet Highlight Depan -->
            <path d="M7.5 11.5L12 4L16.5 11.5L12 17L7.5 11.5Z" fill="#FDE047" opacity="0.9"/>
            <path d="M12 4L16.5 11.5L12 14.5Z" fill="#FEF08A"/>
            <!-- Dudukan / Rim Bawah -->
            <path d="M3 17H21V19.5C21 19.8 20.8 20 20.5 20H3.5C3.2 20 3 19.8 3 19.5V17Z" fill="#D97706" stroke="#78350F" stroke-width="1.1"/>
            <line x1="3" y1="18.5" x2="21" y2="18.5" stroke="#FEF08A" stroke-width="0.8" opacity="0.7"/>
            <!-- Permata Ujung -->
            <circle cx="12" cy="4" r="1.4" fill="#FFFFFF" stroke="#D97706" stroke-width="0.6"/>
            <circle cx="2" cy="7" r="1.3" fill="#FFFFFF" stroke="#D97706" stroke-width="0.6"/>
            <circle cx="22" cy="7" r="1.3" fill="#FFFFFF" stroke="#D97706" stroke-width="0.6"/>
            <!-- Permata Tengah -->
            <circle cx="12" cy="12.5" r="1.4" fill="#FEF08A" stroke="#B45309" stroke-width="0.6"/>
        </svg>
        @if($showLabel)
            <span class="text-xs font-black text-amber-400 uppercase tracking-wider">Business</span>
        @endif
    </span>
@elseif($planCode === 'pro')
    <!-- Pro: 2 Crystal Nitro Perak (Silver Crystals) -->
    <span
        class="inline-flex items-center gap-0.5 select-none shrink-0"
        title="Paket Pro (2 Crystal Perak)"
    >
        @for($i = 0; $i < 2; $i++)
            <svg viewBox="0 0 24 24" class="{{ $dimClass }} shrink-0 drop-shadow-[0_2px_4px_rgba(226,232,240,0.5)]" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Facets 3D Silver Crystal -->
                <!-- Top Left Highlight -->
                <polygon points="12,1.5 12,6.5 8.5,9.5 5,6.5" fill="#FFFFFF" />
                <!-- Top Right Facet -->
                <polygon points="12,1.5 19,6.5 15.5,9.5 12,6.5" fill="#F1F5F9" />
                <!-- Mid Left Facet -->
                <polygon points="5,6.5 8.5,9.5 8.5,14.5 5,17.5" fill="#E2E8F0" />
                <!-- Center Table -->
                <polygon points="12,6.5 15.5,9.5 15.5,14.5 12,17.5 8.5,14.5 8.5,9.5" fill="#CBD5E1" />
                <!-- Mid Right Facet -->
                <polygon points="15.5,9.5 19,6.5 19,17.5 15.5,14.5" fill="#94A3B8" />
                <!-- Bottom Left Facet -->
                <polygon points="5,17.5 8.5,14.5 12,17.5 12,22.5" fill="#64748B" />
                <!-- Bottom Right Facet -->
                <polygon points="15.5,14.5 19,17.5 12,22.5 12,17.5" fill="#475569" />

                <!-- Crisp Edges & Wire Outline -->
                <polygon points="12,1.5 19,6.5 19,17.5 12,22.5 5,17.5 5,6.5" stroke="#1E293B" stroke-width="0.9" stroke-linejoin="round" />
                <line x1="12" y1="1.5" x2="12" y2="6.5" stroke="#1E293B" stroke-width="0.6" />
                <line x1="5" y1="6.5" x2="8.5" y2="9.5" stroke="#1E293B" stroke-width="0.6" />
                <line x1="19" y1="6.5" x2="15.5" y2="9.5" stroke="#1E293B" stroke-width="0.6" />
                <line x1="5" y1="17.5" x2="8.5" y2="14.5" stroke="#1E293B" stroke-width="0.6" />
                <line x1="19" y1="17.5" x2="15.5" y2="14.5" stroke="#1E293B" stroke-width="0.6" />
                <line x1="12" y1="22.5" x2="12" y2="17.5" stroke="#1E293B" stroke-width="0.6" />
                <polygon points="12,6.5 15.5,9.5 15.5,14.5 12,17.5 8.5,14.5 8.5,9.5" stroke="#1E293B" stroke-width="0.6" />
            </svg>
        @endfor
        @if($showLabel)
            <span class="text-xs font-black text-slate-300 uppercase tracking-wider ml-0.5">Pro</span>
        @endif
    </span>
@else
    <!-- Starter: 1 Crystal Nitro Bronze (Perunggu Cokelat Berkilau Terang) -->
    <span
        class="inline-flex items-center gap-1 select-none shrink-0"
        title="Paket Starter (1 Crystal Bronze Cokelat)"
    >
        <svg viewBox="0 0 24 24" class="{{ $dimClass }} shrink-0 drop-shadow-[0_2px_4px_rgba(234,88,12,0.4)]" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Facets 3D Bronze Gemstone (Polished Radiant Copper Brown) -->
            <!-- Top Left Highlight (Bright Warm Copper Glint) -->
            <polygon points="12,1.5 12,6.5 8.5,9.5 5,6.5" fill="#FED7AA" />
            <!-- Top Right Highlight (Radiant Copper Bronze) -->
            <polygon points="12,1.5 19,6.5 15.5,9.5 12,6.5" fill="#FB923C" />
            <!-- Mid Left Facet (Vibrant Warm Bronze) -->
            <polygon points="5,6.5 8.5,9.5 8.5,14.5 5,17.5" fill="#EA580C" />
            <!-- Center Table (Rich Polished Bronze-Copper Table) -->
            <polygon points="12,6.5 15.5,9.5 15.5,14.5 12,17.5 8.5,14.5 8.5,9.5" fill="#C2410C" />
            <!-- Mid Right Facet (Medium Bronze Brown) -->
            <polygon points="15.5,9.5 19,6.5 19,17.5 15.5,14.5" fill="#9A3412" />
            <!-- Bottom Left Facet (Warm Bronze Shade) -->
            <polygon points="5,17.5 8.5,14.5 12,17.5 12,22.5" fill="#7C2D12" />
            <!-- Bottom Right Facet (Shadow Bronze) -->
            <polygon points="15.5,14.5 19,17.5 12,22.5 12,17.5" fill="#571D0B" />

            <!-- Crisp Edges & Wire Outline (Rich Dark Bronze) -->
            <polygon points="12,1.5 19,6.5 19,17.5 12,22.5 5,17.5 5,6.5" stroke="#431407" stroke-width="0.9" stroke-linejoin="round" />
            <line x1="12" y1="1.5" x2="12" y2="6.5" stroke="#431407" stroke-width="0.6" />
            <line x1="5" y1="6.5" x2="8.5" y2="9.5" stroke="#431407" stroke-width="0.6" />
            <line x1="19" y1="6.5" x2="15.5" y2="9.5" stroke="#431407" stroke-width="0.6" />
            <line x1="5" y1="17.5" x2="8.5" y2="14.5" stroke="#431407" stroke-width="0.6" />
            <line x1="19" y1="17.5" x2="15.5" y2="14.5" stroke="#431407" stroke-width="0.6" />
            <line x1="12" y1="22.5" x2="12" y2="17.5" stroke="#431407" stroke-width="0.6" />
            <polygon points="12,6.5 15.5,9.5 15.5,14.5 12,17.5 8.5,14.5 8.5,9.5" stroke="#431407" stroke-width="0.6" />
        </svg>
        @if($showLabel)
            <span class="text-xs font-black text-amber-900 uppercase tracking-wider">Starter</span>
        @endif
    </span>
@endif
