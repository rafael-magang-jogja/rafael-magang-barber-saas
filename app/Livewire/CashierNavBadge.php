<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Enums\QueueStatus;
use App\Models\Booking;
use App\Models\QueueTicket;
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
        $tenant = app(CurrentTenant::class)->get();
        $tenantId = $tenant?->id;

        $queueCount = 0;
        $bookingCount = 0;

        if ($tenantId !== null) {
            // Hitung tiket antrean hari ini yang berstatus 'menunggu' (waiting) dan 'dipanggil' (called)
            if ($this->type === 'queue' || $this->type === 'parent_dot') {
                $queueCount = QueueTicket::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('date', now()->toDateString())
                    ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called])
                    ->count();
            }

            // Hitung booking terkonfirmasi hari ini yang menunggu check-in
            if ($this->type === 'booking' || $this->type === 'parent_dot') {
                $bookingCount = Booking::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('scheduled_at', now()->toDateString())
                    ->where('status', BookingStatus::Confirmed)
                    ->count();
            }
        }

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
