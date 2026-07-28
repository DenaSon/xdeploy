<?php

namespace App\Livewire\Panel;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class CpuInfo extends Component
{
    public array $cpu = [];

    public function mount(ServerManager $serverManager): void
    {
        $server = Server::query()->firstOrFail();

        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->cpu = $overview['cpu'];
    }

    public function render()
    {
        return view('livewire.panel.cpu-info');
    }
}
