<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerWithdrawal extends Model
{
    protected $fillable = [
        'withdrawal_code',
        'user_id',
        'customer_profile_id',
        'amount',
        'admin_fee',
        'net_amount',
        'bank_name',
        'account_number',
        'account_holder',
        'status',
        'proof_path',
        'rejection_reason',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'admin_fee' => 'integer',
            'net_amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
