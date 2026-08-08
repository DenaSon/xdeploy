<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\Actions\GetResourceUsageAction;
use App\Application\Server\ServerReadExecutor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;

#[Lazy]
final class ResourceUsage extends ServerDashboardWidget
{
    /**
     * @var array<string, mixed>
     */
    public array $resources = [];

    public function mount(
        int $serverId,
        GetResourceUsageAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->initializeServer(
            $serverId,
        );

        $this->load(
            $action,
            $executor,
        );
    }

    public function refreshData(
        GetResourceUsageAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->load(
            $action,
            $executor,
        );
    }

    public function reload(
        GetResourceUsageAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->load(
            $action,
            $executor,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.dashboard.resource-usage',
        );
    }

    public function placeholder(): View
    {
        return view(
            'livewire.dashboard.placeholders.card',
            [
                'rows' => 3,
                'minHeight' => 'min-h-72',
            ],
        );
    }

    private function load(
        GetResourceUsageAction $action,
        ServerReadExecutor $executor,
    ): void {
        $resources = $this->read(
            executor: $executor,
            read: static fn () => $action->handle(),
        );

        if ($resources === null) {
            $this->resources = [];

            return;
        }

        $this->resources = $resources
            ->toArray();
    }
}
