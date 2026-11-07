<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('scheduled_date') && $this->filled('scheduled_time')) {
            $this->merge([
                'scheduled_at' => trim((string) $this->input('scheduled_date')).' '.trim((string) $this->input('scheduled_time')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slug = (string) $this->route('tenant');
        $tenantId = Tenant::query()->where('slug', $slug)->value('id');

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('tenant_id', $tenantId)],
            'hair_color_id' => ['nullable', 'integer', Rule::exists('hair_colors', 'id')->where('tenant_id', $tenantId)],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'barber_membership_id' => ['nullable', 'integer', Rule::exists('memberships', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
