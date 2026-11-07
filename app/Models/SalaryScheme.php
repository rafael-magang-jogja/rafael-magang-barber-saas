<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalaryScheme extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'base_salary',
        'alpha_penalty_nominal',
        'commission_percentage',
        'period',
        'target_bonus_rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'alpha_penalty_nominal' => 'integer',
            'commission_percentage' => 'integer',
            'target_bonus_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Cek apakah skema menggunakan persentase bagi hasil omzet.
     */
    public function getHasPercentageCommissionAttribute(): bool
    {
        if (isset($this->attributes['commission_percentage']) && $this->attributes['commission_percentage'] !== null && (int) $this->attributes['commission_percentage'] > 0) {
            return true;
        }

        if (preg_match('/(\d+)\s*%/', $this->name)) {
            return true;
        }

        return false;
    }

    /**
     * Dapatkan persentase bagi hasil untuk skema commission_only (fleksibel).
     */
    public function getEffectiveCommissionPercentageAttribute(): int
    {
        if (isset($this->attributes['commission_percentage']) && $this->attributes['commission_percentage'] !== null && (int) $this->attributes['commission_percentage'] > 0) {
            return (int) $this->attributes['commission_percentage'];
        }

        if (is_array($this->target_bonus_rules) && isset($this->target_bonus_rules['commission_percentage']) && (int) $this->target_bonus_rules['commission_percentage'] > 0) {
            return (int) $this->target_bonus_rules['commission_percentage'];
        }

        // Coba ekstrak angka persentase dari nama jika ada pola misal "30%" atau "40%"
        if (preg_match('/(\d+)\s*%/', $this->name, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Dapatkan daftar target bonus berjenjang dalam format terstandarisasi.
     *
     * @return array<int, array{min_services: int, bonus: int}>
     */
    public function getBonusTiersAttribute(): array
    {
        $rules = $this->target_bonus_rules;
        if (empty($rules) || !is_array($rules)) {
            return [];
        }

        // Format 1: array of tiers langsung [['min_services' => ..., 'bonus' => ...], ...]
        if (isset($rules[0]) && is_array($rules[0])) {
            return $rules;
        }

        // Format 2: ['tiers' => [...]]
        if (isset($rules['tiers']) && is_array($rules['tiers'])) {
            return $rules['tiers'];
        }

        return [];
    }
}
