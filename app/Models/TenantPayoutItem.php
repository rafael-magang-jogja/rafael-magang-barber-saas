<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantPayoutItem extends Model
{
    protected $fillable = [
        'tenant_payout_id',
        'booking_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(TenantPayout::class, 'tenant_payout_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
