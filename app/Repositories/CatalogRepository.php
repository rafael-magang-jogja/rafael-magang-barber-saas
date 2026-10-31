<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\HairColor;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

final class CatalogRepository
{
    /**
     * @return Collection<int, ServiceCategory>
     */
    public function getServiceCategories(): Collection
    {
        return ServiceCategory::query()->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServicesWithCategory(): Collection
    {
        return Service::query()
            ->with('category')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    public function getActiveServices(?Tenant $tenant = null): Collection
    {
        $query = Service::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($tenant !== null) {
            $query->forTenant($tenant);
        }

        return $query->get();
    }

    /**
     * @param  array<int, int>  $serviceIds
     * @return Collection<int, Service>
     */
    public function getActiveServicesByIdsForTenant(int $tenantId, array $serviceIds): Collection
    {
        return Service::query()
            ->withoutGlobalScope('tenant')
            ->with('category')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $serviceIds)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createService(array $attributes): Service
    {
        return Service::query()->create($attributes);
    }

    public function updateServicePrice(Service $service, int $price): Service
    {
        $service->update(['price' => $price]);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateService(Service $service, array $attributes): Service
    {
        $service->update($attributes);

        return $service->refresh();
    }

    /**
     * @return Collection<int, HairColor>
     */
    public function getAllHairColors(): Collection
    {
        $colors = HairColor::query()->orderBy('name')->get();
        app(CurrentTenant::class)->setHasLowColorStockDot(
            $colors->contains(fn (HairColor $c): bool => $c->isNearOrBelowMinStock())
        );

        return $colors;
    }

    /**
     * @return Collection<int, HairColor>
     */
    public function getAvailableHairColors(?Tenant $tenant = null): Collection
    {
        $query = HairColor::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name');

        if ($tenant !== null) {
            $query->forTenant($tenant);
        }

        return $query->get();
    }

    public function findAvailableHairColorForTenant(int $tenantId, ?int $hairColorId, bool $lockForUpdate = false): ?HairColor
    {
        if ($hairColorId === null) {
            return null;
        }

        $query = HairColor::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($hairColorId)
            ->where('is_active', true);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createHairColor(array $attributes): HairColor
    {
        return HairColor::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateHairColor(HairColor $hairColor, array $attributes): HairColor
    {
        $hairColor->update($attributes);

        return $hairColor->refresh();
    }
}
