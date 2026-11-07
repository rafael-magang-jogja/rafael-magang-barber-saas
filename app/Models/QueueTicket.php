<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class QueueTicket extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'date',
        'number',
        'sequence',
        'booking_id',
        'customer_profile_id',
        'barber_membership_id',
        'hair_color_id',
        'requested_barber_id',
        'type',
        'is_cashier_input',
        'status',
        'priority_at',
        'estimated_duration_minutes',
        'notes',
        'refund_status',
        'refund_amount',
        'refund_bank_name',
        'refund_account_number',
        'refund_account_name',
        'refund_notes',
        'refund_requested_at',
        'refunded_at',
        'refunded_by',
        'called_at',
        'started_at',
        'completed_at',
        'skip_count',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sequence' => 'integer',
            'is_cashier_input' => 'boolean',
            'status' => QueueStatus::class,
            'refund_amount' => 'integer',
            'refund_requested_at' => 'datetime',
            'refunded_at' => 'datetime',
            'priority_at' => 'datetime',
            'estimated_duration_minutes' => 'integer',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'skip_count' => 'integer',
            'version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'barber_membership_id');
    }

    public function hairColor(): BelongsTo
    {
        return $this->belongsTo(HairColor::class);
    }

    public function requestedBarber(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'requested_barber_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QueueTicketItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(QueueTicketEvent::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
