<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentTenant::class)->id() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('price')) {
            $this->merge([
                'price' => (int) preg_replace('/\D/', '', (string) $this->input('price')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'service_category_id' => [
                'nullable',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'price' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'type' => ['required', Rule::in(['service', 'product'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
