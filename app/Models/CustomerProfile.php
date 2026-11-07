<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'phone',
        'notes',
        'visit_count',
        'total_spent',
        'last_visit_at',
        'points',
        'wallet_balance',
    ];

    protected function casts(): array
    {
        return [
            'visit_count' => 'integer',
            'total_spent' => 'integer',
            'last_visit_at' => 'datetime',
            'points' => 'integer',
            'wallet_balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(CustomerWithdrawal::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
