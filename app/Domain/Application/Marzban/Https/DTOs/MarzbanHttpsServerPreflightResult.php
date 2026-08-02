<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;

final readonly class MarzbanHttpsServerPreflightResult
{
    public function __construct(
        public MarzbanHttpsLayoutState $layoutState,
        public bool $managedCaddyDetected,
        public MarzbanHttpsPortInfo $port80,
        public MarzbanHttpsPortInfo $port443,
    ) {}

    public function layoutSupported(): bool
    {
        return $this->layoutState
            === MarzbanHttpsLayoutState::Supported;
    }

    public function hasPortConflict(): bool
    {
        return $this->port80->hasConflict()
            || $this->port443->hasConflict();
    }

    public function ready(): bool
    {
        return $this->layoutSupported()
            && $this->port80->availableForXDeploy()
            && $this->port443->availableForXDeploy();
    }

    /**
     * @return array{
     *     layout_state: string,
     *     layout_supported: bool,
     *     managed_caddy_detected: bool,
     *     has_port_conflict: bool,
     *     ready: bool,
     *     ports: array{
     *         80: array<string, int|string|bool>,
     *         443: array<string, int|string|bool>
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'layout_state' => $this->layoutState->value,
            'layout_supported' => $this->layoutSupported(),
            'managed_caddy_detected' => $this->managedCaddyDetected,
            'has_port_conflict' => $this->hasPortConflict(),
            'ready' => $this->ready(),
            'ports' => [
                80 => $this->port80->toArray(),
                443 => $this->port443->toArray(),
            ],
        ];
    }
}
