<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BarberTimeOff extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'membership_id',
        'date_from',
        'date_to',
        'type',
        'status',
        'reason',
        'attachment_path',
        'notes',
        'approved_by',
    ];

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'sick' => 'Sakit',
            'holiday' => 'Izin Libur',
            'leave' => 'Cuti',
            default => ucfirst((string) $this->type),
        };
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        return $disk->url($this->attachment_path);
    }

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
