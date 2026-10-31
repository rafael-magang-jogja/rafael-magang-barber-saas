<?php

declare(strict_types=1);

namespace App\Http\Requests\Operational;

use App\Enums\QueueStatus;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionTicketRequest extends FormRequest
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
            'status' => ['required', Rule::enum(QueueStatus::class)],
            'barber_membership_id' => ['nullable', 'integer', Rule::exists('memberships', 'id')->where('tenant_id', $tenantId)],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
