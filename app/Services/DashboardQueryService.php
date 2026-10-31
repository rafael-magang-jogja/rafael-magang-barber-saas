<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\WorkStatus;
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
        $branches = $this->branchRepository->getActiveBranches();
        $todayAttendance = null;
        $branchQrToken = null;

        if ($userId !== null) {
            $membership = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($membership !== null) {
                $todayAttendance = \App\Models\Attendance::query()
                    ->withoutGlobalScope('tenant')
                    ->where('membership_id', $membership->id)
                    ->where('date', now()->format('Y-m-d'))
                    ->first();

                $branchQrToken = $membership->branch?->qr_token ?? $branches->first()?->qr_token;
            }
        }

        if ($branchQrToken === null) {
            $branchQrToken = $branches->first()?->qr_token;
        }

        return [
            'tickets' => $this->queueRepository->getTodayTicketsGroupedByStatus($targetDate),
            'branches' => $branches,
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'activeTab' => 'board',
            'todayAttendance' => $todayAttendance,
            'branchQrToken' => $branchQrToken,
            'selectedDate' => $targetDate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierWalkInData(): array
    {
        return [
            'branches' => $this->branchRepository->getActiveBranches(),
            'services' => $this->catalogRepository->getActiveServices(),
            'hairColors' => $this->catalogRepository->getAvailableHairColors(),
            'barbers' => $this->staffRepository->getActiveBarbers(withRelations: true),
            'activeTab' => 'walkin',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashierBookingsData(): array
    {
        $allBookings = $this->queueRepository->getAllPendingBookings();
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
        $tipToday = $barberMembershipId !== null
            ? (int) \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('payment_status', 'paid')
                ->whereDate('paid_at', now()->format('Y-m-d'))
                ->sum('tip_amount')
            : 0;

        $todayAttendance = $barberMembershipId !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->where('date', now()->format('Y-m-d'))
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
    public function getCustomerPortalData(string $tenantSlug, ?string $ticketNumber, ?int $userId, ?string $branchCodeOrId = null): array
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

        $barbers = $selectedBranch !== null
            ? $this->staffRepository->getBarbersForBranch($tenant, $selectedBranch->id)
            : $this->staffRepository->getActiveBarbers($tenant, withRelations: true);

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'services' => $this->catalogRepository->getActiveServices($tenant),
            'hairColors' => $this->catalogRepository->getAvailableHairColors($tenant),
            'barbers' => $barbers,
            'activeTicket' => $this->queueRepository->findActiveTicketForCustomer($tenant, $ticketNumber, $userId),
            'recentInvoices' => $this->orderRepository->getRecentOrdersForCustomer($tenant->id, $userId, $ticketNumber),
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
    public function getCashierAttendanceData(int $userId): array
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
        $todayAttendance = $membership !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->whereDate('date', $today)
                ->first()
            : null;

        $recentAttendances = $membership !== null
            ? \App\Models\Attendance::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $membership->id)
                ->orderByDesc('date')
                ->limit(14)
                ->get()
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

        $periodTip = $barberMembershipId !== null
            ? (int) \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->sum('tip_amount')
            : 0;

        $periodCompleted = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->count()
            : 0;

        // Statistik acuan cepat
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $commissionToday = $barberMembershipId !== null
            ? (int) \App\Models\CommissionEntry::query()
                ->withoutGlobalScope('tenant')
                ->where('membership_id', $barberMembershipId)
                ->whereDate('earned_at', $today)
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
                ->whereDate('date', $today)
                ->count()
            : 0;

        $completedThisMonth = $barberMembershipId !== null
            ? \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('status', QueueStatus::Completed)
                ->where('date', '>=', $thisMonth->toDateString())
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

        // Riwayat Tip Pelanggan (Hanya waktu dan nominal - TANPA NAMA PELANGGAN demi privasi)
        $recentTips = $barberMembershipId !== null
            ? \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('payment_status', 'paid')
                ->where('tip_amount', '>', 0)
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->latest('paid_at')
                ->limit(50)
                ->get(['id', 'number', 'tip_amount', 'paid_at'])
            : collect();

        $tipToday = $barberMembershipId !== null
            ? (int) \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('payment_status', 'paid')
                ->whereDate('paid_at', $today)
                ->sum('tip_amount')
            : 0;

        $tipThisMonth = $barberMembershipId !== null
            ? (int) \App\Models\Order::query()
                ->withoutGlobalScope('tenant')
                ->where('barber_membership_id', $barberMembershipId)
                ->where('payment_status', 'paid')
                ->where('paid_at', '>=', $thisMonth)
                ->sum('tip_amount')
            : 0;

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
}
