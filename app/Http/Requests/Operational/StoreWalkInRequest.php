<?php

declare(strict_types=1);

namespace App\Http\Requests\Operational;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWalkInRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('tenant_id', $tenantId)],
            'hair_color_id' => ['nullable', 'integer', Rule::exists('hair_colors', 'id')->where('tenant_id', $tenantId)],
            'barber_membership_id' => ['nullable', 'integer', Rule::exists('memberships', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
