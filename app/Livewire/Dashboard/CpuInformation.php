<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\Actions\GetCpuInformationAction;
use App\Application\Server\ServerReadExecutor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;

#[Lazy]
final class CpuInformation extends ServerDashboardWidget
{
    private const int CACHE_TTL_SECONDS = 600;

    /**
     * @var array<string, mixed>
     */
    public array $cpu = [];

    public function mount(
        int $serverId,
        GetCpuInformationAction $action,
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

    public function reload(
        GetCpuInformationAction $action,
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
            'livewire.dashboard.cpu-information',
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
        GetCpuInformationAction $action,
        ServerReadExecutor $executor,
        bool $fresh = false,
    ): void {
        $cpu = $this->read(
            executor: $executor,
            read: static fn () => $action->handle(),
            cacheSegment: 'cpu',
            cacheTtlSeconds: self::CACHE_TTL_SECONDS,
            fresh: $fresh,
        );

        if ($cpu === null) {
            $this->cpu = [];

            return;
        }

        $this->cpu = $cpu
            ->toArray();
    }
}
