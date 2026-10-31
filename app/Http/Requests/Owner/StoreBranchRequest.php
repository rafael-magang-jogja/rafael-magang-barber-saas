<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBranchRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('branches', 'code')->where('tenant_id', $tenantId),
            ],
            'address' => ['required', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama cabang wajib diisi.',
            'code.required' => 'Kode cabang wajib diisi.',
            'code.unique' => 'Kode cabang ini sudah digunakan di cabang lain.',
            'phone.required' => 'Nomor telepon cabang wajib diisi.',
            'address.required' => 'Alamat lengkap cabang wajib diisi.',
            'late_tolerance_minutes.required' => 'Toleransi telat booking wajib diisi.',
        ];
    }
}
