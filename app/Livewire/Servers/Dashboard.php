<?php

namespace App\Livewire\Servers;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function render()
    {
        return view('livewire.servers.dashboard');
    }
}
