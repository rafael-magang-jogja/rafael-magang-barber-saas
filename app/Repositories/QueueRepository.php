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
use App\Models\QueueTicketEvent;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
     * Otomatis membatalkan antrean aktif (walk-in / booking) dan reservasi booking dari hari-hari sebelumnya yang terlewat.
     *
     * @return array{cancelled_tickets: int, cancelled_bookings: int}
     */
    public function cancelExpiredQueuesAndBookings(?int $tenantId = null, ?int $branchId = null, bool $force = false): array
    {
        $today = now()->toDateString();
        $startOfToday = now()->startOfDay();
        $effectiveTenantId = $tenantId ?? app(CurrentTenant::class)->id();

        if (! $force) {
            $cacheKey = 'expired_queues_checked:' . ($effectiveTenantId ?? 'global') . ':' . $today;
            if (Cache::has($cacheKey)) {
                return ['cancelled_tickets' => 0, 'cancelled_bookings' => 0];
            }

            // Cek cepat tanpa transaksi/lock apakah ada baris yang benar-benar expired
            $hasExpiredTicket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->when($effectiveTenantId !== null, fn ($q) => $q->where('tenant_id', $effectiveTenantId))
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('date', '<', $today)
                ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
                ->exists();

            $hasExpiredBooking = ! $hasExpiredTicket && Booking::query()
                ->withoutGlobalScope('tenant')
                ->when($effectiveTenantId !== null, fn ($q) => $q->where('tenant_id', $effectiveTenantId))
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('scheduled_at', '<', $startOfToday)
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                ->exists();

            if (! $hasExpiredTicket && ! $hasExpiredBooking) {
                Cache::put($cacheKey, true, 300);

                return ['cancelled_tickets' => 0, 'cancelled_bookings' => 0];
            }
        }

        return DB::transaction(function () use ($today, $startOfToday, $effectiveTenantId, $branchId): array {
            // 1. Antrean yang tanggalnya sudah lewat (< hari ini) dan status masih waiting, called, atau in_service
            $expiredTickets = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->when($effectiveTenantId !== null, fn ($q) => $q->where('tenant_id', $effectiveTenantId))
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('date', '<', $today)
                ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
                ->lockForUpdate()
                ->get();

            $cancelledTicketCount = 0;
            $noteText = 'Batal Otomatis: Melewati Hari Operasional (Expired)';

            foreach ($expiredTickets as $ticket) {
                $oldStatus = $ticket->status->value;

                $newNotes = $ticket->notes
                    ? $ticket->notes . " ({$noteText})"
                    : $noteText;

                $ticket->update([
                    'status' => QueueStatus::Cancelled,
                    'notes' => $newNotes,
                    'version' => $ticket->version + 1,
                ]);

                // Jika tiket antrean ini berasal dari booking, batalkan juga bookingnya
                if ($ticket->booking_id !== null) {
                    Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($ticket->booking_id)
                        ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn])
                        ->update([
                            'status' => BookingStatus::Cancelled,
                            'cancel_reason' => $noteText,
                            'cancelled_at' => now(),
                        ]);
                }

                QueueTicketEvent::query()->create([
                    'queue_ticket_id' => $ticket->id,
                    'from_status' => $oldStatus,
                    'to_status' => QueueStatus::Cancelled->value,
                    'notes' => $noteText,
                    'created_at' => now(),
                ]);

                $cancelledTicketCount++;
            }

            // 2. Booking yang tanggal/jam scheduled_at sudah lewat (< awal hari ini) dan status masih pending / confirmed
            $expiredBookings = Booking::query()
                ->withoutGlobalScope('tenant')
                ->when($effectiveTenantId !== null, fn ($q) => $q->where('tenant_id', $effectiveTenantId))
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('scheduled_at', '<', $startOfToday)
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                ->lockForUpdate()
                ->get();

            $cancelledBookingCount = 0;
            $bookingCancelReason = 'Batal Otomatis: Jadwal Terlewat (No-Show)';

            foreach ($expiredBookings as $booking) {
                $booking->update([
                    'status' => BookingStatus::Cancelled,
                    'cancel_reason' => $bookingCancelReason,
                    'cancelled_at' => now(),
                ]);

                $cancelledBookingCount++;
            }

            return [
                'cancelled_tickets' => $cancelledTicketCount,
                'cancelled_bookings' => $cancelledBookingCount,
            ];
        });
    }

    /**
     * @return Collection<string, EloquentCollection<int, QueueTicket>>
     */
    public function getTodayTicketsGroupedByStatus(?string $date = null, ?int $branchId = null): Collection
    {
        $this->cancelExpiredQueuesAndBookings();

        $targetDate = $date ?? now()->toDateString();
        $isToday = ($targetDate === now()->toDateString());

        // Otomatis masukkan confirmed booking yang terjadwal hari ini ke antrean tiket
        app(\App\Services\QueueService::class)->autoEnrollConfirmedBookingsForToday($targetDate);

        return QueueTicket::query()
            ->whereDate('date', $targetDate)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($isToday, function ($q): void {
                // Untuk antrean hari ini di status waiting:
                // Tiket booking baru muncul 15 menit sebelum jam jadwal booking (priority_at <= now + 15 menit)
                // Tiket walk-in dan tiket non-waiting (called, in_service, completed) tetap tampil normal
                $q->where(function ($sub): void {
                    $sub->where('status', '!=', QueueStatus::Waiting->value)
                        ->orWhere(function ($w): void {
                            $w->whereNull('booking_id')
                                ->where('type', '!=', 'booking');
                        })
                        ->orWhere(function ($b): void {
                            $b->where(function ($typeSub) {
                                $typeSub->whereNotNull('booking_id')
                                    ->orWhere('type', 'booking');
                            })->where('priority_at', '<=', now()->addMinutes(15));
                        });
                });
            })
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
        $this->cancelExpiredQueuesAndBookings();

        app(\App\Services\QueueService::class)->autoEnrollConfirmedBookingsForToday(now()->toDateString());

        return QueueTicket::query()
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InService])
            ->where(function ($sub): void {
                $sub->where('status', '!=', QueueStatus::Waiting->value)
                    ->orWhere(function ($w): void {
                        $w->whereNull('booking_id')
                            ->where('type', '!=', 'booking');
                    })
                    ->orWhere(function ($b): void {
                        $b->where(function ($typeSub) {
                            $typeSub->whereNotNull('booking_id')
                                ->orWhere('type', 'booking');
                        })->where('priority_at', '<=', now()->addMinutes(15));
                    });
            })
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
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('status', BookingStatus::Confirmed)
            ->with(['customerProfile', 'barber.user', 'items'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Booking>
     */
    public function getAllPendingBookings(?int $branchId = null): EloquentCollection
    {
        $this->cancelExpiredQueuesAndBookings();

        return Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['customerProfile', 'barber.user', 'items', 'branch', 'queueTicket'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, QueueTicket>
     */
    public function getActiveQueueForBarber(?int $barberMembershipId): EloquentCollection
    {
        $this->cancelExpiredQueuesAndBookings();

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
            ->where(function ($sub): void {
                $sub->where('status', '!=', QueueStatus::Waiting->value)
                    ->orWhere(function ($w): void {
                        $w->whereNull('booking_id')
                            ->where('type', '!=', 'booking');
                    })
                    ->orWhere(function ($b): void {
                        $b->where(function ($typeSub) {
                            $typeSub->whereNotNull('booking_id')
                                ->orWhere('type', 'booking');
                        })->where('priority_at', '<=', now()->addMinutes(15));
                    });
            })
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
