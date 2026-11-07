<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'customer_profile_id',
        'user_id',
        'barber_membership_id',
        'hair_color_id',
        'scheduled_at',
        'estimated_end_at',
        'total_duration_minutes',
        'estimated_total',
        'status',
        'source',
        'notes',
        'xendit_invoice_id',
        'xendit_external_id',
        'xendit_payment_url',
        'xendit_payment_method',
        'xendit_payment_channel',
        'xendit_paid_at',
        'xendit_expired_at',
        'payment_proof_path',
        'payment_proof_uploaded_at',
        'payment_approved_at',
        'payment_approved_by',
        'payment_rejected_reason',
        'refund_status',
        'refund_amount',
        'refund_bank_name',
        'refund_account_number',
        'refund_account_name',
        'refund_notes',
        'refund_requested_at',
        'refunded_at',
        'refunded_by',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'estimated_end_at' => 'datetime',
            'total_duration_minutes' => 'integer',
            'estimated_total' => 'integer',
            'status' => BookingStatus::class,
            'xendit_paid_at' => 'datetime',
            'xendit_expired_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'payment_approved_at' => 'datetime',
            'refund_amount' => 'integer',
            'refund_requested_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return $query->where(function ($q) use ($field, $value): void {
            $q->where($field, $value);
            if (is_numeric($value)) {
                $q->orWhere('id', (int) $value);
            }
        });
    }

    public function paymentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_approved_by');
    }

    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function queueTicket(): HasOne
    {
        return $this->hasOne(QueueTicket::class);
    }

    public function payoutItem(): HasOne
    {
        return $this->hasOne(TenantPayoutItem::class);
    }
}
