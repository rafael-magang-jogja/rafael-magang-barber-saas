<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CashierNavBadge extends Component
{
    /**
     * Tipe badge: 'queue', 'booking', atau 'parent_dot'
     */
    public string $type = 'queue';

    /**
     * Konteks render: 'sidebar', 'mobile', atau 'subnav'
     */
    public string $context = 'sidebar';

    public function render(): View
    {
        $counts = app(CurrentTenant::class)->cashierNavBadgeCounts();
        $queueCount = $counts['queue'];
        $bookingCount = $counts['booking'];

        $count = match ($this->type) {
            'queue' => $queueCount,
            'booking' => $bookingCount,
            default => 0,
        };

        $hasDot = ($queueCount > 0 || $bookingCount > 0);

        return view('livewire.cashier-nav-badge', [
            'count' => $count,
            'hasDot' => $hasDot,
        ]);
    }
}
