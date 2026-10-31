<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateServicePriceRequest extends FormRequest
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
        return [
            'price' => ['required', 'integer', 'min:0'],
        ];
    }
}
