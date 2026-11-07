<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueStatus;
use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\HairColor;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        bool $isCashierInput = false,
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
            $isCashierInput,
        ): QueueTicket {
            $now = CarbonImmutable::now();
            $today = $now->toDateString();

            // Keamanan jam operasional: khusus pemesanan mandiri oleh pelanggan (non-kasir),
            // pastikan cabang sedang buka dan tidak di luar jam operasional atau jam istirahat.
            if (! $isCashierInput) {
                $opCheck = $branch->checkOperationalStatus($now);
                if (! $opCheck['is_open']) {
                    throw new DomainException($opCheck['reason'] ?? 'Cabang sedang tutup atau di luar jam operasional.');
                }
            }

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

            if ($barberMembershipId !== null) {
                $hasActiveAttendance = Attendance::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $branch->tenant_id)
                    ->where('membership_id', $barberMembershipId)
                    ->whereDate('date', $today)
                    ->whereNotNull('check_in_at')
                    ->whereNull('check_out_at')
                    ->exists();

                if (! $hasActiveAttendance) {
                    throw new DomainException('Barber yang dipilih belum hadir / absen masuk atau sudah pulang hari ini.');
                }
            }

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
                    'is_cashier_input' => $isCashierInput,
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

            $targetDate = $lockedBooking->scheduled_at ? $lockedBooking->scheduled_at->toDateString() : CarbonImmutable::now()->toDateString();

            $counter = $this->queueRepository->lockOrCreateCounter($lockedBooking->tenant_id, $lockedBooking->branch_id, $targetDate, 'B');

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
                    'date' => $targetDate,
                    'sequence' => $nextSeq,
                    'number' => $ticketNumber,
                    'type' => 'booking',
                    'is_cashier_input' => false,
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
                $targetDate = $locked->date ? $locked->date->toDateString() : CarbonImmutable::now()->toDateString();

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

                        $hasCheckedIn = Attendance::query()
                            ->withoutGlobalScope('tenant')
                            ->where('membership_id', $locked->barber_membership_id)
                            ->whereDate('date', $targetDate)
                            ->whereNotNull('check_in_at')
                            ->whereNull('check_out_at')
                            ->exists();

                        if ($assignedBarber && $assignedBarber->work_status === WorkStatus::Active && $hasCheckedIn && ! $isBusy) {
                            $barberMembershipId = $assignedBarber->id;
                        } else {
                            $readyBarber = $this->findReadyBarberForBranch(
                                $locked->tenant_id,
                                $locked->branch_id,
                                $targetDate
                            );

                            if ($readyBarber === null) {
                                throw new DomainException('Barber yang dipilih belum absen atau sedang tidak tersedia, dan tidak ada staff lain yang ready di cabang ini.');
                            }

                            $barberMembershipId = $readyBarber->id;
                        }
                    } else {
                        $readyBarber = $this->findReadyBarberForBranch(
                            $locked->tenant_id,
                            $locked->branch_id,
                            $targetDate
                        );

                        if ($readyBarber === null) {
                            throw new DomainException('Tidak ada staff barber yang sedang ready dan sudah absen masuk di cabang ini. Pastikan staff sudah scan QR absensi.');
                        }

                        $barberMembershipId = $readyBarber->id;
                    }
                }

                // Validasi bahwa barber yang akan dipanggil wajib sudah absen masuk hari ini
                $hasCheckedIn = Attendance::query()
                    ->withoutGlobalScope('tenant')
                    ->where('membership_id', $barberMembershipId)
                    ->whereDate('date', $targetDate)
                    ->whereNotNull('check_in_at')
                    ->whereNull('check_out_at')
                    ->exists();

                if (! $hasCheckedIn) {
                    throw new DomainException('Staff barber belum melakukan absen masuk hari ini. Silakan scan QR absensi cabang terlebih dahulu sebelum mengambil antrean.');
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

            if ($targetStatus === QueueStatus::InService) {
                $activeBarberId = $barberMembershipId ?? $locked->barber_membership_id;
                if ($activeBarberId !== null) {
                    $targetDate = $locked->date ? $locked->date->toDateString() : CarbonImmutable::now()->toDateString();
                    $hasCheckedIn = Attendance::query()
                        ->withoutGlobalScope('tenant')
                        ->where('membership_id', $activeBarberId)
                        ->whereDate('date', $targetDate)
                        ->whereNotNull('check_in_at')
                        ->whereNull('check_out_at')
                        ->exists();

                    if (! $hasCheckedIn) {
                        throw new DomainException('Staff barber belum melakukan absen masuk hari ini. Silakan scan QR absensi cabang terlebih dahulu sebelum memulai layanan.');
                    }
                }
            }

            $oldStatus = $locked->status->value;
            $updates = [
                'status' => $targetStatus,
                'version' => $locked->version + 1,
            ];

            if ($barberMembershipId !== null) {
                $updates['barber_membership_id'] = $barberMembershipId;

                // Sinkronkan ke order jika order sudah dibayar/dibuat di muka
                $existingOrder = \App\Models\Order::query()
                    ->withoutGlobalScope('tenant')
                    ->where('queue_ticket_id', $locked->id)
                    ->first();

                if ($existingOrder !== null && $existingOrder->barber_membership_id === null) {
                    $existingOrder->update(['barber_membership_id' => $barberMembershipId]);
                    \App\Models\OrderItem::query()
                        ->withoutGlobalScope('tenant')
                        ->where('order_id', $existingOrder->id)
                        ->whereNull('barber_membership_id')
                        ->update(['barber_membership_id' => $barberMembershipId]);

                    if ($existingOrder->payment_status === \App\Enums\PaymentStatus::Paid) {
                        app(CommissionService::class)->recordOrderCommissions($existingOrder);
                    }
                }
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
                if ($locked->hair_color_id !== null && in_array($locked->status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
                    HairColor::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($locked->hair_color_id)
                        ->increment('stock');
                }
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
                    if ($locked->order !== null && $locked->order->payment_status === \App\Enums\PaymentStatus::Paid) {
                        $bookingUpdates['refund_status'] = 'pending';
                        $bookingUpdates['refund_amount'] = $locked->order->paid_total;
                        $bookingUpdates['refund_requested_at'] = now();
                    }
                    Booking::query()
                        ->withoutGlobalScope('tenant')
                        ->whereKey($locked->booking_id)
                        ->update($bookingUpdates);
                }

                if ($locked->order !== null && $locked->order->payment_status === \App\Enums\PaymentStatus::Paid) {
                    if (empty($locked->refund_status)) {
                        $updates['refund_status'] = 'pending';
                        $updates['refund_amount'] = $locked->order->paid_total;
                        $updates['refund_requested_at'] = now();
                    }
                    // Batalkan komisi barber agar tidak tercatat pada tiket yang dibatalkan
                    \App\Models\CommissionEntry::query()
                        ->withoutGlobalScope('tenant')
                        ->whereHas('orderItem', fn ($q) => $q->where('order_id', $locked->order->id))
                        ->delete();
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

        $checkedInBarberIds = Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $targetDate)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->pluck('membership_id')
            ->all();

        $availableBarbers = $barbers
            ->filter(fn (Membership $b) => in_array($b->id, $checkedInBarberIds, true))
            ->reject(fn (Membership $b) => in_array($b->id, $busyBarberIds, true));

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

            // Validasi: Antrean baru dapat dilewati setelah minimal 1 menit sejak waktu pemanggilan (toleransi 1 menit)
            if ($locked->called_at !== null) {
                $secondsSinceCalled = now()->diffInSeconds($locked->called_at);
                if ($secondsSinceCalled < 60) {
                    $remainingSeconds = 60 - $secondsSinceCalled;
                    throw new DomainException("Antrean baru dapat dilewati setelah 1 menit panggilan. Mohon tunggu {$remainingSeconds} detik lagi.");
                }
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

    /**
     * Cancel expired queue tickets and past unfulfilled bookings.
     *
     * @return array{cancelled_tickets: int, cancelled_bookings: int}
     */
    public function cancelExpiredQueuesAndBookings(?int $tenantId = null, ?int $branchId = null): array
    {
        return $this->queueRepository->cancelExpiredQueuesAndBookings($tenantId, $branchId);
    }

    /**
     * Otomatis mendaftarkan booking yang telah disetujui (Confirmed & Lunas) ke antrean hari ini
     * saat tanggal jadwal (hari H) tiba, sesuai jam prioritas kedatangannya.
     */
    public function autoEnrollConfirmedBookingsForToday(?string $date = null): void
    {
        $targetDate = $date ?? now()->toDateString();
        $confirmedBookings = Booking::query()
            ->withoutGlobalScope('tenant')
            ->whereDate('scheduled_at', $targetDate)
            ->where('status', BookingStatus::Confirmed)
            ->whereDoesntHave('queueTicket')
            ->with(['items'])
            ->get();

        foreach ($confirmedBookings as $booking) {
            try {
                DB::transaction(function () use ($booking): void {
                    $ticket = $this->checkInBooking($booking);
                    $this->createPaidOrderForBookingTicket($booking, $ticket);
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Auto-enroll booking gagal untuk booking ID {$booking->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Membuat Order Lunas dan Payment Transfer untuk antrean booking yang telah dibayar.
     */
    public function createPaidOrderForBookingTicket(Booking $booking, QueueTicket $ticket): Order
    {
        $existingOrder = Order::query()->where('queue_ticket_id', $ticket->id)->first();
        if ($existingOrder !== null) {
            return $existingOrder;
        }

        $orderTotal = (int) $ticket->items->sum('price');
        $order = Order::query()->create([
            'tenant_id' => $booking->tenant_id,
            'branch_id' => $booking->branch_id,
            'queue_ticket_id' => $ticket->id,
            'customer_profile_id' => $booking->customer_profile_id,
            'barber_membership_id' => $booking->barber_membership_id,
            'number' => 'ORD-' . strtoupper(Str::random(10)),
            'subtotal' => $orderTotal,
            'discount_total' => 0,
            'tip_amount' => 0,
            'grand_total' => $orderTotal,
            'paid_total' => $orderTotal,
            'refunded_total' => 0,
            'payment_status' => PaymentStatus::Paid,
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => $booking->payment_approved_at ?? now(),
        ]);

        foreach ($ticket->items as $item) {
            OrderItem::query()->create([
                'tenant_id' => $booking->tenant_id,
                'order_id' => $order->id,
                'service_id' => $item->service_id,
                'barber_membership_id' => $booking->barber_membership_id,
                'name' => $item->service_name,
                'type' => 'service',
                'qty' => 1,
                'unit_price' => $item->price,
                'discount' => 0,
                'line_total' => $item->price,
            ]);
        }

        $paymentMethod = (! empty($booking->xendit_payment_method) && strtolower((string) $booking->xendit_payment_method) === 'qris')
            ? PaymentMethod::Qris->value
            : PaymentMethod::Transfer->value;

        $reference = ! empty($booking->xendit_external_id)
            ? (string) $booking->xendit_external_id
            : 'TRANSFER-REK-' . $booking->code;

        Payment::query()->create([
            'tenant_id' => $booking->tenant_id,
            'order_id' => $order->id,
            'amount' => $orderTotal,
            'method' => $paymentMethod,
            'reference' => $reference,
            'status' => 'success',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return $order;
    }
}
