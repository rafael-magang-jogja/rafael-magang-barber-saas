@props([
    'active' => 'schedules',
])

@php
    $pendingTimeOffsCount = \App\Models\BarberTimeOff::query()->where('status', 'pending')->count();

    $tabs = [
        [
            'id' => 'schedules',
            'label' => 'Jadwal Shift Kerja',
            'short_label' => 'Jadwal Shift',
            'href' => route('owner.schedules'),
            'icon' => 'calendar',
            'badge' => null,
        ],
        [
            'id' => 'time-offs',
            'label' => 'Pengajuan & Izin Libur',
            'short_label' => 'Izin & Libur',
            'href' => route('owner.time-offs'),
            'icon' => 'calendar',
            'badge' => $pendingTimeOffsCount > 0 ? $pendingTimeOffsCount : null,
        ],
        [
            'id' => 'attendances',
            'label' => 'Rekap & Log Absensi',
            'short_label' => 'Log Absensi',
            'href' => route('owner.attendances'),
            'icon' => 'check',
            'badge' => null,
        ],
        [
            'id' => 'settings',
            'label' => 'Pengaturan & Toleransi',
            'short_label' => 'Pengaturan',
            'href' => route('owner.schedule-settings'),
            'icon' => 'settings',
            'badge' => null,
        ],
    ];
@endphp

<div class="md:hidden grid grid-cols-2 gap-1.5 p-1.5 mb-6 bg-zinc-200/80 rounded-2xl w-full">
    @foreach($tabs as $tab)
        @php
            $isActive = ($active === $tab['id']);
        @endphp
        <a
            href="{{ $tab['href'] }}"
            class="flex items-center justify-center gap-1.5 py-2.5 px-2 rounded-xl text-xs font-bold transition text-center {{ $isActive ? 'bg-zinc-950 text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}"
        >
            <x-ui.icon :name="$tab['icon']" class="w-3.5 h-3.5 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-500' }}" />
            <span class="truncate">{{ $tab['short_label'] ?? $tab['label'] }}</span>
            @if($tab['badge'])
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-black shrink-0 {{ $isActive ? 'bg-amber-500 text-zinc-950' : 'bg-rose-500 text-white' }}">
                    {{ $tab['badge'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>
