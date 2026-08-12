<?php

declare(strict_types=1);

namespace App\Livewire\Domains;

use App\Application\Applications\Marzban\MarzbanManager;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
final class Index extends Component
{
    #[Locked]
    public int $serverId;

    /**
     * @var array<string, mixed>
     */
    public array $management = [];

    public bool $loaded = false;

    public bool $unavailable = false;

    public bool $showDrawer = false;

    public function mount(Server $server): void
    {
        $server = Server::query()
            ->ownedBy($this->authenticatedUser())
            ->whereKey($server->getKey())
            ->firstOrFail();

        $this->serverId = (int) $server->getKey();
    }

    public function loadDomains(MarzbanManager $manager): void
    {
        $this->loadManagement($manager);
    }

    public function refreshDomains(MarzbanManager $manager): void
    {
        $this->loadManagement($manager);
    }

    public function openDomainDrawer(): void
    {
        if (! $this->canAddDomain()) {
            return;
        }

        $this->showDrawer = true;
    }

    /**
     * @param  array<string, mixed>  $management
     */
    #[On('marzban-management-updated.{serverId}')]
    public function updateManagement(array $management): void
    {
        $applicationState = data_get(
            $management,
            'application.state',
        );

        $httpsState = data_get(
            $management,
            'https.state',
        );

        if (
            ! is_string($applicationState)
            || ApplicationState::tryFrom($applicationState) === null
            || ! is_string($httpsState)
            || MarzbanHttpsState::tryFrom($httpsState) === null
        ) {
            return;
        }

        $this->management = $management;
        $this->loaded = true;
        $this->unavailable = false;
        $this->showDrawer = false;
    }

    public function render(): View
    {
        return view(
            'livewire.domains.index',
            [
                'server' => $this->server(),
            ],
        )->title('دامنه‌ها');
    }

    private function loadManagement(MarzbanManager $manager): void
    {
        $this->unavailable = false;

        try {
            $this->management = $manager
                ->overview(
                    user: $this->authenticatedUser(),
                    server: $this->server(),
                )
                ->toArray();

            $this->unavailable = false;
        } catch (Throwable $exception) {
            report($exception);

            $this->management = [];
            $this->unavailable = true;
        } finally {
            $this->loaded = true;
        }
    }

    private function canAddDomain(): bool
    {
        return $this->loaded
            && ! $this->unavailable
            && data_get(
                $this->management,
                'application.is_installed',
                false,
            ) === true
            && data_get(
                $this->management,
                'https.state',
            ) === MarzbanHttpsState::Disabled->value;
    }

    private function server(): Server
    {
        return Server::query()
            ->ownedBy($this->authenticatedUser())
            ->findOrFail($this->serverId);
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
