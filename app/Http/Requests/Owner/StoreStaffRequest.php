<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\Role;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
