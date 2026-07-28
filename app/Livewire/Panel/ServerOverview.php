<?php

namespace App\Livewire\Panel;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class ServerOverview extends Component
{
    public array $overview = [];

    public function mount(ServerManager $serverManager): void
    {
        $server = Server::query()->firstOrFail();

        $this->overview = $serverManager
            ->overview($server)
            ->toArray();
    }

    public function generalInformation(): array
    {
        return [
            'Hostname' => $this->overview['hostname'],
            'Operating System' => $this->overview['operatingSystem'],
            'Kernel' => $this->overview['kernel'],
            'User' => $this->overview['user'],
            'Private IP' => $this->overview['privateIp'],
            'Uptime' => $this->overview['uptime'],
        ];
    }

    public function render()
    {
        return view('livewire.panel.server-overview');
    }
}
