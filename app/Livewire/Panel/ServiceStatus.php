<?php

declare(strict_types=1);

namespace App\Livewire\Panel;

use App\Application\Server\ServerManager;
use App\Models\Server;
use Livewire\Component;

class ServiceStatus extends Component
{
    /**
     * @var array<int, array{name: string, status: string}>
     */
    public array $services = [];

    public function mount(ServerManager $serverManager): void
    {
        $server = Server::query()->firstOrFail();

        $overview = $serverManager
            ->overview($server)
            ->toArray();

        $this->services = $overview['services'];
    }

    public function render()
    {
        return view('livewire.panel.service-status');
    }
}
