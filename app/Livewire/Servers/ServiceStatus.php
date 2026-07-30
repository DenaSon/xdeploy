<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class ServiceStatus extends Component
{
    /**
     * @var array<int, array{name: string, status: string}>
     */
    public array $services = [];
    public Server $server;
    public function mount(ServerManager $serverManager,Server $server): void
    {
        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->services = $overview['services'];
    }

    public function render()
    {
        return view('livewire.servers.service-status');
    }
}
