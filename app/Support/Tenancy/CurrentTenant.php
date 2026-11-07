<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\HairColor;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class CurrentTenant
{
    private ?Tenant $tenant = null;

    private ?string $role = null;

    private ?bool $hasWalkinBookingDot = null;

    private ?bool $hasLowColorStockDot = null;

    private ?bool $hasPendingTimeOffDot = null;

    private ?string $firstBranchCode = null;

    private bool $firstBranchResolved = false;

    private ?\App\Models\Subscription $subscription = null;

    private bool $subscriptionResolved = false;

    private ?\App\Models\SubscriptionInvoice $latestPendingInvoice = null;

    private bool $latestPendingInvoiceResolved = false;

    private ?int $pendingTimeOffsCount = null;

    private ?int $superadminPendingInvoicesCount = null;

    private ?string $staffAttendanceStatus = null;

    private bool $staffAttendanceStatusResolved = false;

    private ?\App\Models\Membership $staffMembership = null;

    private bool $staffMembershipResolved = false;

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, Branch>|null
     */
    private ?\Illuminate\Database\Eloquent\Collection $activeAllowedBranches = null;

    /**
     * @var array{queue: int, booking: int}|null
     */
    private ?array $cashierNavBadgeCounts = null;

    public function set(?Tenant $tenant): void
    {
        $this->hasWalkinBookingDot = null;
        $this->hasLowColorStockDot = null;
        $this->hasPendingTimeOffDot = null;
        $this->firstBranchCode = null;
        $this->firstBranchResolved = false;
        $this->subscription = null;
        $this->subscriptionResolved = false;
        $this->latestPendingInvoice = null;
        $this->latestPendingInvoiceResolved = false;
        $this->pendingTimeOffsCount = null;
        $this->superadminPendingInvoicesCount = null;
        $this->staffAttendanceStatus = null;
        $this->staffAttendanceStatusResolved = false;
        $this->staffMembership = null;
        $this->staffMembershipResolved = false;
        $this->activeAllowedBranches = null;
        $this->cashierNavBadgeCounts = null;

        $tenant?->flushMemo();
        \App\Support\Platform\PlatformConfig::flushMemo();

        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function setFirstBranchCode(?string $code): void
    {
        $this->firstBranchCode = $code;
        $this->firstBranchResolved = true;
    }

    public function firstBranchCode(): ?string
    {
        if ($this->firstBranchResolved) {
            return $this->firstBranchCode;
        }

        $this->firstBranchResolved = true;

        if ($this->tenant === null) {
            return null;
        }

        $allowedIds = $this->tenant->allowedBranchIds();

        $this->firstBranchCode = Branch::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->whereIn('id', $allowedIds)
            ->orderBy('id')
            ->value('code');

        return $this->firstBranchCode;
    }

    public function subscription(): ?\App\Models\Subscription
    {
        if ($this->tenant === null) {
            return null;
        }

        return $this->tenant->activeSubscription();
    }

    public function hasWalkinBookingDot(?bool $knownHasPendingBookings = null): bool
    {
        if ($this->hasWalkinBookingDot !== null) {
            return $this->hasWalkinBookingDot;
        }

        if ($this->tenant === null) {
            return $this->hasWalkinBookingDot = false;
        }

        if ($knownHasPendingBookings !== null) {
            return $this->hasWalkinBookingDot = $knownHasPendingBookings;
        }

        return $this->hasWalkinBookingDot = Booking::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending])
            ->exists();
    }

    public function setHasLowColorStockDot(bool $value): void
    {
        $this->hasLowColorStockDot = $value;
    }

    public function hasLowColorStockDot(): bool
    {
        if ($this->hasLowColorStockDot !== null) {
            return $this->hasLowColorStockDot;
        }

        if ($this->tenant === null) {
            return $this->hasLowColorStockDot = false;
        }

        return $this->hasLowColorStockDot = HairColor::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->whereColumn('stock', '<=', DB::raw('min_stock + 2'))
            ->exists();
    }

    public function setHasPendingTimeOffDot(bool $value): void
    {
        $this->hasPendingTimeOffDot = $value;
    }

    public function hasPendingTimeOffDot(): bool
    {
        if ($this->hasPendingTimeOffDot !== null) {
            return $this->hasPendingTimeOffDot;
        }

        if ($this->tenant === null) {
            return $this->hasPendingTimeOffDot = false;
        }

        return $this->hasPendingTimeOffDot = $this->pendingTimeOffsCount() > 0;
    }

    public function pendingTimeOffsCount(): int
    {
        if ($this->pendingTimeOffsCount !== null) {
            return $this->pendingTimeOffsCount;
        }

        if ($this->tenant === null) {
            return $this->pendingTimeOffsCount = 0;
        }

        return $this->pendingTimeOffsCount = \App\Models\BarberTimeOff::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->count();
    }

    public function superadminPendingInvoicesCount(): int
    {
        if ($this->superadminPendingInvoicesCount !== null) {
            return $this->superadminPendingInvoicesCount;
        }

        return $this->superadminPendingInvoicesCount = \App\Models\SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'pending')
            ->count();
    }

    public function staffMembership(?int $userId): ?\App\Models\Membership
    {
        if ($this->staffMembershipResolved) {
            return $this->staffMembership;
        }

        $this->staffMembershipResolved = true;

        if ($this->tenant === null || $userId === null) {
            return $this->staffMembership = null;
        }

        return $this->staffMembership = \App\Models\Membership::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with('branch')
            ->first();
    }

    public function staffAttendanceStatus(?int $userId): ?string
    {
        if ($this->staffAttendanceStatusResolved) {
            return $this->staffAttendanceStatus;
        }

        $this->staffAttendanceStatusResolved = true;

        $staffMembership = $this->staffMembership($userId);

        if ($staffMembership === null) {
            return $this->staffAttendanceStatus = null;
        }

        $todayAtt = \App\Models\Attendance::query()
            ->withoutGlobalScope('tenant')
            ->where('membership_id', $staffMembership->id)
            ->whereDate('date', now()->format('Y-m-d'))
            ->first();

        return $this->staffAttendanceStatus = ($todayAtt !== null && $todayAtt->check_in_at !== null)
            ? 'checked_in'
            : 'not_checked_in';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Branch>
     */
    public function activeAllowedBranches(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->activeAllowedBranches !== null) {
            return $this->activeAllowedBranches;
        }

        if ($this->tenant === null) {
            return $this->activeAllowedBranches = new \Illuminate\Database\Eloquent\Collection();
        }

        return $this->activeAllowedBranches = $this->tenant->branches()
            ->whereIn('id', $this->tenant->allowedBranchIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{queue: int, booking: int}
     */
    public function cashierNavBadgeCounts(): array
    {
        if ($this->cashierNavBadgeCounts !== null) {
            return $this->cashierNavBadgeCounts;
        }

        if ($this->tenant === null) {
            return $this->cashierNavBadgeCounts = ['queue' => 0, 'booking' => 0];
        }

        $tenantId = $this->tenant->id;
        $today = now()->toDateString();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $branchId = null;
        if (auth()->check()) {
            $branchId = \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->value('branch_id');
        }

        return $this->cashierNavBadgeCounts = [
            'queue' => \App\Models\QueueTicket::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('date', $today)
                ->whereIn('status', [\App\Enums\QueueStatus::Waiting, \App\Enums\QueueStatus::Called])
                ->where(function ($sub): void {
                    $sub->where('status', '!=', \App\Enums\QueueStatus::Waiting->value)
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
                ->count(),
            'booking' => Booking::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending])
                ->count(),
        ];
    }

    public function isSubscriptionLocked(): bool
    {
        if ($this->tenant === null) {
            return false;
        }

        return $this->tenant->isSubscriptionLocked();
    }

    public function latestPendingInvoice(): ?\App\Models\SubscriptionInvoice
    {
        if ($this->latestPendingInvoiceResolved) {
            return $this->latestPendingInvoice;
        }

        $this->latestPendingInvoiceResolved = true;

        if ($this->tenant === null) {
            return null;
        }

        return $this->latestPendingInvoice = \App\Models\SubscriptionInvoice::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public function plan(): ?\App\Models\Plan
    {
        return $this->subscription()?->plan ?? $this->tenant?->plan();
    }

    public function planCode(): string
    {
        return $this->plan()?->code ?? 'starter';
    }

    public function canAddBranch(): bool
    {
        return $this->tenant?->canAddBranch() ?? true;
    }

    public function canAddStaff(): bool
    {
        return $this->tenant?->canAddStaff() ?? true;
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->tenant?->hasFeature($featureKey) ?? false;
    }
}
