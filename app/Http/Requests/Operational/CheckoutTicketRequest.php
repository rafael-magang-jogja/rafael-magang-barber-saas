<?php

declare(strict_types=1);

namespace App\Http\Requests\Operational;

use App\Enums\PaymentMethod;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentTenant::class)->id() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_total' => $this->filled('discount_total')
                ? (int) str_replace(['.', ',', ' '], '', (string) $this->input('discount_total'))
                : 0,
            'cash_received' => $this->filled('cash_received')
                ? (int) str_replace(['.', ',', ' '], '', (string) $this->input('cash_received'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount_total' => ['nullable', 'integer', 'min:0'],
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
