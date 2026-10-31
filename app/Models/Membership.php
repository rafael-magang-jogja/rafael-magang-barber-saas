<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Membership extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'role',
        'display_name',
        'specialty',
        'employment_type',
        'salary_scheme_id',
        'custom_base_salary',
        'work_status',
        'rating_avg',
        'reviews_count',
        'permissions',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'work_status' => WorkStatus::class,
            'rating_avg' => 'float',
            'reviews_count' => 'integer',
            'custom_base_salary' => 'integer',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'joined_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salaryScheme(): BelongsTo
    {
        return $this->belongsTo(SalaryScheme::class);
    }

    public function getEffectiveBaseSalaryAttribute(): int
    {
        if ($this->custom_base_salary !== null) {
            return (int) $this->custom_base_salary;
        }

        return (int) ($this->salaryScheme?->base_salary ?? 0);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BarberSchedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
