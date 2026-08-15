<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('جزئیات سفارش')]
final class Show extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function render(): View
    {
        $order = $this->order->load([
            'user',
            'historicalServer',
            'payments',
        ]);

        return view(
            'livewire.admin.orders.show',
            ['order' => $order],
        );
    }
}
