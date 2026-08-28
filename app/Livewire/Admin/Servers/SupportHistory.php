<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Application\Server\Actions\ListServerSupportHistoryAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class SupportHistory extends Component
{
    public int $serverId;

    public bool $supportHistoryOpen = false;

    public function mount(int $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function openSupportHistory(): void
    {
        $this->supportHistoryOpen = true;
    }

    public function closeSupportHistory(): void
    {
        $this->supportHistoryOpen = false;
    }

    public function render(): View
    {
        $history = $this->supportHistoryOpen
            ? app(ListServerSupportHistoryAction::class)->handle(
                admin: $this->adminUser(),
                serverId: $this->serverId,
                limit: 50,
            )
            : [];

        return view(
            'livewire.admin.servers.support-history',
            [
                'history' => $history,
            ],
        );
    }

    private function adminUser(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        return $user;
    }
}
