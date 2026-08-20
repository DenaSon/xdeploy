<?php

declare(strict_types=1);

namespace App\Livewire\Applications\WordPress;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ManagementPanel extends Component
{
    #[Locked]
    public int $serverId;

    public function mount(int $serverId): void
    {
        $server = $this->authenticatedUser()
            ->servers()
            ->whereKey($serverId)
            ->firstOrFail();

        $this->serverId = (int) $server->getKey();
    }

    public function render(): View
    {
        return view(
            'livewire.applications.wordpress.management-panel',
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
