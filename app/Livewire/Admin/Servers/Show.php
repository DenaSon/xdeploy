<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Models\Order;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('جزئیات سرور')]
final class Show extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function render(): View
    {
        $server = $this->server->load('user');

        return view(
            'livewire.admin.servers.show',
            [
                'server' => $server,
                'orders' => Order::query()
                    ->where('server_id', $server->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
            ],
        );
    }
}
