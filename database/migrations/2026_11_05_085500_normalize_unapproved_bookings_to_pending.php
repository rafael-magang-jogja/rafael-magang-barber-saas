<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalisasi booking yang belum bayar/approve agar berstatus Pending
        DB::table('bookings')
            ->whereNull('payment_approved_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('queue_tickets')
                    ->whereColumn('queue_tickets.booking_id', 'bookings.id');
            })
            ->whereIn('status', ['confirmed'])
            ->update([
                'status' => BookingStatus::Pending->value,
            ]);
    }

    public function down(): void
    {
        // Tidak perlu revert
    }
};
