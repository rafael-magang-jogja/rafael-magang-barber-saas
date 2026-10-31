<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;

final class BookingRepository
{
    public function lockBookingByIdOrFail(int $bookingId): Booking
    {
        return Booking::query()
            ->withoutGlobalScope('tenant')
            ->with('items')
            ->whereKey($bookingId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function hasOverlappingSlotForBarber(
        int $tenantId,
        int $barberMembershipId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): bool {
        return Booking::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('barber_membership_id', $barberMembershipId)
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->where('scheduled_at', '<', $endsAt)
            ->where('estimated_end_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();
    }

    public function countActiveBookingsToday(): int
    {
        return Booking::query()
            ->whereDate('scheduled_at', now()->toDateString())
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->count();
    }
}
