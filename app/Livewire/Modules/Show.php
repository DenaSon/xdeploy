<?php

namespace App\Livewire\Modules;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.panel')]
class Show extends Component
{
    public function render()
    {
        return view('livewire.modules.show');
    }
}
