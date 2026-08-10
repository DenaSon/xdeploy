<?php

declare(strict_types=1);

namespace App\Livewire\Applications\Marzban;

use App\Application\Applications\Marzban\MarzbanManager;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

final class ManagementPanel extends Component
{
    #[Locked]
    public int $serverId;

    /**
     * @var array<string, mixed>
     */
    public array $management = [];

    public bool $managementUnavailable = false;

    public function mount(
        int $serverId,
        MarzbanManager $manager,
    ): void {
        $this->serverId = $serverId;

        $this->loadManagement(
            $manager,
        );
    }

    public function refreshManagement(
        MarzbanManager $manager,
    ): void {
        $this->loadManagement(
            $manager,
        );
    }

    /**
     * @param array<string, mixed> $management
     */
    #[On('marzban-management-updated.{serverId}')]
    public function updateManagement(
        array $management,
    ): void {
        $setupState = data_get(
            $management,
            'setup.state',
        );

        $admins = data_get(
            $management,
            'setup.admins',
        );

        $httpsState = data_get(
            $management,
            'https.state',
        );

        if (
            ! is_string($setupState)
            || MarzbanSetupState::tryFrom($setupState) === null
            || ! is_array($admins)
            || ! is_string($httpsState)
            || MarzbanHttpsState::tryFrom($httpsState) === null
        ) {
            return;
        }

        foreach ($admins as $admin) {
            if (
                ! is_array($admin)
                || ! isset($admin['username'])
                || ! is_string($admin['username'])
                || trim($admin['username']) === ''
            ) {
                return;
            }
        }

        $this->management = $management;
        $this->managementUnavailable = false;
    }

    #[On('marzban-setup-completed.{serverId}')]
    public function markSetupCompleted(
        MarzbanManager $manager,
    ): void {
        /*
         * Do not synthesize a local "complete" state.
         *
         * Reload the real server snapshot so the UI also receives the
         * detected administrator list and remains consistent with Marzban.
         */
        $this->loadManagement(
            $manager,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.applications.marzban.management-panel',
        );
    }

    private function loadManagement(
        MarzbanManager $manager,
    ): void {
        try {
            $user = $this->authenticatedUser();

            $server = Server::query()
                ->ownedBy($user)
                ->findOrFail(
                    $this->serverId,
                );

            $this->management = $manager
                ->overview(
                    user: $user,
                    server: $server,
                )
                ->toArray();

            $this->managementUnavailable = false;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->management = [];
            $this->managementUnavailable = true;
        }
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
