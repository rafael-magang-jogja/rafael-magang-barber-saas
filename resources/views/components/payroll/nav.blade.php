@props(['active' => 'payroll'])

@php
    $tabs = [
        [
            'id' => 'payroll',
            'label' => 'Rekap & Slip Payroll',
            'short_label' => 'Rekap & Slip',
            'href' => route('owner.payroll'),
            'icon' => 'file-text',
        ],
        [
            'id' => 'staff',
            'label' => 'Gaji per Karyawan & Kenaikan',
            'short_label' => 'Gaji Staf',
            'href' => route('owner.payroll.staff'),
            'icon' => 'users',
        ],
        [
            'id' => 'rules',
            'label' => 'Standar Gaji Jabatan & Komisi Layanan',
            'short_label' => 'Standar Gaji',
            'href' => route('owner.payroll.rules'),
            'icon' => 'sliders',
        ],
    ];
@endphp

{{-- Tab Navigasi Khusus Mobile: Statis 3 Kolom Ringkas, Disembunyikan di Tablet & Desktop (md:hidden) --}}
<div class="md:hidden grid grid-cols-3 gap-1 p-1 bg-zinc-100/90 border border-zinc-200/80 rounded-2xl mb-5 w-full shadow-2xs">
    @foreach($tabs as $tab)
        @php
            $isActive = ($active === $tab['id']);
        @endphp
        <a
            href="{{ $tab['href'] }}"
            class="flex items-center justify-center gap-1.5 py-2 px-1.5 rounded-xl text-xs font-bold text-center transition-all duration-150 select-none min-w-0 active:scale-95 {{ $isActive ? 'bg-zinc-950 text-white shadow-xs font-extrabold ring-1 ring-zinc-900/10' : 'text-zinc-600 hover:text-zinc-950 hover:bg-white/80 active:bg-zinc-200/50' }}"
            title="{{ $tab['label'] }}"
        >
            <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-400' }}" />
            <span class="truncate">{{ $tab['short_label'] }}</span>
        </a>
    @endforeach
</div>
