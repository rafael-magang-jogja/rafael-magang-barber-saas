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
        // Staf Barber tidak ada model gaji pokok, murni komisi per kepala / layanan
        if ($this->role === Role::Barber || (is_object($this->role) && $this->role->value === 'barber') || $this->role === 'barber') {
            return 0;
        }

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

    /**
     * Generate secure attendance QR token for a given date (default today).
     */
    public function getAttendanceQrToken(?string $date = null): string
    {
        $dateStr = $date ?? now()->toDateString();
        $secret = (string) config('app.key', 'barberos-attendance-secret');

        return hash_hmac('sha256', "membership:{$this->id}:tenant:{$this->tenant_id}:date:{$dateStr}", $secret);
    }

    /**
     * Get structured JSON payload to encode in the staff attendance QR code.
     */
    public function getAttendanceQrPayload(?string $date = null): string
    {
        $dateStr = $date ?? now()->toDateString();
        $token = $this->getAttendanceQrToken($dateStr);

        return (string) json_encode([
            'type' => 'staff_attendance',
            'membership_id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $this->display_name ?? $this->user?->name ?? 'Staff',
            'date' => $dateStr,
            'token' => substr($token, 0, 32),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Verify whether an attendance QR token is authentic for a given date.
     */
    public function verifyAttendanceQrToken(string $token, ?string $date = null): bool
    {
        $expectedToken = substr($this->getAttendanceQrToken($date), 0, 32);

        return hash_equals($expectedToken, $token);
    }
}
