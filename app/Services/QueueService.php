<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\QueueTicketItem;
use App\Models\Service;
use App\Repositories\BookingRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\QueueRepository;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class QueueService
{
    /**
     * Valid state transitions for queue tickets.
     *
     * @var array<string, list<QueueStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'waiting' => [QueueStatus::Called, QueueStatus::NoShow, QueueStatus::Cancelled],
        'called' => [QueueStatus::InService, QueueStatus::NoShow, QueueStatus::Cancelled],
        'no_show' => [QueueStatus::Called, QueueStatus::Cancelled],
        'in_service' => [QueueStatus::Completed, QueueStatus::Cancelled],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly QueueRepository $queueRepository,
        private readonly CatalogRepository $catalogRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a sequential walk-in ticket with row-level lock on queue_counters.
     *
     * @param  array<int, int>  $serviceIds
     */
    public function createWalkInTicket(
        Branch $branch,
        string $customerName,
        ?string $customerPhone = null,
        array $serviceIds = [],
        ?int $barberMembershipId = null,
        ?string $notes = null,
        ?int $actorUserId = null,
        ?int $hairColorId = null,
    ): QueueTicket {
        return DB::transaction(function () use (
            $branch,
            $customerName,
            $customerPhone,
            $serviceIds,
            $barberMembershipId,
            $notes,
            $actorUserId,
            $hairColorId,
        ): QueueTicket {
            $today = CarbonImmutable::now()->toDateString();

            $services = $serviceIds !== []
                ? $this->catalogRepository->getActiveServicesByIdsForTenant($branch->tenant_id, $serviceIds)
                : Service::query()
                    ->withoutGlobalScope('tenant')
                    ->with('category')
                    ->where('tenant_id', $branch->tenant_id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit(1)
                    ->get();

            if ($services->isEmpty()) {
                throw new DomainException('Pilih minimal 1 layanan yang aktif.');
            }

            $hasColoringService = $services->contains(
                fn (Service $s): bool => stripos($s->name, 'color') !== false
                    || stripos($s->name, 'warna') !== false
                    || ($s->category && (stripos($s->category->name, 'warna') !== false || stripos($s->category->name, 'color') !== false))
            );

            $selectedColor = null;
            if ($hasColoringService && $hairColorId !== null) {
                $selectedColor = $this->catalogRepository->findAvailableHairColorForTenant(
                    tenantId: $branch->tenant_id,
                    hairColorId: $hairColorId,
                    lockForUpdate: true,
                );

                if ($selectedColor === null || $selectedColor->stock <= 0) {
                    throw new DomainException('Warna coloring yang dipilih sedang habis atau tidak tersedia.');
                }

                $selectedColor->decrement('stock');
            }

            $counter = $this->queueRepository->lockOrCreateCounter($branch->tenant_id, $branch->id, $today, 'A');

            $nextSeq = $counter->last_number + 1;
            $counter->update(['last_number' => $nextSeq]);

            $ticketNumber = sprintf('A-%03d', $nextSeq);

            $customerProfile = $this->customerRepository->firstOrCreateByPhone(
                tenantId: $branch->tenant_id,
                phone: $customerPhone,
                name: $customerName,
                userId: $actorUserId,
            );

            $estimatedDuration = (int) $services->sum('duration_minutes');

            $ticket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'customer_profile_id' => $customerProfile->id,
                    'barber_membership_id' => $barberMembershipId,
                    'hair_color_id' => $selectedColor?->id,
                    'requested_barber_id' => $barberMembershipId,
                    'date' => $today,
                    'sequence' => $nextSeq,
                    'number' => $ticketNumber,
                    'type' => 'walk_in',
                    'status' => QueueStatus::Waiting,
                    'priority_at' => now(),
                    'estimated_duration_minutes' => $estimatedDuration,
                    'notes' => $notes,
                    'version' => 1,
                ]);

            /** @var Service $service */
            foreach ($services as $service) {
                $isColorService = stripos($service->name, 'color') !== false
                    || stripos($service->name, 'warna') !== false
                    || ($service->category && (stripos($service->category->name, 'warna') !== false || stripos($service->category->name, 'color') !== false));
                $itemName = ($isColorService && $selectedColor !== null)
                    ? "{$service->name} ({$selectedColor->name})"
                    : $service->name;

                QueueTicketItem::query()->create([
                    'queue_ticket_id' => $ticket->id,
                    'service_id' => $service->id,
                    'service_name' => $itemName,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                ]);
            }

            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'from_status' => null,
                'to_status' => QueueStatus::Waiting->value,
                'actor_user_id' => $actorUserId,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'queue_ticket.created',
                auditable: $ticket,
                newValues: ['number' => $ticket->number, 'type' => 'walk_in'],
                userId: $actorUserId,
            );

            return $ticket->load(['items', 'customerProfile', 'barber']);
        });
    }

    /**
     * Check-in a confirmed booking and issue a priority B-xxx queue ticket.
     */
    public function checkInBooking(Booking $booking, ?int $actorUserId = null, ?int $hairColorId = null): QueueTicket
    {
        return DB::transaction(function () use ($booking, $actorUserId, $hairColorId): QueueTicket {
            $lockedBooking = $this->bookingRepository->lockBookingByIdOrFail($booking->id);

            if ($lockedBooking->status !== BookingStatus::Confirmed) {
                throw new DomainException('Hanya booking berstatus Confirmed yang dapat check-in.');
            }

            $resolvedColorId = $hairColorId ?? $lockedBooking->hair_color_id;
            $selectedColor = null;
            if ($resolvedColorId !== null) {
                $selectedColor = $this->catalogRepository->findAvailableHairColorForTenant(
                    tenantId: $lockedBooking->tenant_id,
                    hairColorId: $resolvedColorId,
                    lockForUpdate: true,
                );

                if ($selectedColor !== null && $selectedColor->stock > 0) {
                    $selectedColor->decrement('stock');
                }
            }

            $today = CarbonImmutable::now()->toDateString();

            $counter = $this->queueRepository->lockOrCreateCounter($lockedBooking->tenant_id, $lockedBooking->branch_id, $today, 'B');

            $nextSeq = $counter->last_number + 1;
            $counter->update(['last_number' => $nextSeq]);

            $ticketNumber = sprintf('B-%03d', $nextSeq);

            $lockedBooking->update([
                'status' => BookingStatus::CheckedIn,
                'hair_color_id' => $selectedColor?->id ?? $lockedBooking->hair_color_id,
            ]);

            $ticket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->create([
                    'tenant_id' => $lockedBooking->tenant_id,
                    'branch_id' => $lockedBooking->branch_id,
                    'booking_id' => $lockedBooking->id,
                    'customer_profile_id' => $lockedBooking->customer_profile_id,
                    'barber_membership_id' => $lockedBooking->barber_membership_id,
                    'hair_color_id' => $selectedColor?->id ?? $lockedBooking->hair_color_id,
                    'requested_barber_id' => $lockedBooking->barber_membership_id,
                    'date' => $today,
                    'sequence' => $nextSeq,
                    'number' => $ticketNumber,
                    'type' => 'booking',
                    'status' => QueueStatus::Waiting,
                    'priority_at' => $lockedBooking->scheduled_at ?? now(),
                    'estimated_duration_minutes' => (int) $lockedBooking->items->sum('duration_minutes'),
                    'notes' => $lockedBooking->notes,
                    'version' => 1,
                ]);

            foreach ($lockedBooking->items as $item) {
                $isColorService = stripos($item->service_name, 'color') !== false || stripos($item->service_name, 'warna') !== false;
                $itemName = $item->service_name;
                if ($isColorService && $selectedColor !== null && ! str_contains($itemName, '(')) {
                    $itemName = "{$item->service_name} ({$selectedColor->name})";
                }

                QueueTicketItem::query()->create([
                    'queue_ticket_id' => $ticket->id,
                    'service_id' => $item->service_id,
                    'service_name' => $itemName,
                    'price' => $item->price,
                    'duration_minutes' => $item->duration_minutes,
                ]);
            }

            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'from_status' => null,
                'to_status' => QueueStatus::Waiting->value,
                'actor_user_id' => $actorUserId,
                'created_at' => now(),
            ]);

            return $ticket->load(['items', 'customerProfile', 'barber']);
        });
    }

    /**
     * Transition a ticket status safely with row lock & version increment.
     */
    public function transitionStatus(
        QueueTicket $ticket,
        QueueStatus $targetStatus,
        ?int $barberMembershipId = null,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): QueueTicket {
        return DB::transaction(function () use ($ticket, $targetStatus, $barberMembershipId, $actorUserId, $reason): QueueTicket {
            $locked = $this->queueRepository->lockTicketByIdOrFail($ticket->id);

            $currentKey = $locked->status->value;
            $allowed = self::ALLOWED_TRANSITIONS[$currentKey] ?? [];

            if (! in_array($targetStatus, $allowed, true)) {
                throw new DomainException("Transisi status antrean dari {$currentKey} ke {$targetStatus->value} tidak diizinkan.");
            }

            if ($targetStatus === QueueStatus::Called) {
                if ($barberMembershipId === null) {
                    if ($locked->barber_membership_id !== null) {
                        $assignedBarber = Membership::query()
                            ->withoutGlobalScope('tenant')
                            ->find($locked->barber_membership_id);

                        $isBusy = QueueTicket::query()
                            ->where('id', '!=', $locked->id)
                            ->where('barber_membership_id', $locked->barber_membership_id)
                            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
                            ->whereDate('date', $locked->date ?? now()->toDateString())
                            ->exists();

                        if ($assignedBarber && $assignedBarber->work_status === WorkStatus::Active && ! $isBusy) {
                            $barberMembershipId = $assignedBarber->id;
                        } else {
                            $readyBarber = $this->findReadyBarberForBranch(
                                $locked->tenant_id,
                                $locked->branch_id,
                                $locked->date ? $locked->date->toDateString() : null
                            );

                            if ($readyBarber === null) {
                                throw new DomainException('Barber yang dipilih sedang tidak tersedia dan tidak ada staff lain yang ready di cabang ini.');
                            }

                            $barberMembershipId = $readyBarber->id;
                        }
                    } else {
                        $readyBarber = $this->findReadyBarberForBranch(
                            $locked->tenant_id,
                            $locked->branch_id,
                            $locked->date ? $locked->date->toDateString() : null
                        );

                        if ($readyBarber === null) {
                            throw new DomainException('Tidak ada staff barber yang sedang ready di cabang ini. Pastikan ada staff yang berstatus Ready dan telah menyelesaikan tugas sebelumnya.');
                        }

                        $barberMembershipId = $readyBarber->id;
                    }
                }

                $busyTicket = QueueTicket::query()
                    ->where('id', '!=', $locked->id)
                    ->where('barber_membership_id', $barberMembershipId)
                    ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
                    ->whereDate('date', $locked->date ?? now()->toDateString())
                    ->first();

                if ($busyTicket !== null) {
                    throw new DomainException("Barber sedang melayani antrean {$busyTicket->number}. Selesaikan tugas sebelumnya terlebih dahulu sebelum memanggil antrean berikutnya.");
                }
            }

            $oldStatus = $locked->status->value;
            $updates = [
                'status' => $targetStatus,
                'version' => $locked->version + 1,
            ];

            if ($barberMembershipId !== null) {
                $updates['barber_membership_id'] = $barberMembershipId;
            }

            if ($targetStatus === QueueStatus::Called) {
                $updates['called_at'] = now();
            } elseif ($targetStatus === QueueStatus::InService) {
                $updates['started_at'] = now();
            } elseif ($targetStatus === QueueStatus::Completed) {
                $updates['completed_at'] = now();
                if ($locked->booking_id !== null) {
                    Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($locked->booking_id)
                        ->update(['status' => BookingStatus::Completed]);
                }
            } elseif ($targetStatus === QueueStatus::Cancelled) {
                if ($reason !== null && trim($reason) !== '') {
                    $cleanReason = trim($reason);
                    $updates['notes'] = $locked->notes ? $locked->notes . " (Batal: {$cleanReason})" : "Batal: {$cleanReason}";
                }
                if ($locked->booking_id !== null) {
                    $bookingUpdates = ['status' => BookingStatus::Cancelled];
                    if ($reason !== null && trim($reason) !== '') {
                        $bookingUpdates['cancel_reason'] = trim($reason);
                        $bookingUpdates['cancelled_at'] = now();
                        $bookingUpdates['cancelled_by'] = $actorUserId;
                    }
                    Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($locked->booking_id)
                        ->update($bookingUpdates);
                }
            }

            $locked->update($updates);

            if ($targetStatus === QueueStatus::Cancelled) {
                $this->resequenceWaitingTickets(
                    $locked->tenant_id,
                    $locked->branch_id,
                    (string) ($locked->date ?? now()->toDateString())
                );
            }

            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $locked->id,
                'from_status' => $oldStatus,
                'to_status' => $targetStatus->value,
                'actor_user_id' => $actorUserId,
                'notes' => $reason,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'queue_ticket.status_changed',
                auditable: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $targetStatus->value],
                userId: $actorUserId,
            );

            return $locked->fresh(['items', 'customerProfile', 'barber']);
        });
    }

    /**
     * Estimate wait time in minutes for a branch or specific barber.
     */
    public function estimateWaitMinutes(Branch $branch, ?int $barberMembershipId = null): int
    {
        $totalMinutes = $this->queueRepository->sumActiveEstimatedMinutes(
            tenantId: $branch->tenant_id,
            branchId: $branch->id,
            barberMembershipId: $barberMembershipId,
        );

        if ($barberMembershipId !== null) {
            return $totalMinutes;
        }

        $activeBarbers = max(1, $this->queueRepository->countActiveBarbersInBranch($branch->tenant_id, $branch->id));

        return (int) ceil($totalMinutes / $activeBarbers);
    }

    /**
     * Cari staff barber yang status kerjanya Ready (Active) dan tidak sedang menangani antrean aktif hari ini.
     * Jika ada beberapa yang ready, pilih yang memiliki total antrean terendah hari ini (distribusi adil).
     */
    public function findReadyBarberForBranch(int $tenantId, int $branchId, ?string $date = null): ?Membership
    {
        $targetDate = $date ?? CarbonImmutable::now()->toDateString();

        $barbers = Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('role', Role::Barber)
            ->where(function ($q) use ($branchId): void {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })
            ->where('work_status', WorkStatus::Active)
            ->get();

        if ($barbers->isEmpty()) {
            return null;
        }

        $busyBarberIds = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereDate('date', $targetDate)
            ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
            ->whereNotNull('barber_membership_id')
            ->pluck('barber_membership_id')
            ->all();

        $availableBarbers = $barbers->reject(fn (Membership $b) => in_array($b->id, $busyBarberIds, true));

        if ($availableBarbers->isEmpty()) {
            return null;
        }

        $barberTicketCounts = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $targetDate)
            ->whereIn('barber_membership_id', $availableBarbers->pluck('id'))
            ->selectRaw('barber_membership_id, count(*) as total')
            ->groupBy('barber_membership_id')
            ->pluck('total', 'barber_membership_id')
            ->all();

        return $availableBarbers->sortBy(fn (Membership $b) => $barberTicketCounts[$b->id] ?? 0)->first();
    }

    /**
     * Reschedule queue ticket and its linked booking (if any) to a new date/time or barber.
     * Automatically updates priority_at and re-sequences the waiting queue.
     */
    public function rescheduleTicket(
        QueueTicket $ticket,
        CarbonImmutable $newScheduledAt,
        ?int $barberMembershipId = null,
        ?string $reason = null,
        ?int $actorUserId = null,
    ): QueueTicket {
        return DB::transaction(function () use (
            $ticket,
            $newScheduledAt,
            $barberMembershipId,
            $reason,
            $actorUserId,
        ): QueueTicket {
            $lockedTicket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if (in_array($lockedTicket->status, [QueueStatus::Completed, QueueStatus::Cancelled], true)) {
                throw new DomainException('Antrean yang sudah selesai atau dibatalkan tidak dapat di-reschedule.');
            }

            $newDate = $newScheduledAt->toDateString();
            $oldDate = $lockedTicket->date ? $lockedTicket->date->toDateString() : null;
            $isDifferentDay = $oldDate !== $newDate;

            // Jika statusnya 'called', kembalikan ke 'waiting' agar antrean lain dapat dipanggil
            $targetStatus = QueueStatus::Waiting;
            $oldStatus = $lockedTicket->status->value;

            $ticketUpdates = [
                'priority_at' => $newScheduledAt,
                'date' => $newDate,
                'status' => $targetStatus,
            ];

            if ($barberMembershipId !== null) {
                $ticketUpdates['barber_membership_id'] = $barberMembershipId;
                $ticketUpdates['requested_barber_id'] = $barberMembershipId;
            }

            if ($reason !== null && trim($reason) !== '') {
                $cleanReason = trim($reason);
                $ticketUpdates['notes'] = $lockedTicket->notes
                    ? $lockedTicket->notes . ' | Reschedule: ' . $cleanReason
                    : 'Reschedule: ' . $cleanReason;
            }

            // Jika pindah hari, alokasikan sequence nomor antrean untuk tanggal baru tersebut
            if ($isDifferentDay) {
                $typePrefix = $lockedTicket->type === 'booking' ? 'B' : 'A';
                $counter = $this->queueRepository->lockOrCreateCounter(
                    $lockedTicket->tenant_id,
                    $lockedTicket->branch_id,
                    $newDate,
                    $typePrefix
                );
                $nextSeq = $counter->last_number + 1;
                $counter->update(['last_number' => $nextSeq]);

                $ticketUpdates['sequence'] = $nextSeq;
                $ticketUpdates['number'] = sprintf('%s-%03d', $typePrefix, $nextSeq);
            }

            $lockedTicket->update($ticketUpdates);

            // Jika tiket ini terhubung dengan Booking, perbarui juga data Booking tersebut
            if ($lockedTicket->booking_id !== null) {
                $booking = Booking::query()
                    ->withoutGlobalScope('tenant')
                    ->lockForUpdate()
                    ->find($lockedTicket->booking_id);

                if ($booking !== null) {
                    $duration = $booking->total_duration_minutes ?? $lockedTicket->estimated_duration_minutes ?? 45;
                    $bookingUpdates = [
                        'scheduled_at' => $newScheduledAt,
                        'estimated_end_at' => $newScheduledAt->addMinutes($duration),
                        'status' => $isDifferentDay ? BookingStatus::Confirmed : BookingStatus::CheckedIn,
                    ];

                    if ($barberMembershipId !== null) {
                        $bookingUpdates['barber_membership_id'] = $barberMembershipId;
                    }

                    if ($reason !== null && trim($reason) !== '') {
                        $cleanReason = trim($reason);
                        $bookingUpdates['notes'] = $booking->notes
                            ? $booking->notes . ' | Reschedule: ' . $cleanReason
                            : 'Reschedule: ' . $cleanReason;
                    }

                    $booking->update($bookingUpdates);
                }
            }

            // Sinkronisasi urutan (sequence) antrean waiting agar tersusun berurutan sesuai priority_at
            $this->resequenceWaitingTickets(
                $lockedTicket->tenant_id,
                $lockedTicket->branch_id,
                $newDate
            );

            if ($isDifferentDay && $oldDate !== null) {
                $this->resequenceWaitingTickets(
                    $lockedTicket->tenant_id,
                    $lockedTicket->branch_id,
                    $oldDate
                );
            }

            // Catat riwayat pergerakan antrean
            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'from_status' => $oldStatus,
                'to_status' => $targetStatus->value,
                'actor_user_id' => $actorUserId,
                'notes' => 'Reschedule jadwal ke ' . $newScheduledAt->format('Y-m-d H:i') . ($reason ? ' - ' . trim($reason) : ''),
            ]);

            $this->auditLogger->log(
                action: 'queue.reschedule',
                auditable: $lockedTicket,
                oldValues: ['priority_at' => $ticket->priority_at?->toDateTimeString(), 'date' => $oldDate],
                newValues: ['priority_at' => $newScheduledAt->toDateTimeString(), 'date' => $newDate],
                tenantId: $lockedTicket->tenant_id,
                userId: $actorUserId,
            );

            return $lockedTicket->fresh(['customerProfile', 'barber.user', 'branch', 'items']);
        });
    }

    /**
     * Resequence waiting tickets on a specific branch and date so sequence numbers (1, 2, 3...)
     * strictly match the chronological scheduled priority_at order.
     */
    public function resequenceWaitingTickets(int $tenantId, int $branchId, string $date): void
    {
        $waitingTickets = QueueTicket::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->where('status', QueueStatus::Waiting)
            ->orderByRaw('COALESCE(priority_at, created_at) ASC')
            ->orderBy('id', 'ASC')
            ->get();

        foreach ($waitingTickets as $index => $t) {
            $newSeq = $index + 1;
            if ($t->sequence !== $newSeq) {
                $t->update(['sequence' => $newSeq]);
            }
        }
    }

    /**
     * Panggil ulang antrean yang sedang dipanggil ke TV Display (memperbarui timestamp called_at).
     */
    public function recallTicket(QueueTicket $ticket, ?int $actorUserId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $actorUserId): QueueTicket {
            $locked = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($locked->status !== QueueStatus::Called) {
                throw new DomainException('Hanya antrean yang sedang dipanggil yang dapat dipanggil ulang.');
            }

            $locked->update([
                'called_at' => now(),
                'version' => $locked->version + 1,
            ]);

            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $locked->id,
                'from_status' => QueueStatus::Called->value,
                'to_status' => QueueStatus::Called->value,
                'actor_user_id' => $actorUserId,
                'notes' => 'Panggilan ulang antrean ke TV Display',
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'queue.recall',
                auditable: $locked,
                oldValues: [],
                newValues: ['called_at' => now()->toDateTimeString()],
                tenantId: $locked->tenant_id,
                userId: $actorUserId,
            );

            return $locked->fresh(['customerProfile', 'barber.user', 'branch', 'items']);
        });
    }

    /**
     * Lewati antrean (Skip).
     * Jika panggilan ke-1: Mundur 1 posisi antrean (status kembali ke Waiting, skip_count = 1).
     * Jika panggilan ke-2: Dibatalkan otomatis (status Cancelled, skip_count = 2).
     *
     * @return array{ticket: QueueTicket, is_cancelled: bool, skip_count: int}
     */
    public function skipTicket(QueueTicket $ticket, ?int $actorUserId = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($ticket, $actorUserId, $reason): array {
            $locked = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($locked->status !== QueueStatus::Called) {
                throw new DomainException('Hanya antrean yang sedang dipanggil yang dapat dilewati.');
            }

            $currentSkipCount = (int) $locked->skip_count;
            $oldStatus = $locked->status->value;
            $targetDate = (string) ($locked->date ? $locked->date->toDateString() : now()->toDateString());

            // JIKA PANGGILAN KEDUA (skip_count >= 1): Terapkan hukuman pembatalan otomatis
            if ($currentSkipCount >= 1) {
                $cancelNote = 'Dibatalkan otomatis: 2x tidak hadir saat dipanggil' . ($reason ? ' - ' . trim($reason) : '');
                $updates = [
                    'status' => QueueStatus::Cancelled,
                    'skip_count' => $currentSkipCount + 1,
                    'notes' => $locked->notes ? $locked->notes . ' | ' . $cancelNote : $cancelNote,
                    'version' => $locked->version + 1,
                ];

                if ($locked->booking_id !== null) {
                    Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($locked->booking_id)
                        ->update([
                            'status' => BookingStatus::Cancelled,
                            'cancel_reason' => $cancelNote,
                            'cancelled_at' => now(),
                            'cancelled_by' => $actorUserId,
                        ]);
                }

                $locked->update($updates);

                $this->resequenceWaitingTickets(
                    $locked->tenant_id,
                    $locked->branch_id,
                    $targetDate
                );

                QueueTicketEvent::query()->create([
                    'queue_ticket_id' => $locked->id,
                    'from_status' => $oldStatus,
                    'to_status' => QueueStatus::Cancelled->value,
                    'actor_user_id' => $actorUserId,
                    'notes' => $cancelNote,
                    'created_at' => now(),
                ]);

                $this->auditLogger->log(
                    action: 'queue.skip_cancelled',
                    auditable: $locked,
                    oldValues: ['status' => $oldStatus, 'skip_count' => $currentSkipCount],
                    newValues: ['status' => QueueStatus::Cancelled->value, 'skip_count' => $currentSkipCount + 1],
                    tenantId: $locked->tenant_id,
                    userId: $actorUserId,
                );

                return [
                    'ticket' => $locked->fresh(['customerProfile', 'barber.user', 'branch', 'items']),
                    'is_cancelled' => true,
                    'skip_count' => $currentSkipCount + 1,
                ];
            }

            // JIKA PANGGILAN PERTAMA (skip_count == 0): Mundur 1 nomor antrean
            $waitingTickets = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $locked->tenant_id)
                ->where('branch_id', $locked->branch_id)
                ->whereDate('date', $targetDate)
                ->where('status', QueueStatus::Waiting)
                ->orderByRaw('COALESCE(priority_at, created_at) ASC')
                ->orderBy('sequence')
                ->get();

            $firstWaiting = $waitingTickets->first();
            $secondWaiting = $waitingTickets->get(1);

            if ($firstWaiting !== null) {
                $firstPriority = $firstWaiting->priority_at ?? $firstWaiting->created_at ?? now();

                if ($secondWaiting !== null) {
                    $secondPriority = $secondWaiting->priority_at ?? $secondWaiting->created_at ?? now();
                    if ($secondPriority->gt($firstPriority)) {
                        $diffSeconds = (int) $firstPriority->diffInSeconds($secondPriority);
                        $newPriority = $firstPriority->copy()->addSeconds(max(1, (int) round($diffSeconds / 2)));
                    } else {
                        $newPriority = $firstPriority->copy()->addSecond();
                        $secondWaiting->update([
                            'priority_at' => $firstPriority->copy()->addSeconds(2),
                        ]);
                    }
                } else {
                    $newPriority = $firstPriority->copy()->addSecond();
                }
            } else {
                $newPriority = now()->addSeconds(30);
            }

            $skipNote = 'Dilewati 1x (mundur 1 posisi antrean)' . ($reason ? ' - ' . trim($reason) : '');
            $locked->update([
                'status' => QueueStatus::Waiting,
                'skip_count' => 1,
                'priority_at' => $newPriority,
                'called_at' => null,
                'notes' => $locked->notes ? $locked->notes . ' | ' . $skipNote : $skipNote,
                'version' => $locked->version + 1,
            ]);

            $this->resequenceWaitingTickets(
                $locked->tenant_id,
                $locked->branch_id,
                $targetDate
            );

            QueueTicketEvent::query()->create([
                'queue_ticket_id' => $locked->id,
                'from_status' => $oldStatus,
                'to_status' => QueueStatus::Waiting->value,
                'actor_user_id' => $actorUserId,
                'notes' => $skipNote,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'queue.skip_shifted',
                auditable: $locked,
                oldValues: ['status' => $oldStatus, 'skip_count' => 0],
                newValues: ['status' => QueueStatus::Waiting->value, 'skip_count' => 1],
                tenantId: $locked->tenant_id,
                userId: $actorUserId,
            );

            return [
                'ticket' => $locked->fresh(['customerProfile', 'barber.user', 'branch', 'items']),
                'is_cancelled' => false,
                'skip_count' => 1,
            ];
        });
    }
}
