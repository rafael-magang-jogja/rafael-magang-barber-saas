<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\Platform\PlatformConfig::clearCache());
        static::deleted(fn () => \App\Support\Platform\PlatformConfig::clearCache());
    }

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
