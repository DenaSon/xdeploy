<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\Actions\GetServerIdentityAction;
use App\Application\Server\ServerReadExecutor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;

#[Defer]
final class ServerOverview extends ServerDashboardWidget
{
    private const int CACHE_TTL_SECONDS = 300;

    /**
     * @var array<string, mixed>
     */
    public array $identity = [];

    public function mount(
        int $serverId,
        GetServerIdentityAction $action,
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
        GetServerIdentityAction $action,
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
            'livewire.dashboard.server-overview',
        );
    }

    public function placeholder(): View
    {
        return view(
            'livewire.dashboard.placeholders.card',
            [
                'rows' => 3,
                'minHeight' => 'min-h-80',
            ],
        );
    }

    private function load(
        GetServerIdentityAction $action,
        ServerReadExecutor $executor,
        bool $fresh = false,
    ): void {
        $identity = $this->read(
            executor: $executor,
            read: static fn () => $action->handle(),
            cacheSegment: 'identity',
            cacheTtlSeconds: self::CACHE_TTL_SECONDS,
            fresh: $fresh,
        );

        if ($identity === null) {
            $this->identity = [];

            return;
        }

        $this->identity = $identity
            ->toArray();
    }
}
