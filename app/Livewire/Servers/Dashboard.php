<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

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
    ): void {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->server = $user
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();
    }

    public function render(): View
    {
        return view(
            'livewire.servers.dashboard',
        );
    }
}
