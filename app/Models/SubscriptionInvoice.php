<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionInvoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'target_plan_id',
        'type',
        'number',
        'amount',
        'status',
        'due_at',
        'paid_at',
        'gateway',
        'gateway_ref',
        'payment_method',
        'bank_name',
        'proof_path',
        'proof_uploaded_at',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function targetPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }

    public function getProofUrlAttribute(): ?string
    {
        if (empty($this->proof_path)) {
            return null;
        }

        if (str_starts_with($this->proof_path, 'http://') || str_starts_with($this->proof_path, 'https://')) {
            return $this->proof_path;
        }

        return asset('storage/' . $this->proof_path);
    }

    /**
     * @return array<int, array{bank: string, account_number: string, account_name: string, logo_color: string}>
     */
    public static function platformBankAccounts(): array
    {
        $custom = Setting::query()->withoutGlobalScope('tenant')->whereNull('tenant_id')->where('key', 'platform_bank_accounts')->value('value');
        if ($custom) {
            $decoded = json_decode((string) $custom, true);
            if (is_array($decoded) && ! empty($decoded)) {
                return $decoded;
            }
        }

        return [
            [
                'bank' => 'BCA',
                'account_number' => '8830123456',
                'account_name' => 'PT Barber King Digital',
                'logo_color' => 'bg-blue-600',
            ],
            [
                'bank' => 'Mandiri',
                'account_number' => '1370098765432',
                'account_name' => 'PT Barber King Digital',
                'logo_color' => 'bg-indigo-700',
            ],
            [
                'bank' => 'BRI',
                'account_number' => '034101000123305',
                'account_name' => 'PT Barber King Digital',
                'logo_color' => 'bg-sky-600',
            ],
            [
                'bank' => 'BNI',
                'account_number' => '0891234567',
                'account_name' => 'PT Barber King Digital',
                'logo_color' => 'bg-teal-600',
            ],
        ];
    }
}
