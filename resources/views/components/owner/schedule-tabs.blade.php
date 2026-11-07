@props([
    'active' => 'schedules',
])

@php
    $pendingTimeOffsCount = app(\App\Support\Tenancy\CurrentTenant::class)->pendingTimeOffsCount();

    $tabs = [
        [
            'id' => 'scanner',
            'label' => 'Scan Absen Karyawan',
            'short_label' => 'Scan Absen',
            'href' => route('owner.attendances.scanner'),
            'icon' => 'camera',
            'badge' => null,
        ],
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

{{-- Tab Navigasi Khusus Mobile: Statis 5 Kolom Ringkas Tanpa Scroll --}}
<div class="md:hidden grid grid-cols-5 gap-1 p-1 mb-5 bg-zinc-100/90 border border-zinc-200/80 rounded-2xl w-full shadow-2xs">
    @foreach($tabs as $tab)
        @php
            $isActive = ($active === $tab['id']);
        @endphp
        <a
            href="{{ $tab['href'] }}"
            class="flex flex-col items-center justify-center py-2 px-0.5 rounded-xl text-center transition-all duration-150 select-none min-w-0 active:scale-95 {{ $isActive ? 'bg-zinc-950 text-white shadow-xs font-black ring-1 ring-zinc-900/10' : 'text-zinc-600 hover:text-zinc-950 hover:bg-white/80 active:bg-zinc-200/50' }}"
            title="{{ $tab['label'] }}"
        >
            <div class="relative inline-flex mb-1">
                <x-ui.icon :name="$tab['icon']" class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-zinc-400' }}" />
                @if($tab['badge'])
                    <span class="absolute -top-1 -right-2 min-w-[14px] h-3.5 px-0.5 rounded-full text-[9px] font-black leading-none bg-rose-500 text-white flex items-center justify-center shadow-xs">
                        {{ $tab['badge'] }}
                    </span>
                @endif
            </div>
            <span class="text-[10px] sm:text-xs font-bold leading-tight truncate max-w-full tracking-tight">
                {{ $tab['short_label'] ?? $tab['label'] }}
            </span>
        </a>
    @endforeach
</div>
