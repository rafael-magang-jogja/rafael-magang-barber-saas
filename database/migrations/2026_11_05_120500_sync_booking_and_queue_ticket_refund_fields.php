<?php

use App\Models\Booking;
use App\Models\QueueTicket;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sinkronisasi data refund dari queue_tickets ke bookings yang masih kosong
        $bookings = Booking::query()
            ->withoutGlobalScope('tenant')
            ->whereNull('refund_bank_name')
            ->whereNotNull('refund_status')
            ->get();

        foreach ($bookings as $booking) {
            $ticket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('booking_id', $booking->id)
                ->first();

            if ($ticket && ! empty($ticket->refund_bank_name)) {
                $booking->update([
                    'refund_bank_name' => $ticket->refund_bank_name,
                    'refund_account_number' => $ticket->refund_account_number,
                    'refund_account_name' => $ticket->refund_account_name,
                    'refund_amount' => $booking->refund_amount ?? $ticket->refund_amount,
                    'refund_requested_at' => $booking->refund_requested_at ?? $ticket->refund_requested_at,
                ]);
            }
        }

        // Sinkronisasi sebaliknya dari bookings ke queue_tickets jika ada tiket yang kosong
        $tickets = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->whereNotNull('booking_id')
            ->whereNull('refund_bank_name')
            ->whereNotNull('refund_status')
            ->get();

        foreach ($tickets as $ticket) {
            $booking = Booking::query()
                ->withoutGlobalScope('tenant')
                ->find($ticket->booking_id);

            if ($booking && ! empty($booking->refund_bank_name)) {
                $ticket->update([
                    'refund_bank_name' => $booking->refund_bank_name,
                    'refund_account_number' => $booking->refund_account_number,
                    'refund_account_name' => $booking->refund_account_name,
                    'refund_amount' => $ticket->refund_amount ?? $booking->refund_amount,
                    'refund_requested_at' => $ticket->refund_requested_at ?? $booking->refund_requested_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed for data synchronization
    }
};
