<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Application\Actions\GetModulesAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.panel')]
final class Index extends Component
{
    /**
     * @var array<int, array{
     *     type: string,
     *     name: string,
     *     description?: string,
     *     icon?: string,
     *     category?: string,
     * }>
     */
    public array $modules = [];

    public function mount(GetModulesAction $getModules): void
    {
        $this->modules = $getModules->handle();
    }

    public function render()
    {
        return view('livewire.modules.index');
    }
}
