<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class QueueRepository
{
    public function lockOrCreateCounter(int $tenantId, int $branchId, string $queueDate, string $prefix = 'A'): QueueCounter
    {
        $counter = QueueCounter::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereDate('date', $queueDate)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            $counter = QueueCounter::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'date' => $queueDate,
                    'prefix' => $prefix,
                    'last_number' => 0,
                ]);
        }

        return $counter;
    }

    public function lockTicketByIdOrFail(int $ticketId): QueueTicket
    {
        return QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($ticketId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return Collection<string, EloquentCollection<int, QueueTicket>>
     */
    public function getTodayTicketsGroupedByStatus(?string $date = null): Collection
    {
        $targetDate = $date ?? now()->toDateString();

        return QueueTicket::query()
            ->whereDate('date', $targetDate)
            ->with(['customerProfile', 'barber.user', 'branch', 'items', 'order.payments', 'hairColor'])
            ->orderByRaw('COALESCE(priority_at, created_at) ASC')
            ->orderBy('sequence')
            ->get()
            ->groupBy(fn (QueueTicket $t): string => $t->status->value);
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getTodayActiveTicketsForChairs(): EloquentCollection
    {
        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->with(['customerProfile', 'items', 'hairColor'])
            ->orderByRaw('COALESCE(priority_at, created_at) ASC')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Booking>
     */
    public function getTodayPendingBookings(): EloquentCollection
    {
        return Booking::query()
            ->whereDate('scheduled_at', now()->toDateString())
            ->where('status', BookingStatus::Confirmed)
            ->with(['customerProfile', 'barber.user', 'items'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Booking>
     */
    public function getAllPendingBookings(): EloquentCollection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending])
            ->with(['customerProfile', 'barber.user', 'items', 'branch'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getActiveQueueForBarber(?int $barberMembershipId): EloquentCollection
    {
        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->when($barberMembershipId !== null, fn ($q) => $q->where('barber_membership_id', $barberMembershipId))
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->with(['customerProfile', 'items'])
            ->orderByRaw('COALESCE(priority_at, created_at) ASC')
            ->orderBy('sequence')
            ->get();
    }

    public function getServingTicketForBarber(?int $barberMembershipId): ?QueueTicket
    {
        if ($barberMembershipId === null) {
            return null;
        }

        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->where('barber_membership_id', $barberMembershipId)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->with(['customerProfile', 'items', 'hairColor'])
            ->orderByDesc('called_at')
            ->first();
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getWaitingQueueForBarberBranch(?int $branchId, ?int $barberMembershipId): EloquentCollection
    {
        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->where('status', QueueStatus::Waiting)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where(function ($q) use ($barberMembershipId): void {
                if ($barberMembershipId !== null) {
                    $q->where('barber_membership_id', $barberMembershipId)
                        ->orWhereNull('barber_membership_id');
                } else {
                    $q->whereNull('barber_membership_id');
                }
            })
            ->with(['customerProfile', 'items', 'hairColor'])
            ->orderBy('priority_at')
            ->orderBy('sequence')
            ->get();
    }

    public function countCompletedTodayForBarber(?int $barberMembershipId): int
    {
        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->when($barberMembershipId !== null, fn ($q) => $q->where('barber_membership_id', $barberMembershipId))
            ->where('status', QueueStatus::Completed)
            ->count();
    }

    public function findCalledTicketForDisplay(Tenant $tenant, int $branchId): ?QueueTicket
    {
        return QueueTicket::query()
            ->forTenant($tenant)
            ->where('branch_id', $branchId)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->with('barber')
            ->orderByDesc('called_at')
            ->first();
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getWaitingTicketsForDisplay(Tenant $tenant, int $branchId, int $limit = 6): EloquentCollection
    {
        return QueueTicket::query()
            ->forTenant($tenant)
            ->where('branch_id', $branchId)
            ->whereDate('date', now()->toDateString())
            ->where('status', QueueStatus::Waiting)
            ->orderBy('sequence')
            ->limit($limit)
            ->get();
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getActiveTicketsForBranchDisplay(Tenant $tenant, int $branchId): EloquentCollection
    {
        return QueueTicket::query()
            ->forTenant($tenant)
            ->where('branch_id', $branchId)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->with(['barber.user', 'items', 'hairColor'])
            ->orderByRaw('COALESCE(priority_at, created_at) ASC')
            ->orderBy('sequence')
            ->get();
    }

    public function findActiveTicketForCustomer(Tenant $tenant, ?string $ticketNumber, ?int $userId): ?QueueTicket
    {
        return QueueTicket::query()
            ->forTenant($tenant)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->when($ticketNumber !== null && $ticketNumber !== '', fn ($q) => $q->where('number', $ticketNumber))
            ->when(
                ($ticketNumber === null || $ticketNumber === '') && $userId !== null,
                fn ($q) => $q->whereHas('customerProfile', fn ($cp) => $cp->where('user_id', $userId)),
            )
            ->with(['barber.user', 'items', 'hairColor'])
            ->latest()
            ->first();
    }

    public function sumActiveEstimatedMinutes(int $tenantId, int $branchId, ?int $barberMembershipId = null): int
    {
        $query = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService]);

        if ($barberMembershipId !== null) {
            $query->where('barber_membership_id', $barberMembershipId);
        }

        return (int) $query->sum('estimated_duration_minutes');
    }

    public function countActiveBarbersInBranch(int $tenantId, int $branchId): int
    {
        return Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('role', Role::Barber)
            ->where('is_active', true)
            ->where('work_status', WorkStatus::Active)
            ->count();
    }
}
