<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('جزئیات کاربر')]
final class Show extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function render(): View
    {
        $user = $this->user
            ->load('profile')
            ->loadCount('servers');

        return view(
            'livewire.admin.users.show',
            [
                'user' => $user,
                'servers' => $user
                    ->servers()
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'orders' => Order::query()
                    ->with('historicalServer')
                    ->where('user_id', $user->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
            ],
        );
    }
}
