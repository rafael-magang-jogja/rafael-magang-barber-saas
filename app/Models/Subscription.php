<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Subscription extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saved(function (Subscription $subscription): void {
            if ($subscription->wasChanged('plan_id')) {
                $subscription->unsetRelation('plan');
            }
            $currentTenant = app(\App\Support\Tenancy\CurrentTenant::class)->get();
            if ($currentTenant !== null && $currentTenant->id === $subscription->tenant_id) {
                $currentTenant->flushMemo();
            }
            if ($subscription->relationLoaded('tenant') && $subscription->tenant instanceof Tenant) {
                $subscription->tenant->flushMemo();
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'price',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'integer',
            'auto_renew' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }
}
