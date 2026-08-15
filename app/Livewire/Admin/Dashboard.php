<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('داشبورد مدیریت')]
final class Dashboard extends Component
{
    public function render(): View
    {
        return view(
            'livewire.admin.dashboard',
        );
    }
}
