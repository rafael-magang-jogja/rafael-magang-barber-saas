@props([
    'active' => 'overview',
])

@php
    $tabs = [
        [
            'id' => 'overview',
            'label' => 'Platform Overview',
            'short_label' => 'Overview',
            'href' => route('platform.dashboard'),
            'icon' => 'zap',
        ],
        [
            'id' => 'plans',
            'label' => 'Paket Langganan',
            'short_label' => 'Paket',
            'href' => route('platform.plans'),
            'icon' => 'coins',
        ],
        [
            'id' => 'subscriptions',
            'label' => 'Tagihan & Konfirmasi',
            'short_label' => 'Tagihan SaaS',
            'href' => route('platform.subscriptions'),
            'icon' => 'banknote',
            'badge_dot' => \App\Models\SubscriptionInvoice::withoutGlobalScope('tenant')->where('status', 'pending')->exists(),
        ],
        [
            'id' => 'transactions',
            'label' => 'Monitoring Transaksi',
            'short_label' => 'Transaksi',
            'href' => route('platform.transactions'),
            'icon' => 'receipt',
        ],
        [
            'id' => 'complaints',
            'label' => 'Komplain & Ulasan',
            'short_label' => 'Komplain',
            'href' => route('platform.complaints'),
            'icon' => 'star',
        ],
        [
            'id' => 'reports',
            'label' => 'Laporan Platform',
            'short_label' => 'Laporan',
            'href' => route('platform.reports'),
            'icon' => 'trending-up',
        ],
        [
            'id' => 'settings',
            'label' => 'Konfigurasi SaaS',
            'short_label' => 'Pengaturan',
            'href' => route('platform.settings'),
            'icon' => 'settings',
        ],
    ];
@endphp

<!-- Sub-navigation Tabs Khusus Superadmin (Mobile: Grid 2 Kolom, Tablet/Desktop: Horizontal Scroll/Flex) -->
<div class="mb-5 sm:mb-6">
    <!-- Mode Mobile: Grid 2 Kolom Ringkas -->
    <div class="sm:hidden grid grid-cols-2 gap-1.5 p-1.5 bg-zinc-200/80 rounded-2xl w-full">
        @foreach($tabs as $tab)
            @php $isActive = ($active === $tab['id']); @endphp
            <a
                href="{{ $tab['href'] }}"
                class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-xl text-xs font-bold transition text-center relative {{ $isActive ? 'bg-zinc-950 text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}"
            >
                <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-400' }}" />
                <span class="truncate">{{ $tab['short_label'] }}</span>
                @if(!empty($tab['badge_dot']))
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping absolute top-1.5 right-1.5"></span>
                    <span class="w-2 h-2 rounded-full bg-rose-500 absolute top-1.5 right-1.5"></span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- Mode Tablet & Desktop: Flex Baris Elegan -->
    <div class="hidden sm:flex items-center gap-1.5 p-1.5 bg-zinc-100 rounded-2xl border border-zinc-200/80 overflow-x-auto w-full">
        @foreach($tabs as $tab)
            @php $isActive = ($active === $tab['id']); @endphp
            <a
                href="{{ $tab['href'] }}"
                class="inline-flex items-center gap-2 py-2 px-3.5 rounded-xl text-xs font-bold transition whitespace-nowrap shrink-0 relative {{ $isActive ? 'bg-white text-zinc-950 shadow-2xs border border-zinc-200/80 font-black' : 'text-zinc-500 hover:text-zinc-900 hover:bg-white/50' }}"
            >
                <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0 {{ $isActive ? 'text-amber-600' : 'text-zinc-400' }}" />
                <span>{{ $tab['label'] }}</span>
                @if(!empty($tab['badge_dot']))
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                @endif
            </a>
        @endforeach
    </div>
</div>
