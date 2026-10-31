<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Membership;
use App\Models\SalaryScheme;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class StaffRepository
{
    /**
     * @return Collection<int, Membership>
     */
    public function getAllMembershipsWithRelations(): Collection
    {
        return Membership::query()
            ->whereIn('role', [Role::Admin, Role::Barber])
            ->with(['user', 'branch', 'salaryScheme'])
            ->orderBy('role')
            ->get();
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getActiveBarbers(?Tenant $tenant = null, bool $withRelations = false): Collection
    {
        $query = Membership::query()
            ->where('role', Role::Barber)
            ->where('is_active', true);

        if ($tenant !== null) {
            $query->forTenant($tenant);
        }

        if ($withRelations) {
            $query->with(['user', 'branch']);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getBarbersForBranch(Tenant $tenant, int $branchId): Collection
    {
        return Membership::query()
            ->forTenant($tenant)
            ->where('role', Role::Barber)
            ->where('is_active', true)
            ->where(function ($q) use ($branchId): void {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })
            ->with('user')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SalaryScheme>
     */
    public function getActiveSalarySchemes(): Collection
    {
        return SalaryScheme::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function findBarberMembershipForUser(?int $userId): ?Membership
    {
        if ($userId === null) {
            return null;
        }

        return Membership::query()
            ->where('role', Role::Barber)
            ->where('user_id', $userId)
            ->first();
    }

    public function updateBarberWorkStatus(?int $userId, WorkStatus $status): ?Membership
    {
        $membership = $this->findBarberMembershipForUser($userId);

        if ($membership !== null) {
            $membership->update(['work_status' => $status]);
        }

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMembership(array $attributes): Membership
    {
        return Membership::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateUser(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMembership(Membership $membership, array $attributes): Membership
    {
        $membership->update($attributes);

        return $membership->refresh();
    }
}
