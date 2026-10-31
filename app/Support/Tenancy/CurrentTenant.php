<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\HairColor;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
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

    public function set(?Tenant $tenant): void
    {
        if ($this->tenant?->id !== $tenant?->id) {
            $this->hasWalkinBookingDot = null;
            $this->hasLowColorStockDot = null;
            $this->hasPendingTimeOffDot = null;
            $this->firstBranchCode = null;
            $this->firstBranchResolved = false;
            $this->subscription = null;
            $this->subscriptionResolved = false;
        }

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
        if ($this->subscriptionResolved) {
            return $this->subscription;
        }

        $this->subscriptionResolved = true;

        if ($this->tenant === null) {
            return null;
        }

        $this->subscription = \App\Models\Subscription::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->with('plan')
            ->orderByRaw("CASE WHEN status IN ('active', 'trialing') THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        return $this->subscription;
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
            ->where('tenant_id', $this->tenant->id)
            ->whereDate('scheduled_at', now()->toDateString())
            ->where('status', BookingStatus::Confirmed)
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

        return $this->hasPendingTimeOffDot = \App\Models\BarberTimeOff::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->exists();
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
        if ($this->tenant === null) {
            return null;
        }

        return \App\Models\SubscriptionInvoice::query()
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
