<div
    wire:poll.60s
    x-data="{
        displayTime: '{{ $currentTime }}',
        updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            this.displayTime = `${hours}:${minutes}`;
        }
    }"
    x-init="
        updateClock();
        const now = new Date();
        const msToNextMinute = (60 - now.getSeconds()) * 1000 - now.getMilliseconds();
        setTimeout(() => {
            updateClock();
            setInterval(() => updateClock(), 60000);
        }, Math.max(100, msToNextMinute));
    "
    class="inline-flex items-center"
>
    @if($showPulse)
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-amber-500/10 text-amber-700 border border-amber-500/20 font-mono font-bold text-xs shadow-2xs">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
            <span><span x-text="displayTime">{{ $currentTime }}</span> WIB</span>
        </span>
    @else
        <span class="font-mono text-xs font-black text-amber-600 bg-amber-500/10 px-2 py-0.5 rounded-lg inline-flex items-center">
            <span><span x-text="displayTime">{{ $currentTime }}</span> WIB</span>
        </span>
    @endif
</div>
