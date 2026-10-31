<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

final class StoreHairColorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'hex_code' => ['required', 'string', 'max:16'],
            'stock' => ['required', 'integer', 'min:0', 'max:10000'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
