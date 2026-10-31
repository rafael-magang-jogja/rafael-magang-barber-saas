<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

final class PlatformRepository
{
    /**
     * @return Collection<int, Tenant>
     */
    public function getAllTenantsWithRelations(): Collection
    {
        return Tenant::query()
            ->with(['owner', 'subscriptions.plan'])
            ->withCount('branches')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Plan>
     */
    public function getActivePlans(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get();
    }

    public function findTenantBySlugOrFail(string $slug): Tenant
    {
        return Tenant::query()->where('slug', $slug)->firstOrFail();
    }
}
