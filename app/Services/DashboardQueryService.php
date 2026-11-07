<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\WorkStatus;
use App\Models\QueueTicket;
use App\Repositories\BookingRepository;
use App\Repositories\BranchRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PlatformRepository;
use App\Repositories\QueueRepository;
use App\Repositories\StaffRepository;
use App\Support\Tenancy\CurrentTenant;

final class DashboardQueryService
{
    public function __construct(
        private readonly PlatformRepository $platformRepository,
        private readonly BranchRepository $branchRepository,
        private readonly StaffRepository $staffRepository,
        private readonly CatalogRepository $catalogRepository,
        private readonly QueueRepository $queueRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly OrderRepository $orderRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getPlatformDashboardData(): array
    {
        $totalGmv = \App\Models\Order::query()->withoutGlobalScope('tenant')->where('payment_status', 'paid')->sum('grand_total');
        $totalOrders = \App\Models\Order::query()->withoutGlobalScope('tenant')->count();
        $totalBranches = \App\Models\Branch::query()->withoutGlobalScope('tenant')->count();

        return [
            'tenants' => $this->platformRepository->getAllTenantsWithRelations(),
            'plans' => $this->platformRepository->getActivePlans(),
            'totalGmv' => (int) $totalGmv,
            'totalOrders' => (int) $totalOrders,
            'totalBranches' => (int) $totalBranches,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOwnerDashboardData(): array
    {
        return [
            'todayRevenue' => $this->orderRepository->getTodayRevenue(),
            'customersToday' => $this->orderRepository->getCustomersTodayCount(),
            'activeBookings' => $this->bookingRepository->countActiveBookingsToday(),
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'latestOrders' => $this->orderRepository->getLatestOrders(6),
            'draftPayroll' => $this->orderRepository->getDraftPayrollPeriod(),
            'branchesCount' => $this->branchRepository->getAllBranches()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierBoardData(?int $userId = null, ?string $selectedDate = null): array
    {
        $targetDate = $selectedDate ?? now()->toDateString();
        $tenantId = app(CurrentTenant::class)->id();
        $branches = $this->branchRepository->getActiveBranches();
        $todayAttendance = null;
        $branchQrToken = null;
        $cashierBranchId = null;

        if ($userId !== null) {
            $membership = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($membership !== null) {
                $cashierBranchId = $membership->branch_id;

                $todayAttendance = \App\Models\Attendance::query()
                    ->withoutGlobalScope('tenant')
                    ->where('membership_id', $membership->id)
                    ->whereDate('date', now()->format('Y-m-d'))
                    ->first();

                $branchQrToken = $membership->branch?->qr_token ?? $branches->first()?->qr_token;
            }
        }

        if ($cashierBranchId !== null) {
            $branches = $branches->where('id', $cashierBranchId)->values();
        }

        if ($branchQrToken === null) {
            $branchQrToken = $branches->first()?->qr_token;
        }

        $allBarbers = $this->staffRepository->getActiveBarbers(withRelations: true);
        if ($cashierBranchId !== null) {
            $allBarbers = $allBarbers->where('branch_id', $cashierBranchId)->values();
        }

        // Hanya barber yang sudah absen masuk dan belum absen pulang pada targetDate
        $presentBarberMembershipIds = \App\Models\Attendance::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('date', $targetDate)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->pluck('membership_id')
            ->all();

        $presentBarbers = $allBarbers->filter(fn ($b) => in_array($b->id, $presentBarberMembershipIds, true))->values();

        $todayStart = \Illuminate\Support\Carbon::parse($targetDate)->startOfDay();
        $todayEnd = \Illuminate\Support\Carbon::parse($targetDate)->endOfDay();

        $pendingRefundBookings = \App\Models\Booking::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($cashierBranchId !== null, fn ($q) => $q->where('branch_id', $cashierBranchId))
            ->where('refund_status', 'pending')
            ->with(['customerProfile', 'barber.user', 'items', 'branch', 'queueTicket'])
            ->orderByDesc('updated_at')
            ->get();

        $upcomingTodayBookings = \App\Models\Booking::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($cashierBranchId !== null, fn ($q) => $q->where('branch_id', $cashierBranchId))
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->whereIn('status', [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::Pending])
            ->whereDoesntHave('queueTicket')
            ->with(['customerProfile', 'barber.user', 'items', 'branch', 'queueTicket'])
            ->orderBy('scheduled_at')
            ->get();

        $futureBookings = \App\Models\Booking::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($cashierBranchId !== null, fn ($q) => $q->where('branch_id', $cashierBranchId))
            ->where('scheduled_at', '>', $todayEnd)
            ->whereIn('status', [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::Pending])
            ->whereDoesntHave('queueTicket')
            ->with(['customerProfile', 'barber.user', 'items', 'branch', 'queueTicket'])
            ->orderBy('scheduled_at')
            ->get();

        return [
            'tickets' => $this->queueRepository->getTodayTicketsGroupedByStatus($targetDate, $cashierBranchId),
            'branches' => $branches,
            'cashierBranchId' => $cashierBranchId,
            'barbers' => $allBarbers,
            'presentBarbers' => $presentBarbers,
            'pendingRefundBookings' => $pendingRefundBookings,
            'upcomingTodayBookings' => $upcomingTodayBookings,
            'futureBookings' => $futureBookings,
            'futureBookingsCount' => $futureBookings->count(),
            'activeTab' => 'board',
            'todayAttendance' => $todayAttendance,
            'branchQrToken' => $branchQrToken,
            'selectedDate' => $targetDate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierWalkInData(?int $userId = null): array
    {
        $today = now()->toDateString();
        $tenantId = app(CurrentTenant::class)->id();
        $branches = $this->branchRepository->getActiveBranches();

        $cashierBranchId = null;
        if ($userId !== null) {
            $membership = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($membership !== null && $membership->branch_id !== null) {
                $cashierBranchId = $membership->branch_id;
                $branches = $branches->where('id', $cashierBranchId)->values();
            }
        }

        $allBarbers = $this->staffRepository->getActiveBarbers(withRelations: true);
        if ($cashierBranchId !== null) {
            $allBarbers = $allBarbers->where('branch_id', $cashierBranchId)->values();
        }

        $presentBarberMembershipIds = \App\Models\Attendance::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('date', $today)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->pluck('membership_id')
            ->all();

        $presentBarbers = $allBarbers
            ->filter(fn ($b) => in_array($b->id, $presentBarberMembershipIds, true))
            ->values();

        return [
            'branches' => $branches,
            'cashierBranchId' => $cashierBranchId,
            'services' => $this->catalogRepository->getActiveServices(),
            'hairColors' => $this->catalogRepository->getAvailableHairColors(),
            'barbers' => $presentBarbers,
            'activeTab' => 'walkin',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierBookingsData(?int $userId = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();
        $cashierBranchId = null;

        if ($userId !== null) {
            $membership = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($membership !== null && $membership->branch_id !== null) {
                $cashierBranchId = $membership->branch_id;
            }
        }

        $allBookings = $this->queueRepository->getAllPendingBookings($cashierBranchId);
        $todayStr = now()->toDateString();

        $todayBookings = $allBookings->filter(fn ($b) => $b->scheduled_at->toDateString() === $todayStr)->values();
        $pastBookings = $allBookings->filter(fn ($b) => $b->scheduled_at->toDateString() < $todayStr)->sortByDesc('scheduled_at')->values();
        $futureBookings = $allBookings->filter(fn ($b) => $b->scheduled_at->toDateString() > $todayStr)->sortBy('scheduled_at')->values();

        return [
            'pendingBookings' => $todayBookings,
            'todayBookings' => $todayBookings,
            'pastBookings' => $pastBookings,
            'futureBookings' => $futureBookings,
            'allBookings' => $allBookings,
            'cashierBranchId' => $cashierBranchId,
            'hairColors' => $this->catalogRepository->getAvailableHairColors(),
            'activeTab' => 'bookings',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierChairsData(): array
    {
        return [
            'chairTickets' => $this->queueRepository->getTodayActiveTicketsForChairs(),
            'branches' => $this->branchRepository->getActiveBranches(),
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'activeTab' => 'chairs',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBarberDashboardData(?int $userId): array
    {
        $barberMembership = $this->staffRepository->findBarberMembershipForUser($userId);
        if ($barberMembership !== null) {
            $barberMembership->load(['tenant', 'branch', 'user']);
        }
        $barberMembershipId = $barberMembership?->id;
        $branchId = $barberMembership?->branch_id;

        $servingTicket = $this->queueRepository->getServingTicketForBarber($barberMembershipId);
        $waitingQueue = $this->queueRepository->getWaitingQueueForBarberBranch($branchId, $barberMembershipId);
        $completedToday = $this->queueRepository->countCompletedTodayForBarber($barberMembershipId);
        $commissionToday = $this->orderRepository->getTodayCommissionForBarber($barberMembershipId);
        $tipToday = 0;

        $todayAttendance = $barberMembershipId !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereDate('date', now()->format('Y-m-d'))
                ->first()
            : null;

        $branchQrToken = $barberMembership?->branch?->qr_token
            ?? \App\Models\Branch::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $barberMembership?->tenant_id)
                ->value('qr_token');

        return [
            'barberMembership' => $barberMembership,
            'servingTicket' => $servingTicket,
            'waitingQueue' => $waitingQueue,
            'hasActiveTask' => ($servingTicket !== null),
            'myQueue' => $this->queueRepository->getActiveQueueForBarber($barberMembershipId),
            'completedToday' => $completedToday,
            'commissionToday' => $commissionToday,
            'tipToday' => $tipToday,
            'todayAttendance' => $todayAttendance,
            'branchQrToken' => $branchQrToken,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerPortalData(string $tenantSlug, ?string $ticketNumber = null, ?int $userId = null, int|string|null $branchCodeOrId = null): array
    {
        $currentTenant = app(CurrentTenant::class)->get();
        $tenant = ($currentTenant !== null && $currentTenant->slug === $tenantSlug)
            ? $currentTenant
            : $this->platformRepository->findTenantBySlugOrFail($tenantSlug);

        $branches = $this->branchRepository->getActiveBranches($tenant);

        $selectedBranch = null;
        if ($branchCodeOrId !== null && $branchCodeOrId !== '') {
            $selectedBranch = is_numeric($branchCodeOrId)
                ? $branches->firstWhere('id', (int) $branchCodeOrId)
                : $branches->firstWhere('code', strtoupper($branchCodeOrId));
        }
        $selectedBranch = $selectedBranch ?? $branches->first();

        $allBarbers = $selectedBranch !== null
            ? $this->staffRepository->getBarbersForBranch($tenant, $selectedBranch->id)
            : $this->staffRepository->getActiveBarbers($tenant, withRelations: true);

        $today = now()->toDateString();
        $presentBarberMembershipIds = \App\Models\Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', $today)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->pluck('membership_id')
            ->all();

        $walkinBarbers = $allBarbers->filter(fn ($b) => in_array($b->id, $presentBarberMembershipIds, true))->values();

        $branchQueueStats = null;
        $branchOperationalStatus = null;
        if ($selectedBranch !== null) {
            $branchOperationalStatus = $selectedBranch->checkOperationalStatus();
            $servingTicket = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $selectedBranch->id)
                ->whereDate('date', $today)
                ->whereIn('status', [QueueStatus::Called, QueueStatus::InService])
                ->latest('called_at')
                ->first();

            $waitingCount = QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $selectedBranch->id)
                ->whereDate('date', $today)
                ->where('status', QueueStatus::Waiting)
                ->count();

            $estMinutes = $this->queueRepository->sumActiveEstimatedMinutes($tenant->id, $selectedBranch->id);

            $branchQueueStats = [
                'servingNumber' => $servingTicket?->number,
                'waitingCount' => $waitingCount,
                'estMinutes' => $estMinutes,
            ];
        }

        $initialDate = \Carbon\CarbonImmutable::now()->addDay();
        $initialSlots = [];
        if ($selectedBranch !== null) {
            $initialSlots = app(\App\Services\BookingService::class)->getAvailableSlots(
                branch: $selectedBranch,
                date: $initialDate,
                barberMembershipId: null,
                durationMinutes: 30,
            );
        }

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'branchOperationalStatus' => $branchOperationalStatus,
            'services' => $this->catalogRepository->getActiveServices($tenant),
            'hairColors' => $this->catalogRepository->getAvailableHairColors($tenant),
            'barbers' => $allBarbers,
            'walkinBarbers' => $walkinBarbers,
            'activeTicket' => $this->queueRepository->findActiveTicketForCustomer($tenant, $ticketNumber, $userId),
            'recentInvoices' => $this->orderRepository->getRecentOrdersForCustomer($tenant->id, $userId, $ticketNumber),
            'branchQueueStats' => $branchQueueStats,
            'initialSlots' => $initialSlots,
            'initialDate' => $initialDate->toDateString(),
        ];
    }

    public function getInvoiceDetailData(string $orderNumber): array
    {
        $order = $this->orderRepository->findOrderByNumberOrFail($orderNumber);
        $currentTenant = app(CurrentTenant::class)->get();
        $tenant = ($currentTenant !== null && $currentTenant->id === $order->tenant_id)
            ? $currentTenant
            : $order->tenant;

        return [
            'order' => $order,
            'tenant' => $tenant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTvDisplayData(string $tenantSlug, string $branchCode): array
    {
        $currentTenant = app(CurrentTenant::class)->get();
        $tenant = ($currentTenant !== null && $currentTenant->slug === $tenantSlug)
            ? $currentTenant
            : $this->platformRepository->findTenantBySlugOrFail($tenantSlug);
        $branch = $this->branchRepository->findActiveBranchByCodeOrFail($tenant, $branchCode);

        $isLocked = $tenant->isBranchLocked($branch);
        if ($isLocked) {
            return [
                'tenant' => $tenant,
                'branch' => $branch,
                'isLocked' => true,
                'planName' => $tenant->plan()?->name ?? 'Starter',
                'maxBranches' => $tenant->maxBranches(),
                'waitingTickets' => collect(),
                'activeTickets' => collect(),
                'calledTicket' => null,
                'barberCards' => collect(),
            ];
        }

        $activeTickets = $this->queueRepository->getActiveTicketsForBranchDisplay($tenant, $branch->id);
        $calledTicket = $activeTickets
            ->whereIn('status', [\App\Enums\QueueStatus::Called, \App\Enums\QueueStatus::InService])
            ->sortByDesc(fn ($ticket) => $ticket->called_at?->getTimestamp() ?? 0)
            ->first();
        $waitingTickets = $activeTickets
            ->where('status', \App\Enums\QueueStatus::Waiting)
            ->values();
        $branchBarbers = $this->staffRepository->getBarbersForBranch($tenant, $branch->id);

        $today = now()->toDateString();
        $presentBarberMembershipIds = \App\Models\Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', $today)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->pluck('membership_id')
            ->all();

        $branchBarbers = $branchBarbers->filter(fn ($b) => in_array($b->id, $presentBarberMembershipIds, true))->values();

        $barberCards = $branchBarbers->map(function ($barber) use ($activeTickets) {
            $barberTickets = $activeTickets->where('barber_membership_id', $barber->id);
            $activeTicket = $barberTickets->firstWhere('status', QueueStatus::InService)
                ?? $barberTickets->firstWhere('status', QueueStatus::Called);
            $waitingCount = $barberTickets->where('status', QueueStatus::Waiting)->count();

            if ($activeTicket && $activeTicket->status === QueueStatus::InService) {
                $state = 'working';
                $statusLabel = 'Sedang Bekerja';
                $subInfo = 'Melayani ' . $activeTicket->number . ($activeTicket->items->isNotEmpty() ? ' · ' . $activeTicket->items->pluck('service_name')->join(', ') : '');
            } elseif ($activeTicket && $activeTicket->status === QueueStatus::Called) {
                $state = 'calling';
                $statusLabel = 'Memanggil Pelanggan';
                $subInfo = 'Tiket ' . $activeTicket->number . ' menuju kursi';
            } elseif ($barber->work_status === WorkStatus::Break) {
                $state = 'break';
                $statusLabel = 'Istirahat (Break)';
                $subInfo = $waitingCount > 0 ? $waitingCount . ' antrean menunggu' : 'Sementara tidak menerima antrean';
            } elseif ($barber->work_status === WorkStatus::Off) {
                $state = 'off';
                $statusLabel = 'Selesai Shift';
                $subInfo = 'Tidak bertugas';
            } else {
                $state = 'available';
                $statusLabel = 'Ready';
                $subInfo = $waitingCount > 0
                    ? 'Siap melayani · ' . $waitingCount . ' antrean menunggu'
                    : 'Siap melayani pelanggan langsung';
            }

            return [
                'barber' => $barber,
                'name' => $barber->display_name ?? $barber->user?->name ?? 'Barber',
                'specialty' => $barber->specialty ?: 'All-round Barber',
                'state' => $state,
                'statusLabel' => $statusLabel,
                'subInfo' => $subInfo,
                'activeTicket' => $activeTicket,
                'waitingCount' => $waitingCount,
            ];
        });

        $availableCount = $barberCards->where('state', 'available')->count();
        $workingCount = $barberCards->whereIn('state', ['working', 'calling'])->count();

        return [
            'tenant' => $tenant,
            'branch' => $branch,
            'calledTicket' => $calledTicket,
            'waitingTickets' => $waitingTickets,
            'branchBarbers' => $branchBarbers,
            'activeBranchTickets' => $activeTickets,
            'barberCards' => $barberCards,
            'availableCount' => $availableCount,
            'workingCount' => $workingCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierAttendanceData(
        int $userId,
        string $period = 'today',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $status = 'all',
        ?int $barberId = null,
    ): array
    {
        $tenant = app(CurrentTenant::class)->get();
        $membership = \App\Models\Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant?->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with(['branch', 'user'])
            ->first();

        $branch = $membership?->branch
            ?? \App\Models\Branch::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant?->id)
                ->where('is_active', true)
                ->first();

        $today = now()->format('Y-m-d');

        // Otomatisasi Alpa lewat 23:59 & auto check-out jika lupa absen pulang
        app(\App\Services\OwnerManagementService::class)->autoCloseDailyAttendances(
            tenantId: $tenant?->id,
            dateFrom: now()->subDays(14)->toDateString(),
            dateTo: now()->toDateString()
        );

        $todayAttendance = $membership !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->whereDate('date', $today)
                ->first()
            : null;

        $period = in_array($period, ['today', '7days', 'this_month', 'custom'], true) ? $period : 'today';

        $resolvedDateFrom = match ($period) {
            'today' => now()->startOfDay()->toDateString(),
            '7days' => now()->subDays(6)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'custom' => ! empty($dateFrom) ? (string) $dateFrom : now()->startOfDay()->toDateString(),
            default => now()->startOfDay()->toDateString(),
        };

        $resolvedDateTo = match ($period) {
            'today' => now()->endOfDay()->toDateString(),
            '7days' => now()->toDateString(),
            'this_month' => now()->endOfMonth()->toDateString(),
            'custom' => ! empty($dateTo) ? (string) $dateTo : now()->endOfDay()->toDateString(),
            default => now()->endOfDay()->toDateString(),
        };

        $recentAttendancesQuery = $branch !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branch->id)
                ->whereDate('date', '>=', $resolvedDateFrom)
                ->whereDate('date', '<=', $resolvedDateTo)
                ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('membership_id', $barberId))
                ->when($status !== 'all' && in_array($status, ['present', 'late', 'sick', 'leave', 'holiday', 'absent'], true), fn ($q) => $q->where('status', $status))
                ->with(['membership.user'])
                ->latest('date')
                ->latest('check_in_at')
                ->orderBy('membership_id')
            : \App\Models\Attendance::query()->whereRaw('1 = 0');

        $recentAttendances = (clone $recentAttendancesQuery)->paginate(10)->withQueryString();

        $stats = $branch !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branch->id)
                ->whereDate('date', '>=', $resolvedDateFrom)
                ->whereDate('date', '<=', $resolvedDateTo)
                ->when($barberId !== null && $barberId > 0, fn ($q) => $q->where('membership_id', $barberId))
                ->selectRaw("
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN status IN ('leave', 'absent', 'sick', 'holiday') THEN 1 END) as leave_count,
                    COALESCE(SUM(late_minutes), 0) as total_late_minutes
                ")
                ->first()
            : null;

        $totalRecords = (int) ($stats->total_records ?? 0);
        $presentCount = (int) ($stats->present_count ?? 0);
        $lateCount = (int) ($stats->late_count ?? 0);
        $leaveCount = (int) ($stats->leave_count ?? 0);
        $totalLateMinutes = (int) ($stats->total_late_minutes ?? 0);

        $branchStaff = $branch !== null
            ? \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->with(['user', 'branch'])
                ->get()
            : collect();

        $todayAttendedMembershipIds = $branch !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('branch_id', $branch->id)
                ->whereDate('date', $today)
                ->pluck('membership_id')
                ->all()
            : [];

        $todayDayOfWeek = now()->dayOfWeek;

        $allStaffSchedulesToday = $branchStaff->isNotEmpty()
            ? \App\Models\BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->whereIn('membership_id', $branchStaff->pluck('id'))
                ->where('day_of_week', $todayDayOfWeek)
                ->get()
                ->keyBy('membership_id')
            : collect();

        $todayUnattendedStaff = ($period === 'today' && in_array($status, ['all', 'absent'], true))
            ? $branchStaff->filter(function ($staff) use ($todayAttendedMembershipIds, $barberId) {
                if (in_array($staff->id, $todayAttendedMembershipIds, true)) {
                    return false;
                }
                if ($barberId !== null && $barberId > 0 && $staff->id !== $barberId) {
                    return false;
                }
                return true;
            })->values()
            : collect();

        // Jadwal kerja mingguan kasir (dari BarberSchedule atau opening_hours cabang)
        $schedules = $membership !== null
            ? \App\Models\BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->get()
                ->keyBy('day_of_week')
            : collect();

        // Rekan barber di cabang ini hari ini
        $branchBarbers = collect();
        $barberSchedulesToday = collect();
        $barberAttendancesToday = collect();

        if ($tenant !== null && $branch !== null) {
            $branchBarbers = $this->staffRepository->getBarbersForBranch($tenant, $branch->id);
            $barberIds = $branchBarbers->pluck('id')->all();

            $todayDayOfWeek = now()->dayOfWeek;
            $barberSchedulesToday = \App\Models\BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->whereIn('membership_id', $barberIds)
                ->where('day_of_week', $todayDayOfWeek)
                ->get()
                ->keyBy('membership_id');

            $barberAttendancesToday = \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->whereIn('membership_id', $barberIds)
                ->whereDate('date', $today)
                ->get()
                ->keyBy('membership_id');
        }

        $todayTimeOff = $membership !== null
            ? \App\Models\BarberTimeOff::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->where('status', 'approved')
                ->whereDate('date_from', '<=', $today)
                ->whereDate('date_to', '>=', $today)
                ->first()
            : null;

        $timeOffRequests = $membership !== null
            ? \App\Models\BarberTimeOff::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : collect();

        return [
            'tenant' => $tenant,
            'branch' => $branch,
            'membership' => $membership,
            'todayAttendance' => $todayAttendance,
            'todayTimeOff' => $todayTimeOff,
            'recentAttendances' => $recentAttendances,
            'period' => $period,
            'dateFrom' => $resolvedDateFrom,
            'dateTo' => $resolvedDateTo,
            'statusFilter' => $status,
            'barberFilter' => $barberId,
            'totalRecords' => $totalRecords,
            'presentCount' => $presentCount,
            'lateCount' => $lateCount,
            'leaveCount' => $leaveCount,
            'totalLateMinutes' => $totalLateMinutes,
            'branchStaff' => $branchStaff,
            'todayUnattendedStaff' => $todayUnattendedStaff,
            'allStaffSchedulesToday' => $allStaffSchedulesToday,
            'weeklySchedules' => $schedules,
            'schedules' => $schedules,
            'timeOffRequests' => $timeOffRequests,
            'branchBarbers' => $branchBarbers,
            'barberSchedulesToday' => $barberSchedulesToday,
            'barberAttendancesToday' => $barberAttendancesToday,
            'branchQrToken' => $branch?->qr_token,
            'activeTab' => 'attendance',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBarberAttendanceData(int $userId): array
    {
        $barberMembership = $this->staffRepository->findBarberMembershipForUser($userId);
        if ($barberMembership !== null) {
            $barberMembership->load(['tenant', 'branch', 'user']);
        }

        $branch = $barberMembership?->branch;
        $today = now()->format('Y-m-d');

        $todayAttendance = $barberMembership !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->whereDate('date', $today)
                ->first()
            : null;

        $recentAttendances = $barberMembership !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->orderByDesc('date')
                ->limit(14)
                ->get()
            : collect();

        $weeklySchedules = $barberMembership !== null
            ? \App\Models\BarberSchedule::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->get()
                ->keyBy('day_of_week')
            : collect();

        $todayTimeOff = $barberMembership !== null
            ? \App\Models\BarberTimeOff::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->where('status', 'approved')
                ->whereDate('date_from', '<=', $today)
                ->whereDate('date_to', '>=', $today)
                ->first()
            : null;

        $timeOffRequests = $barberMembership !== null
            ? \App\Models\BarberTimeOff::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembership->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : collect();

        return [
            'barberMembership' => $barberMembership,
            'branch' => $branch,
            'todayAttendance' => $todayAttendance,
            'todayTimeOff' => $todayTimeOff,
            'recentAttendances' => $recentAttendances,
            'weeklySchedules' => $weeklySchedules,
            'timeOffRequests' => $timeOffRequests,
            'branchQrToken' => $branch?->qr_token,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBarberProfileData(?int $userId): array
    {
        $barberMembership = $this->staffRepository->findBarberMembershipForUser($userId);
        if ($barberMembership !== null) {
            $barberMembership->load(['tenant', 'branch', 'user', 'salaryScheme']);
        }
        $barberMembershipId = $barberMembership?->id;

        $completedThisMonth = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count()
            : 0;

        $totalCompleted = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->count()
            : 0;

        $commissionThisMonth = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereMonth('earned_at', now()->month)
                ->whereYear('earned_at', now()->year)
                ->sum('amount')
            : 0;

        $attendancesThisMonth = $barberMembershipId !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->whereNotNull('check_in_at')
                ->count()
            : 0;

        return [
            'barberMembership' => $barberMembership,
            'user' => $barberMembership?->user ?? auth()->user(),
            'tenant' => $barberMembership?->tenant,
            'branch' => $barberMembership?->branch,
            'completedThisMonth' => $completedThisMonth,
            'totalCompleted' => $totalCompleted,
            'commissionThisMonth' => $commissionThisMonth,
            'attendancesThisMonth' => $attendancesThisMonth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBarberEarningsData(?int $userId, string $period = 'this_month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $barberMembership = $this->staffRepository->findBarberMembershipForUser($userId);
        if ($barberMembership !== null) {
            $barberMembership->load(['tenant', 'branch', 'user', 'salaryScheme']);
        }
        $barberMembershipId = $barberMembership?->id;
        $tenant = $barberMembership?->tenant;
        $branch = $barberMembership?->branch;

        $planCode = $tenant?->planCode() ?? 'starter';
        $canViewAnalytics = in_array($planCode, ['pro', 'business'], true) || ($tenant?->hasFeature('analytics_pro') ?? false);

        // Resolusi periode filter
        $periodLabel = 'Bulan Ini';
        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                $periodLabel = 'Hari Ini (' . now()->translatedFormat('d M Y') . ')';
                break;
            case 'yesterday':
                $startDate = now()->subDay()->startOfDay();
                $endDate = now()->subDay()->endOfDay();
                $periodLabel = 'Kemarin (' . now()->subDay()->translatedFormat('d M Y') . ')';
                break;
            case '7days':
                $startDate = now()->subDays(6)->startOfDay();
                $endDate = now()->endOfDay();
                $periodLabel = '7 Hari Terakhir (' . now()->subDays(6)->translatedFormat('d M') . ' – ' . now()->translatedFormat('d M') . ')';
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth()->startOfDay();
                $endDate = now()->subMonth()->endOfMonth()->endOfDay();
                $periodLabel = 'Bulan Lalu (' . now()->subMonth()->translatedFormat('F Y') . ')';
                break;
            case 'custom':
                $startDate = ! empty($dateFrom) ? \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay() : now()->subDays(30)->startOfDay();
                $endDate = ! empty($dateTo) ? \Illuminate\Support\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
                $periodLabel = $startDate->translatedFormat('d M Y') . ' – ' . $endDate->translatedFormat('d M Y');
                break;
            case 'this_month':
            default:
                $period = 'this_month';
                $startDate = now()->startOfMonth()->startOfDay();
                $endDate = now()->endOfMonth()->endOfDay();
                $periodLabel = 'Bulan Ini (' . now()->translatedFormat('F Y') . ')';
                break;
        }

        // Komisi, Tip & Pangkas Sesuai Filter Periode Terpilih
        $periodCommission = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereBetween('earned_at', [$startDate, $endDate])
                ->sum('amount')
            : 0;

        $periodTip = 0;

        $periodCompleted = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->whereDate('date', '>=', $startDate->toDateString())
                ->whereDate('date', '<=', $endDate->toDateString())
                ->count()
            : 0;

        // Statistik acuan cepat
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayStr = $today->toDateString();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $commissionToday = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereBetween('earned_at', [$today, $todayEnd])
                ->sum('amount')
            : 0;

        $commissionThisMonth = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->where('earned_at', '>=', $thisMonth)
                ->sum('amount')
            : 0;

        $commissionLastMonth = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereBetween('earned_at', [$lastMonth, $lastMonth->copy()->endOfMonth()])
                ->sum('amount')
            : 0;

        $completedToday = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->whereDate('date', $todayStr)
                ->count()
            : 0;

        $completedThisMonth = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->whereDate('date', '>=', $thisMonth->toDateString())
                ->count()
            : 0;

        // Riwayat komisi pada periode filter
        $recentCommissions = $barberMembershipId !== null
            ? \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->with(['orderItem.service'])
                ->where('membership_id', $barberMembershipId)
                ->whereBetween('earned_at', [$startDate, $endDate])
                ->latest('earned_at')
                ->limit(50)
                ->get()
            : collect();

        $recentTips = collect();
        $tipToday = 0;
        $tipThisMonth = 0;

        // Data Grafik Pendapatan Sesuai Periode (Fitur Pro/Bisnis)
        $dailyChart = [];
        $peakDay = null;
        $peakAmount = 0;
        $totalChartAmount = 0;

        if ($canViewAnalytics && $barberMembershipId !== null) {
            $rawEntries = \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereBetween('earned_at', [$startDate, $endDate])
                ->get();

            $diffDays = (int) $startDate->diffInDays($endDate) + 1;

            if ($diffDays <= 10) {
                // Grafik harian per hari (misal 1 hari, 7 hari, atau rentang kustom pendek)
                $dayAmounts = [];
                for ($d = clone $startDate; $d->lte($endDate); $d->addDay()) {
                    $dateStr = $d->toDateString();
                    $dayEntries = $rawEntries->filter(fn ($item) => $item->earned_at?->toDateString() === $dateStr);
                    $amount = (int) $dayEntries->sum('amount');
                    $count = $dayEntries->count();

                    if ($amount > $peakAmount) {
                        $peakAmount = $amount;
                        $peakDay = $d->translatedFormat('l, d M');
                    }
                    $totalChartAmount += $amount;

                    $dayAmounts[] = [
                        'date' => $dateStr,
                        'day_name' => $d->translatedFormat('D'),
                        'day_date' => $d->format('d/m'),
                        'is_today' => $d->isToday(),
                        'amount' => $amount,
                        'count' => $count,
                    ];
                }

                $maxAmount = max($peakAmount, 1);
                foreach ($dayAmounts as $item) {
                    $heightPct = $item['amount'] > 0 ? (int) round(($item['amount'] / $maxAmount) * 100) : 6;
                    $dailyChart[] = array_merge($item, [
                        'percentage' => max(6, $heightPct),
                        'is_peak' => $item['amount'] > 0 && $item['amount'] === $peakAmount,
                    ]);
                }
            } else {
                // Grafik mingguan jika rentang periode > 10 hari (sebulan penuh / kustom panjang)
                $currentPeriodStart = clone $startDate;
                $weekIndex = 1;
                $weekBuckets = [];

                while ($currentPeriodStart->lte($endDate)) {
                    $currentPeriodEnd = (clone $currentPeriodStart)->addDays(6)->endOfDay();
                    if ($currentPeriodEnd->gt($endDate)) {
                        $currentPeriodEnd = clone $endDate;
                    }

                    $weekEntries = $rawEntries->filter(fn ($item) => $item->earned_at?->between($currentPeriodStart, $currentPeriodEnd));
                    $amount = (int) $weekEntries->sum('amount');
                    $count = $weekEntries->count();

                    if ($amount > $peakAmount) {
                        $peakAmount = $amount;
                        $peakDay = "Minggu ke-{$weekIndex} (" . $currentPeriodStart->format('d/m') . ' - ' . $currentPeriodEnd->format('d/m') . ')';
                    }
                    $totalChartAmount += $amount;

                    $weekBuckets[] = [
                        'date' => $currentPeriodStart->toDateString(),
                        'day_name' => "Mgg {$weekIndex}",
                        'day_date' => $currentPeriodStart->format('d/m') . '-' . $currentPeriodEnd->format('d/m'),
                        'is_today' => now()->between($currentPeriodStart, $currentPeriodEnd),
                        'amount' => $amount,
                        'count' => $count,
                    ];

                    $currentPeriodStart = (clone $currentPeriodEnd)->addSecond();
                    $weekIndex++;
                }

                $maxAmount = max($peakAmount, 1);
                foreach ($weekBuckets as $item) {
                    $heightPct = $item['amount'] > 0 ? (int) round(($item['amount'] / $maxAmount) * 100) : 6;
                    $dailyChart[] = array_merge($item, [
                        'percentage' => max(6, $heightPct),
                        'is_peak' => $item['amount'] > 0 && $item['amount'] === $peakAmount,
                    ]);
                }
            }
        }

        // Skema gaji & perhitungan denda alpha periode terpilih
        $salaryScheme = $barberMembership?->salaryScheme;
        if ($salaryScheme === null && $barberMembership?->role !== null && $tenant !== null) {
            $salaryScheme = \App\Models\SalaryScheme::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where(function ($q) use ($barberMembership) {
                    $q->where('type', $barberMembership->role->value)
                        ->orWhere('name', 'like', '%' . $barberMembership->role->value . '%');
                })
                ->first();
        }

        $alphaPenaltyNominal = 0;
        $alphaDays = 0;
        $alphaDeduction = 0;

        if ($barberMembershipId !== null) {
            $calcEnd = $endDate->lt(now()) ? $endDate : now()->endOfDay();
            if ($startDate->lte($calcEnd)) {
                $alphaDays = \App\Models\Attendance::query()->withoutGlobalScope('tenant')
                    ->where('membership_id', $barberMembershipId)
                    ->whereBetween('date', [$startDate->format('Y-m-d'), $calcEnd->format('Y-m-d')])
                    ->where('status', 'absent')
                    ->count();
            }
        }

        return [
            'barberMembership' => $barberMembership,
            'user' => $barberMembership?->user ?? auth()->user(),
            'tenant' => $tenant,
            'branch' => $branch,
            'canViewAnalytics' => $canViewAnalytics,
            'planCode' => $planCode,
            'selectedPeriod' => $period,
            'periodLabel' => $periodLabel,
            'dateFrom' => $dateFrom ?? $startDate->toDateString(),
            'dateTo' => $dateTo ?? $endDate->toDateString(),
            'periodCommission' => $periodCommission,
            'periodTip' => $periodTip,
            'tipToday' => $tipToday,
            'tipThisMonth' => $tipThisMonth,
            'periodCompleted' => $periodCompleted,
            'recentTips' => $recentTips,
            'baseSalary' => (int) ($barberMembership?->effective_base_salary ?? 0),
            'salaryScheme' => $salaryScheme,
            'alphaPenaltyNominal' => $alphaPenaltyNominal,
            'alphaDays' => $alphaDays,
            'alphaDeduction' => $alphaDeduction,
            'commissionToday' => $commissionToday,
            'commissionThisMonth' => $commissionThisMonth,
            'commissionLastMonth' => $commissionLastMonth,
            'completedToday' => $completedToday,
            'completedThisMonth' => $completedThisMonth,
            'recentCommissions' => $recentCommissions,
            'dailyChart' => $dailyChart,
            'peakDay' => $peakDay,
            'peakAmount' => $peakAmount,
            'totalChartAmount' => $totalChartAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierProfileData(?int $userId): array
    {
        $cashierMembership = $this->staffRepository->findCashierMembershipForUser($userId);
        if ($cashierMembership !== null) {
            $cashierMembership->load(['tenant', 'branch', 'user', 'salaryScheme']);
        }
        $cashierMembershipId = $cashierMembership?->id;
        $user = $cashierMembership?->user ?? auth()->user();
        $actualUserId = $user?->id ?? $userId;
        $branchId = $cashierMembership?->branch_id;
        $tenantId = $cashierMembership?->tenant_id;

        $ordersScope = function ($q) use ($actualUserId, $branchId): void {
            $q->where(function ($sub) use ($actualUserId, $branchId): void {
                if ($actualUserId !== null) {
                    $sub->where('cashier_user_id', $actualUserId);
                }
                if ($branchId !== null) {
                    $sub->orWhere('branch_id', $branchId);
                }
            });
        };

        $ordersThisMonthQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($ordersScope)
            ->where(function ($q): void {
                $q->where(function ($sub): void {
                    $sub->whereNotNull('paid_at')
                        ->whereMonth('paid_at', now()->month)
                        ->whereYear('paid_at', now()->year);
                })->orWhere(function ($sub): void {
                    $sub->whereNull('paid_at')
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                });
            });

        $ordersThisMonth = ($actualUserId !== null || $branchId !== null)
            ? (clone $ordersThisMonthQuery)->count()
            : 0;

        $omzetThisMonth = ($actualUserId !== null || $branchId !== null)
            ? (int) (clone $ordersThisMonthQuery)->sum(
                \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
            )
            : 0;

        $totalOrdersQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($ordersScope);

        $totalOrders = ($actualUserId !== null || $branchId !== null)
            ? (clone $totalOrdersQuery)->count()
            : 0;

        $totalOmzet = ($actualUserId !== null || $branchId !== null)
            ? (int) (clone $totalOrdersQuery)->sum(
                \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
            )
            : 0;

        $attendancesThisMonth = $cashierMembershipId !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $cashierMembershipId)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->whereNotNull('check_in_at')
                ->count()
            : 0;

        $latestPayrollItem = $cashierMembershipId !== null
            ? \App\Models\PayrollItem::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $cashierMembershipId)
                ->with('payroll')
                ->latest('id')
                ->first()
            : null;

        return [
            'cashierMembership' => $cashierMembership,
            'user' => $user,
            'tenant' => $cashierMembership?->tenant,
            'branch' => $cashierMembership?->branch,
            'ordersThisMonth' => $ordersThisMonth,
            'omzetThisMonth' => $omzetThisMonth,
            'totalOrders' => $totalOrders,
            'totalOmzet' => $totalOmzet,
            'attendancesThisMonth' => $attendancesThisMonth,
            'baseSalary' => (int) ($cashierMembership?->effective_base_salary ?? 0),
            'latestPayrollItem' => $latestPayrollItem,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierEarningsData(?int $userId, string $period = 'this_month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $cashierMembership = $this->staffRepository->findCashierMembershipForUser($userId);
        if ($cashierMembership !== null) {
            $cashierMembership->load(['tenant', 'branch', 'user', 'salaryScheme']);
        }
        $cashierMembershipId = $cashierMembership?->id;
        $user = $cashierMembership?->user ?? auth()->user();
        $actualUserId = $user?->id ?? $userId;
        $tenant = $cashierMembership?->tenant;
        $branch = $cashierMembership?->branch;

        $planCode = $tenant?->planCode() ?? 'starter';
        $canViewAnalytics = in_array($planCode, ['pro', 'business'], true) || ($tenant?->hasFeature('analytics_pro') ?? false);

        // Resolusi periode filter
        $periodLabel = 'Bulan Ini';
        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                $periodLabel = 'Hari Ini (' . now()->translatedFormat('d M Y') . ')';
                break;
            case 'yesterday':
                $startDate = now()->subDay()->startOfDay();
                $endDate = now()->subDay()->endOfDay();
                $periodLabel = 'Kemarin (' . now()->subDay()->translatedFormat('d M Y') . ')';
                break;
            case '7days':
                $startDate = now()->subDays(6)->startOfDay();
                $endDate = now()->endOfDay();
                $periodLabel = '7 Hari Terakhir (' . now()->subDays(6)->translatedFormat('d M') . ' – ' . now()->translatedFormat('d M') . ')';
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth()->startOfDay();
                $endDate = now()->subMonth()->endOfMonth()->endOfDay();
                $periodLabel = 'Bulan Lalu (' . now()->subMonth()->translatedFormat('F Y') . ')';
                break;
            case 'custom':
                $startDate = ! empty($dateFrom) ? \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay() : now()->subDays(30)->startOfDay();
                $endDate = ! empty($dateTo) ? \Illuminate\Support\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
                $periodLabel = $startDate->translatedFormat('d M Y') . ' – ' . $endDate->translatedFormat('d M Y');
                break;
            case 'this_month':
            default:
                $period = 'this_month';
                $startDate = now()->startOfMonth()->startOfDay();
                $endDate = now()->endOfMonth()->endOfDay();
                $periodLabel = 'Bulan Ini (' . now()->translatedFormat('F Y') . ')';
                break;
        }

        $branchId = $branch?->id;
        $tenantId = $tenant?->id;

        $earningsScope = function ($q) use ($actualUserId, $branchId): void {
            $q->where(function ($sub) use ($actualUserId, $branchId): void {
                if ($actualUserId !== null) {
                    $sub->where('cashier_user_id', $actualUserId);
                }
                if ($branchId !== null) {
                    $sub->orWhere('branch_id', $branchId);
                }
            });
        };

        // Query transaksi yang diproses kasir pada periode terpilih
        $ordersQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($earningsScope)
            ->where(function ($q) use ($startDate, $endDate): void {
                $q->where(function ($sub) use ($startDate, $endDate): void {
                    $sub->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$startDate, $endDate]);
                })->orWhere(function ($sub) use ($startDate, $endDate): void {
                    $sub->whereNull('paid_at')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            });

        $periodOrdersCount = (clone $ordersQuery)->count();
        $periodOmzet = (int) (clone $ordersQuery)->sum(
            \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
        );
        $recentOrders = (clone $ordersQuery)
            ->with(['customerProfile', 'branch', 'items.service'])
            ->latest('id')
            ->limit(30)
            ->get();

        // Acuan cepat (Hari Ini, Bulan Ini, Bulan Lalu)
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $ordersTodayQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($earningsScope)
            ->where(function ($q) use ($today, $todayEnd): void {
                $q->where(function ($sub) use ($today, $todayEnd): void {
                    $sub->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$today, $todayEnd]);
                })->orWhere(function ($sub) use ($today, $todayEnd): void {
                    $sub->whereNull('paid_at')
                        ->whereBetween('created_at', [$today, $todayEnd]);
                });
            });

        $ordersToday = (clone $ordersTodayQuery)->count();
        $omzetToday = (int) (clone $ordersTodayQuery)->sum(
            \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
        );

        $ordersThisMonthQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($earningsScope)
            ->where(function ($q) use ($thisMonth): void {
                $q->where(function ($sub) use ($thisMonth): void {
                    $sub->whereNotNull('paid_at')
                        ->where('paid_at', '>=', $thisMonth);
                })->orWhere(function ($sub) use ($thisMonth): void {
                    $sub->whereNull('paid_at')
                        ->where('created_at', '>=', $thisMonth);
                });
            });

        $ordersThisMonth = (clone $ordersThisMonthQuery)->count();
        $omzetThisMonth = (int) (clone $ordersThisMonthQuery)->sum(
            \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
        );

        $lastMonthEnd = $lastMonth->copy()->endOfMonth();
        $ordersLastMonthQuery = \App\Models\Order::query()
            ->withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('payment_status', 'paid')
            ->where($earningsScope)
            ->where(function ($q) use ($lastMonth, $lastMonthEnd): void {
                $q->where(function ($sub) use ($lastMonth, $lastMonthEnd): void {
                    $sub->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$lastMonth, $lastMonthEnd]);
                })->orWhere(function ($sub) use ($lastMonth, $lastMonthEnd): void {
                    $sub->whereNull('paid_at')
                        ->whereBetween('created_at', [$lastMonth, $lastMonthEnd]);
                });
            });

        $omzetLastMonth = (int) (clone $ordersLastMonthQuery)->sum(
            \Illuminate\Support\Facades\DB::raw('CASE WHEN grand_total > 0 THEN grand_total ELSE paid_total END')
        );

        // Komisi jika ada skema komisi kasir
        $periodCommission = $cashierMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $cashierMembershipId)
                ->whereBetween('earned_at', [$startDate, $endDate])
                ->sum('amount')
            : 0;

        // Riwayat slip gaji kasir
        $payrollItems = $cashierMembershipId !== null
            ? \App\Models\PayrollItem::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $cashierMembershipId)
                ->with('payroll')
                ->latest('id')
                ->limit(12)
                ->get()
            : collect();

        // Data Grafik Transaksi/Omzet Kasir
        $dailyChart = [];
        $peakDay = null;
        $peakAmount = 0;
        $totalChartAmount = 0;

        if ($canViewAnalytics && ($actualUserId !== null || $branchId !== null)) {
            $rawOrders = \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('payment_status', 'paid')
                ->where($earningsScope)
                ->where(function ($q) use ($startDate, $endDate): void {
                    $q->where(function ($sub) use ($startDate, $endDate): void {
                        $sub->whereNotNull('paid_at')
                            ->whereBetween('paid_at', [$startDate, $endDate]);
                    })->orWhere(function ($sub) use ($startDate, $endDate): void {
                        $sub->whereNull('paid_at')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    });
                })
                ->get();

            $dateOfOrder = fn ($item) => $item->paid_at ?? $item->created_at;
            $orderAmount = fn ($item) => (int) ($item->grand_total > 0 ? $item->grand_total : ($item->paid_total ?? 0));

            $diffDays = (int) $startDate->diffInDays($endDate) + 1;

            if ($diffDays <= 10) {
                $dayMap = [];
                for ($i = 0; $i < $diffDays; $i++) {
                    $d = $startDate->copy()->addDays($i);
                    $dateKey = $d->toDateString();
                    $dayOrders = $rawOrders->filter(fn ($item) => $dateOfOrder($item)?->toDateString() === $dateKey);
                    $amount = (int) $dayOrders->sum($orderAmount);
                    $count = $dayOrders->count();

                    if ($amount > $peakAmount) {
                        $peakAmount = $amount;
                        $peakDay = $d->translatedFormat('l, d M');
                    }
                    $totalChartAmount += $amount;

                    $dayMap[] = [
                        'date' => $dateKey,
                        'day_name' => $d->translatedFormat('D'),
                        'day_date' => $d->format('d/m'),
                        'is_today' => $d->isToday(),
                        'amount' => $amount,
                        'count' => $count,
                    ];
                }

                $maxAmount = max($peakAmount, 1);
                foreach ($dayMap as $item) {
                    $heightPct = $item['amount'] > 0 ? (int) round(($item['amount'] / $maxAmount) * 100) : 6;
                    $dailyChart[] = array_merge($item, [
                        'percentage' => max(6, $heightPct),
                        'is_peak' => $item['amount'] > 0 && $item['amount'] === $peakAmount,
                    ]);
                }
            } else {
                $currentPeriodStart = clone $startDate;
                $weekIndex = 1;
                $weekBuckets = [];

                while ($currentPeriodStart->lte($endDate)) {
                    $currentPeriodEnd = (clone $currentPeriodStart)->addDays(6)->endOfDay();
                    if ($currentPeriodEnd->gt($endDate)) {
                        $currentPeriodEnd = clone $endDate;
                    }

                    $weekOrders = $rawOrders->filter(fn ($item) => $dateOfOrder($item)?->between($currentPeriodStart, $currentPeriodEnd));
                    $amount = (int) $weekOrders->sum($orderAmount);
                    $count = $weekOrders->count();

                    if ($amount > $peakAmount) {
                        $peakAmount = $amount;
                        $peakDay = "Minggu ke-{$weekIndex} (" . $currentPeriodStart->format('d/m') . ' - ' . $currentPeriodEnd->format('d/m') . ')';
                    }
                    $totalChartAmount += $amount;

                    $weekBuckets[] = [
                        'date' => $currentPeriodStart->toDateString(),
                        'day_name' => "Mgg {$weekIndex}",
                        'day_date' => $currentPeriodStart->format('d/m') . '-' . $currentPeriodEnd->format('d/m'),
                        'is_today' => now()->between($currentPeriodStart, $currentPeriodEnd),
                        'amount' => $amount,
                        'count' => $count,
                    ];

                    $currentPeriodStart = (clone $currentPeriodEnd)->addSecond();
                    $weekIndex++;
                }

                $maxAmount = max($peakAmount, 1);
                foreach ($weekBuckets as $item) {
                    $heightPct = $item['amount'] > 0 ? (int) round(($item['amount'] / $maxAmount) * 100) : 6;
                    $dailyChart[] = array_merge($item, [
                        'percentage' => max(6, $heightPct),
                        'is_peak' => $item['amount'] > 0 && $item['amount'] === $peakAmount,
                    ]);
                }
            }
        }

        return [
            'cashierMembership' => $cashierMembership,
            'user' => $user,
            'tenant' => $tenant,
            'branch' => $branch,
            'canViewAnalytics' => $canViewAnalytics,
            'planCode' => $planCode,
            'selectedPeriod' => $period,
            'periodLabel' => $periodLabel,
            'dateFrom' => $dateFrom ?? $startDate->toDateString(),
            'dateTo' => $dateTo ?? $endDate->toDateString(),
            'periodOrdersCount' => $periodOrdersCount,
            'periodOmzet' => $periodOmzet,
            'recentOrders' => $recentOrders,
            'baseSalary' => (int) ($cashierMembership?->effective_base_salary ?? 0),
            'periodCommission' => $periodCommission,
            'payrollItems' => $payrollItems,
            'ordersToday' => $ordersToday,
            'omzetToday' => $omzetToday,
            'ordersThisMonth' => $ordersThisMonth,
            'omzetThisMonth' => $omzetThisMonth,
            'omzetLastMonth' => $omzetLastMonth,
            'dailyChart' => $dailyChart,
            'peakDay' => $peakDay,
            'peakAmount' => $peakAmount,
            'totalChartAmount' => $totalChartAmount,
        ];
    }
}
