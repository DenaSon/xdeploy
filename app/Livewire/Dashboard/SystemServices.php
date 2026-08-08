<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\Actions\GetSystemServicesAction;
use App\Application\Server\ServerReadExecutor;
use App\Domain\Server\DTOs\SystemServiceData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;

#[Defer]
final class SystemServices extends ServerDashboardWidget
{
    private const int CACHE_TTL_SECONDS = 30;

    /**
     * @var list<array<string, mixed>>
     */
    public array $services = [];

    public function mount(
        int $serverId,
        GetSystemServicesAction $action,
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
        GetSystemServicesAction $action,
        ServerReadExecutor $executor,
    ): void {
        $this->load(
            action: $action,
            executor: $executor,
        );
    }

    public function reload(
        GetSystemServicesAction $action,
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
            'livewire.dashboard.system-services',
        );
    }

    public function placeholder(): View
    {
        return view(
            'livewire.dashboard.placeholders.card',
            [
                'rows' => 6,
                'minHeight' => 'min-h-[32rem]',
            ],
        );
    }

    private function load(
        GetSystemServicesAction $action,
        ServerReadExecutor $executor,
        bool $fresh = false,
    ): void {
        $services = $this->read(
            executor: $executor,
            read: static fn () => $action->handle(),
            cacheSegment: 'services',
            cacheTtlSeconds: self::CACHE_TTL_SECONDS,
            fresh: $fresh,
        );

        if ($services === null) {
            $this->services = [];

            return;
        }

        $this->services = array_map(
            static fn (
                SystemServiceData $service,
            ): array => $service->toArray(),
            $services,
        );
    }
}
