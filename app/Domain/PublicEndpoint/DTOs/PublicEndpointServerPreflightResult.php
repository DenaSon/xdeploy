<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\DTOs;

final readonly class PublicEndpointServerPreflightResult
{
    /**
     * @param  array<int, array<string, int|string|bool>>  $ports
     */
    public function __construct(
        public string $layoutState,
        public bool $layoutSupported,
        public bool $managedCaddyDetected,
        public bool $hasPortConflict,
        public bool $ready,
        public array $ports = [],
    ) {}

    /**
     * @return array{
     *   layout_state: string,
     *   layout_supported: bool,
     *   managed_caddy_detected: bool,
     *   has_port_conflict: bool,
     *   ready: bool,
     *   ports: array<int, array<string, int|string|bool>>
     * }
     */
    public function toArray(): array
    {
        return [
            'layout_state' => $this->layoutState,
            'layout_supported' => $this->layoutSupported,
            'managed_caddy_detected' => $this->managedCaddyDetected,
            'has_port_conflict' => $this->hasPortConflict,
            'ready' => $this->ready,
            'ports' => $this->ports,
        ];
    }
}
