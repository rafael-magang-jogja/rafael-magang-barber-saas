<?php

declare(strict_types=1);

namespace App\Http\Requests\Operational;

use App\Enums\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ToggleWorkStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_status' => ['required', Rule::enum(WorkStatus::class)],
        ];
    }
}
