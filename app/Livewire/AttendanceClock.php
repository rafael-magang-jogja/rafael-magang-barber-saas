<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class AttendanceClock extends Component
{
    public bool $showPulse = false;

    public function mount(bool $showPulse = false): void
    {
        $this->showPulse = $showPulse;
    }

    public function render(): View
    {
        return view('livewire.attendance-clock', [
            'currentTime' => now()->format('H:i'),
        ]);
    }
}
