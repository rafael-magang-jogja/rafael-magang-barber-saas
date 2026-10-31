<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\DashboardQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class TvQueueDisplay extends Component
{
    public string $tenantSlug;

    public string $branchCode;

    public function mount(string $tenantSlug, string $branchCode): void
    {
        $this->tenantSlug = $tenantSlug;
        $this->branchCode = $branchCode;
    }

    public function render(DashboardQueryService $dashboardQueryService): View
    {
        $data = $dashboardQueryService->getTvDisplayData($this->tenantSlug, $this->branchCode);

        return view('livewire.tv-queue-display', $data);
    }
}
