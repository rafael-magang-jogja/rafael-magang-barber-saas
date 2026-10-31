<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

final class BranchRepository
{
    /**
     * @return Collection<int, Branch>
     */
    public function getActiveBranches(?Tenant $tenant = null): Collection
    {
        $tenant = $tenant ?? app(CurrentTenant::class)->get();
        $query = Branch::query()->where('is_active', true)->orderBy('id');

        if ($tenant !== null) {
            $query->forTenant($tenant);
            $allowedIds = $tenant->allowedBranchIds();
            $query->whereIn('id', $allowedIds);
        }

        $branches = $query->get();
        app(CurrentTenant::class)->setFirstBranchCode($branches->first()?->code);

        return $branches;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getAllBranches(): Collection
    {
        $branches = Branch::query()->orderBy('id')->get();
        app(CurrentTenant::class)->setFirstBranchCode($branches->first()?->code);

        return $branches;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranchesWithCounts(): Collection
    {
        $branches = Branch::query()
            ->withCount(['memberships', 'queueTickets'])
            ->orderBy('id')
            ->get();
        app(CurrentTenant::class)->setFirstBranchCode($branches->first()?->code);

        return $branches;
    }

    public function findActiveBranchByCodeOrFail(Tenant $tenant, string $code): Branch
    {
        return Branch::query()
            ->forTenant($tenant)
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function findByIdOrFail(int $id, ?Tenant $tenant = null): Branch
    {
        $query = Branch::query();

        if ($tenant !== null) {
            $query->forTenant($tenant);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Branch
    {
        return Branch::query()->create($attributes);
    }
}
