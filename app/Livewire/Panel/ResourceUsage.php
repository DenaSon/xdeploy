<?php

namespace App\Livewire\Panel;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class ResourceUsage extends Component
{
    public array $memory = [];

    public array $disk = [];

    public array $loadAverage = [];

    public function mount(ServerManager $serverManager): void
    {

        $server = Server::query()->firstOrFail();

        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->memory = $overview['memory'];
        $this->disk = $overview['disk'];
        $this->loadAverage = $overview['loadAverage'];
    }

    public function render()
    {
        return view('livewire.panel.resource-usage');
    }
}
