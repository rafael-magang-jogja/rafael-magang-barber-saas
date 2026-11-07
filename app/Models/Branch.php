<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Branch extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'maps_url',
        'phone',
        'lat',
        'lng',
        'radius_meters',
        'opening_hours',
        'late_tolerance_minutes',
        'booking_slot_minutes',
        'allow_customer_choose_barber',
        'qr_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'radius_meters' => 'integer',
            'opening_hours' => 'array',
            'late_tolerance_minutes' => 'integer',
            'booking_slot_minutes' => 'integer',
            'allow_customer_choose_barber' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Mendapatkan jadwal jam operasional cabang untuk tanggal/waktu tertentu.
     *
     * @return array{is_open: bool, open: string, close: string, break_start: string|null, break_end: string|null}
     */
    public function getDayHours(?\Carbon\CarbonInterface $date = null): array
    {
        $date = $date ?? now();
        $openingHours = $this->opening_hours ?? [];
        if (empty($openingHours)) {
            return [
                'is_open' => true,
                'open' => '09:00',
                'close' => '21:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
            ];
        }

        $dayNum = $date->dayOfWeek; // 0 (Sun) .. 6 (Sat)
        $dayCode = strtolower($date->format('D')); // 'sun' .. 'sat'

        $dayConfig = $openingHours[$dayNum]
            ?? $openingHours[(string) $dayNum]
            ?? $openingHours[$dayCode]
            ?? null;

        if ($dayConfig === null) {
            return [
                'is_open' => true,
                'open' => '09:00',
                'close' => '21:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
            ];
        }

        $isOpen = true;
        if (isset($dayConfig['is_open'])) {
            $isOpen = filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN) || (string) $dayConfig['is_open'] === '1';
        }

        return [
            'is_open' => $isOpen,
            'open' => ! empty($dayConfig['open']) ? substr((string) $dayConfig['open'], 0, 5) : '09:00',
            'close' => ! empty($dayConfig['close']) ? substr((string) $dayConfig['close'], 0, 5) : '21:00',
            'break_start' => ! empty($dayConfig['break_start']) ? substr((string) $dayConfig['break_start'], 0, 5) : '12:00',
            'break_end' => ! empty($dayConfig['break_end']) ? substr((string) $dayConfig['break_end'], 0, 5) : '13:00',
        ];
    }

    /**
     * Memeriksa apakah cabang sedang buka sekarang untuk melayani antrean pelanggan walk-in.
     * Mengembalikan array [status, pesan_alasan].
     *
     * @return array{is_open: bool, reason: string|null, hours: array{is_open: bool, open: string, close: string, break_start: string|null, break_end: string|null}}
     */
    public function checkOperationalStatus(?\Carbon\CarbonInterface $time = null): array
    {
        $time = $time ?? now();
        $hours = $this->getDayHours($time);
        $dayName = match ($time->dayOfWeek) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        if (! $hours['is_open']) {
            return [
                'is_open' => false,
                'reason' => "Cabang {$this->name} tutup pada hari {$dayName}.",
                'hours' => $hours,
            ];
        }

        $currentTimeStr = $time->format('H:i');

        if ($currentTimeStr < $hours['open']) {
            return [
                'is_open' => false,
                'reason' => "Cabang {$this->name} belum buka (jam operasional hari {$dayName} pukul {$hours['open']} - {$hours['close']} WIB).",
                'hours' => $hours,
            ];
        }

        if ($currentTimeStr >= $hours['close']) {
            return [
                'is_open' => false,
                'reason' => "Cabang {$this->name} sudah tutup (jam operasional hari {$dayName} pukul {$hours['open']} - {$hours['close']} WIB).",
                'hours' => $hours,
            ];
        }

        // Cek jam istirahat
        if (! empty($hours['break_start']) && ! empty($hours['break_end'])) {
            if ($currentTimeStr >= $hours['break_start'] && $currentTimeStr < $hours['break_end']) {
                return [
                    'is_open' => false,
                    'reason' => "Cabang {$this->name} saat ini sedang jam istirahat ({$hours['break_start']} - {$hours['break_end']} WIB). Layanan antrean akan dibuka kembali setelah jam istirahat.",
                    'hours' => $hours,
                ];
            }
        }

        return [
            'is_open' => true,
            'reason' => null,
            'hours' => $hours,
        ];
    }
}
