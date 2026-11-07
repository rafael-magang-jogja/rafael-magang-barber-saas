<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class TenantPayout extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reference_no',
        'amount',
        'status',
        'bank_name',
        'account_number',
        'account_holder',
        'transfer_proof_path',
        'processed_by',
        'processed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantPayoutItem::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(
            Booking::class,
            TenantPayoutItem::class,
            'tenant_payout_id',
            'id',
            'id',
            'booking_id'
        );
    }
}
