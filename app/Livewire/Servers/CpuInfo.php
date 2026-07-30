<?php

namespace App\Livewire\Servers;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class CpuInfo extends Component
{
    public array $cpu = [];

    public Server $server;

    public function mount(ServerManager $serverManager, Server $server): void
    {

        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->cpu = $overview['cpu'];
    }

    public function render()
    {
        return view('livewire.servers.cpu-info');
    }
}
