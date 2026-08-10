<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Actions\ListApplicationCatalogAction;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('برنامه‌ها')]
final class Index extends Component
{
    #[Locked]
    public int $serverId;

    /**
     * @var array<int, array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     short_description: string,
     *     description: string|null,
     *     icon: string|null,
     * }>
     */
    public array $applications = [];

    public function mount(
        Server $server,
        ListApplicationCatalogAction $listApplicationCatalog,
    ): void {
        $server = $this->resolveOwnedServer(
            $server,
        );

        $this->serverId = (int) $server->getKey();

        /*
         * Catalog data comes exclusively from local persistence.
         * No SSH connection or runtime application inspection belongs here.
         */
        $this->applications =
            $listApplicationCatalog->execute();
    }

    public function render(): View
    {
        return view(
            'livewire.applications.index',
            [
                'server' => $this->server(),
            ],
        );
    }

    private function resolveOwnedServer(
        Server $server,
    ): Server {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();
    }

    private function server(): Server
    {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey(
                $this->serverId,
            )
            ->firstOrFail();
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
