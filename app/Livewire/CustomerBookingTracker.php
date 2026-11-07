<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class CustomerBookingTracker extends Component
{
    public int $bookingId;
    public string $bookingCode = '';
    public string $status = 'pending';
    public bool $isPaid = false;
    public ?string $ticketNumber = null;
    public ?string $ticketStatus = null;

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;

        $booking = Booking::query()
            ->withoutGlobalScope('tenant')
            ->with(['queueTicket'])
            ->findOrFail($bookingId);

        $this->bookingCode = $booking->code;
        $this->status = $booking->status->value;
        $this->isPaid = $booking->xendit_paid_at !== null
            || $booking->payment_approved_at !== null
            || $booking->queueTicket !== null;
        $this->ticketNumber = $booking->queueTicket?->number;
        $this->ticketStatus = $booking->queueTicket?->status->value;
    }

    public function checkBookingStatus(): void
    {
        $booking = Booking::query()
            ->withoutGlobalScope('tenant')
            ->with(['queueTicket'])
            ->find($this->bookingId);

        if ($booking === null) {
            return;
        }

        $newPaid = $booking->xendit_paid_at !== null
            || $booking->payment_approved_at !== null
            || $booking->queueTicket !== null;
        $newStatus = $booking->status->value;
        $newTicketNumber = $booking->queueTicket?->number;
        $newTicketStatus = $booking->queueTicket?->status->value;

        // Jika terjadi perubahan status pembayaran, terbit tiket, atau status antrean berubah
        if (
            $newPaid !== $this->isPaid
            || $newStatus !== $this->status
            || $newTicketNumber !== $this->ticketNumber
            || $newTicketStatus !== $this->ticketStatus
        ) {
            $this->isPaid = $newPaid;
            $this->status = $newStatus;
            $this->ticketNumber = $newTicketNumber;
            $this->ticketStatus = $newTicketStatus;

            // Muat ulang halaman secara otomatis agar seluruh komponen dan status terbaru tampil
            $this->js('window.location.reload()');
        }
    }

    public function render(): View
    {
        return view('livewire.customer-booking-tracker');
    }
}
