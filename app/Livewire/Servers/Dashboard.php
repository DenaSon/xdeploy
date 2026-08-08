<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\ActivateServerAction;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('Dashboard')]
final class Dashboard extends Component
{
    public Server $server;

    public function mount(
        Server $server,
        ActivateServerAction $activateServer,
    ): void {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        /*
         * Keep the parent page intentionally lightweight.
         *
         * No SSH connection, readiness inspection, server overview query,
         * service discovery, Docker inspection, or resource collection is
         * allowed in the initial Dashboard request.
         *
         * Independent child widgets own those reads after the shell has
         * already rendered.
         */
        $this->server = $activateServer->handle(
            $user,
            $server,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.servers.dashboard',
        );
    }
}
