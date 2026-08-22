<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class Landing extends Component
{
    public function render(): View
    {
        return view('livewire.public.landing')
            ->layout('layouts.public', [
                'title' => 'از سرور تا سرویس، در یک پنل',
            ]);
    }
}
