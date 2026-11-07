<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\PlatformAnnouncementService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PlatformAnnouncementPopup extends Component
{
    public ?string $role = null;

    public bool $permanently = false;

    public bool $isDismissedInComponent = false;

    public function mount(?string $role = null): void
    {
        $this->role = $role;
    }

    public function dismiss(bool $permanently = false): void
    {
        $user = auth()->user();
        if ($user instanceof User) {
            $service = app(PlatformAnnouncementService::class);
            $announcement = $service->getPendingAnnouncementForUser($user, $this->role);

            if ($announcement !== null) {
                $service->dismiss($announcement, $user, $permanently);
            }
        }

        $this->isDismissedInComponent = true;
    }

    public function dismissWithCheckbox(): void
    {
        $this->dismiss($this->permanently);
    }

    public function render(): View
    {
        $announcement = null;
        $user = auth()->user();

        if ($user instanceof User && ! $this->isDismissedInComponent) {
            $announcement = app(PlatformAnnouncementService::class)->getPendingAnnouncementForUser($user, $this->role);
        }

        return view('livewire.platform-announcement-popup', [
            'pendingAnnouncement' => $announcement,
        ]);
    }
}
