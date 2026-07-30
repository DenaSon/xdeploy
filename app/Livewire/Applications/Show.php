<?php

namespace App\Livewire\Applications;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.panel')]
class Show extends Component
{
    public function render()
    {
        return view('livewire.applications.show');
    }
}
