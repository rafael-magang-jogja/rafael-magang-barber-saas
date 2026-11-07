<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Terkonfirmasi',
            self::CheckedIn => 'Sudah Check-in',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::NoShow => 'Tidak Hadir',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
