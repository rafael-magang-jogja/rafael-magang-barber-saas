<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class HairColor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'hex_code',
        'stock',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Apakah stok sudah menyentuh atau mendekati batas minimum (stock <= min_stock + 2).
     */
    public function isNearOrBelowMinStock(): bool
    {
        return $this->is_active && $this->stock <= ($this->min_stock + 2);
    }

    public function isBelowMinStock(): bool
    {
        return $this->is_active && $this->stock <= $this->min_stock;
    }
}
