<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlatformAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'target_role',
        'is_active',
        'created_by',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PlatformAnnouncementRead::class, 'announcement_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereIn('target_role', ['all', $role]);
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function durationLabel(): string
    {
        if ($this->ends_at === null) {
            return 'Seterusnya (Tanpa Batas)';
        }

        if ($this->starts_at && $this->ends_at->isSameDay($this->starts_at)) {
            return 'Hari Ini Saja (s/d 23:59 WIB)';
        }

        return 'Hingga ' . $this->ends_at->translatedFormat('d M Y, H:i') . ' WIB';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'urgent' => 'Darurat / Mendesak',
            'maintenance' => 'Pemeliharaan Server',
            'warning' => 'Peringatan Penting',
            default => 'Info & Pembaruan',
        };
    }

    public function targetRoleLabel(): string
    {
        return match ($this->target_role) {
            'owner' => 'Khusus Pemilik (Owner)',
            'admin', 'cashier' => 'Khusus Kasir / Admin',
            'barber' => 'Khusus Barber Staff',
            'customer' => 'Khusus Customer',
            default => 'Semua Pengguna (Global)',
        };
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'urgent' => 'alert-triangle',
            'maintenance' => 'settings',
            'warning' => 'bell',
            default => 'megaphone',
        };
    }

    public function typeBadgeClasses(): string
    {
        return match ($this->type) {
            'urgent' => 'bg-rose-100 text-rose-800 border-rose-200',
            'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
            'warning' => 'bg-orange-100 text-orange-800 border-orange-200',
            default => 'bg-sky-100 text-sky-800 border-sky-200',
        };
    }
}
