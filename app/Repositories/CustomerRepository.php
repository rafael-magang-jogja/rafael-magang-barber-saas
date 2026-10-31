<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CustomerProfile;

final class CustomerRepository
{
    public function firstOrCreateByPhone(
        int $tenantId,
        ?string $phone,
        string $name,
        ?int $userId = null,
    ): CustomerProfile {
        $cleanPhone = $phone !== null ? trim($phone) : '';

        if ($cleanPhone === '') {
            return CustomerProfile::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'name' => $name,
                    'phone' => null,
                ]);
        }

        $profile = CustomerProfile::query()
            ->withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $cleanPhone],
                ['user_id' => $userId, 'name' => $name],
            );

        if ($userId !== null && $profile->user_id === null) {
            $profile->update(['user_id' => $userId]);
        }

        return $profile;
    }
}
