<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Membership;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentTenant::class)->id() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();
        /** @var Membership|null $membership */
        $membership = $this->route('membership');
        $userId = $membership?->user_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Barber->value])],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'specialty' => ['nullable', 'string', 'max:120'],
            'salary_scheme_id' => [
                'required',
                Rule::exists('salary_schemes', 'id')->where('tenant_id', $tenantId),
            ],
            'work_status' => [
                'nullable',
                Rule::in([WorkStatus::Active->value, WorkStatus::Break->value, WorkStatus::Off->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
