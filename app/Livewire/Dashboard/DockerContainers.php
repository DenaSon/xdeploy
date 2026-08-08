<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\Actions\GetDockerRuntimeAction;
use App\Application\Server\ServerReadExecutor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;

#[Lazy]
final class DockerContainers extends ServerDashboardWidget
{
    private const int CACHE_TTL_SECONDS = 20;

    /**
     * @var array<string, mixed>
     */
    public array $docker = [];

    public function mount(
        int $serverId,
        GetDockerRuntimeAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->initializeServer(
            $serverId,
        );

        $this->load(
            action: $action,
            executor: $executor,
        );
    }

    public function refreshData(
        GetDockerRuntimeAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->load(
            action: $action,
            executor: $executor,
        );
    }

    public function reload(
        GetDockerRuntimeAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->load(
            action: $action,
            executor: $executor,
            fresh: true,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.dashboard.docker-containers',
        );
    }

    public function placeholder(): View
    {
        return view(
            'livewire.dashboard.placeholders.card',
            [
                'rows' => 3,
                'minHeight' => 'min-h-64',
            ],
        );
    }

    private function load(
        GetDockerRuntimeAction $action,
        ServerReadExecutor $executor,
        bool $fresh = false,
    ): void {
        $docker = $this->read(
            executor: $executor,
            read: static fn () => $action->handle(),
            cacheSegment: 'docker',
            cacheTtlSeconds: self::CACHE_TTL_SECONDS,
            fresh: $fresh,
        );

        if ($docker === null) {
            $this->docker = [];

            return;
        }

        $this->docker = $docker
            ->toArray();
    }
}
