<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Actions\GetApplicationsAction;
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
    public array $applications = [];

    public function mount(GetApplicationsAction $getApplications): void
    {
        $this->applications = $getApplications->handle();
    }

    public function render()
    {
        return view('livewire.applications.index');
    }
}
