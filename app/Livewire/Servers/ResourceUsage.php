<?php

namespace App\Livewire\Servers;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class ResourceUsage extends Component
{
    public array $memory = [];

    public array $disk = [];

    public Server $server;

    public array $loadAverage = [];

    public function mount(ServerManager $serverManager, Server $server): void
    {

        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->memory = $overview['memory'];
        $this->disk = $overview['disk'];
        $this->loadAverage = $overview['loadAverage'];
    }

    public function render()
    {
        return view('livewire.servers.resource-usage');
    }
}
