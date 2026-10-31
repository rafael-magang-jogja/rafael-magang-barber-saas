<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayrollItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'payroll_id',
        'tenant_id',
        'membership_id',
        'services_count',
        'base_salary',
        'commission_total',
        'bonus_total',
        'deduction_total',
        'tip_total',
        'gross_total',
        'net_total',
        'scheme_snapshot',
        'payment_status',
        'paid_at',
        'payment_method',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'services_count' => 'integer',
            'base_salary' => 'integer',
            'commission_total' => 'integer',
            'bonus_total' => 'integer',
            'deduction_total' => 'integer',
            'tip_total' => 'integer',
            'gross_total' => 'integer',
            'net_total' => 'integer',
            'scheme_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
