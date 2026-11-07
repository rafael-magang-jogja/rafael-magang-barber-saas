<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformAnnouncementRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'dismissed_permanently',
        'last_shown_at',
    ];

    protected $casts = [
        'dismissed_permanently' => 'boolean',
        'last_shown_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class, 'announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
