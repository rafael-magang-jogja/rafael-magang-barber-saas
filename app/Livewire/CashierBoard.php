<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\DashboardQueryService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

final class CashierBoard extends Component
{
    #[Url(as: 'branch_id')]
    public ?int $branchId = null;

    #[Url(as: 'barber_id')]
    public ?int $barberId = null;

    #[Url(as: 'date_filter')]
    public string $dateFilter = 'today';

    #[Url(as: 'custom_date')]
    public ?string $customDate = null;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    public function mount(
        ?int $branchId = null,
        ?int $barberId = null,
        ?string $dateFilter = null,
        ?string $customDate = null,
        ?string $statusFilter = null,
    ): void {
        $tenantId = app(\App\Support\Tenancy\CurrentTenant::class)->id();
        $cashierBranchId = auth()->check()
            ? \App\Models\Membership::query()
                ->withoutGlobalScope('tenant')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->value('branch_id')
            : null;

        if ($cashierBranchId !== null) {
            $this->branchId = (int) $cashierBranchId;
        } elseif ($branchId !== null) {
            $this->branchId = $branchId;
        } elseif (request()->filled('branch_id')) {
            $this->branchId = request()->integer('branch_id');
        }

        if ($barberId !== null) {
            $this->barberId = $barberId;
        } elseif (request()->filled('barber_id')) {
            $this->barberId = request()->integer('barber_id');
        }

        if ($dateFilter !== null) {
            $this->dateFilter = $dateFilter;
        } elseif (request()->filled('date_filter')) {
            $this->dateFilter = (string) request()->input('date_filter');
        }

        if ($customDate !== null) {
            $this->customDate = $customDate;
        } elseif (request()->filled('custom_date')) {
            $this->customDate = (string) request()->input('custom_date');
        }

        if ($statusFilter !== null) {
            $this->statusFilter = $statusFilter;
        } elseif (request()->filled('status')) {
            $this->statusFilter = (string) request()->input('status');
        }
    }

    public function render(DashboardQueryService $dashboardQueryService): View
    {
        $selectedDate = now()->toDateString();
        if ($this->dateFilter === 'yesterday') {
            $selectedDate = now()->subDay()->toDateString();
        } elseif ($this->dateFilter === 'custom' && ! empty($this->customDate)) {
            $selectedDate = Carbon::parse($this->customDate)->toDateString();
        }

        $data = $dashboardQueryService->getCashierBoardData(auth()->id(), $selectedDate);

        if (! empty($data['cashierBranchId'])) {
            $this->branchId = (int) $data['cashierBranchId'];
        }

        $data['filterBranchId'] = $this->branchId;
        $data['filterBarberId'] = $this->barberId;
        $data['filterDateType'] = $this->dateFilter;
        $data['filterStatus'] = $this->statusFilter;
        $data['currentDateFilter'] = $this->dateFilter;
        $data['customDate'] = $this->customDate;
        $data['selectedDate'] = $selectedDate;

        return view('livewire.cashier-board', $data);
    }
}
