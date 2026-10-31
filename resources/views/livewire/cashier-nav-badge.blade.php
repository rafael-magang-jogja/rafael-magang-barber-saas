<span wire:poll.5s class="inline-flex items-center">
    @if($type === 'parent_dot')
        @if($hasDot)
            @if($context === 'mobile')
                <span class="absolute -top-0.5 -right-1 flex h-2 w-2" data-nav-indicator>
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
            @else
                <span class="relative flex h-2 w-2 shrink-0" data-nav-indicator>
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
            @endif
        @endif
    @elseif($type === 'queue' || $type === 'booking')
        @if($count > 0)
            @php
                $badgeBg = $type === 'queue' ? 'bg-amber-400 text-zinc-950 font-black' : 'bg-rose-500 text-white font-black';
            @endphp
            @if($context === 'mobile')
                <span class="absolute -top-1.5 -right-2.5 min-w-[16px] h-4 px-1 rounded-full text-[9px] leading-none {{ $badgeBg }} flex items-center justify-center num shadow-sm shrink-0">{{ $count > 99 ? '99+' : $count }}</span>
            @elseif($context === 'subnav')
                <span class="inline-flex items-center justify-center min-w-[18px] h-4 px-1 rounded-full text-[10px] leading-none {{ $badgeBg }} num shadow-2xs shrink-0 ml-1">{{ $count > 99 ? '99+' : $count }}</span>
            @else
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] leading-none {{ $badgeBg }} num shadow-2xs shrink-0 ml-1.5">{{ $count }}</span>
            @endif
        @endif
    @endif
</span>
