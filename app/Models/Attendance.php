<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Attendance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'membership_id',
        'date',
        'check_in_at',
        'check_out_at',
        'check_in_lat',
        'check_in_lng',
        'photo_path',
        'status',
        'late_minutes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'late_minutes' => 'integer',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'sick' => 'Sakit',
            'leave' => 'Izin / Cuti',
            'holiday' => 'Libur',
            'absent' => 'Alpa',
            default => ucfirst((string) $this->status),
        };
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
