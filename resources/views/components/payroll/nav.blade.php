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

{{-- Tab Navigasi Khusus Mobile: Statis (tanpa scroll), Disembunyikan di Tablet & Desktop (md:hidden) --}}
<div class="md:hidden grid grid-cols-3 gap-1 p-1 bg-zinc-200/80 rounded-2xl mb-5 w-full">
    @foreach($tabs as $tab)
        @php
            $isActive = ($active === $tab['id']);
        @endphp
        <a
            href="{{ $tab['href'] }}"
            class="flex items-center justify-center gap-1.5 py-2 px-1 rounded-xl text-[11px] font-bold text-center transition min-w-0 {{ $isActive ? 'bg-zinc-950 text-amber-400 shadow-sm' : 'text-zinc-600 hover:text-zinc-950 hover:bg-white/50' }}"
            title="{{ $tab['label'] }}"
        >
            <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0" />
            <span class="truncate">{{ $tab['short_label'] }}</span>
        </a>
    @endforeach
</div>
