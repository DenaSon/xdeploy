<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('اطلاعات سرور')]
final class Details extends Component
{
    #[Locked]
    public int $serverId;

    public function mount(Server $server): void
    {
        $ownedServer = $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        $this->serverId = (int) $ownedServer->getKey();
    }

    public function render(): View
    {
        $server = $this->ownedServer();

        return view(
            'livewire.servers.details',
            [
                'server' => $server,
                'sshCommand' => $this->sshCommand($server),
            ],
        );
    }

    private function ownedServer(): Server
    {
        return $this
            ->authenticatedUser()
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

    private function sshCommand(Server $server): ?string
    {
        $host = trim(
            (string) $server->host,
        );

        $username = trim(
            (string) $server->username,
        );

        if ($host === '' || $username === '') {
            return null;
        }

        return sprintf(
            'ssh %s@%s -p %d',
            $username,
            $host,
            $server->port,
        );
    }
}
