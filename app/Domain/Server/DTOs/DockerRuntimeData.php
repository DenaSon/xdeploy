<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class DockerRuntimeData
{
    /**
     * @param  list<DockerContainerData>  $containers
     */
    public function __construct(
        public bool $installed,
        public bool $accessible,
        public array $containers = [],
    ) {}

    public static function notInstalled(): self
    {
        return new self(
            installed: false,
            accessible: false,
        );
    }

    public static function unavailable(): self
    {
        return new self(
            installed: true,
            accessible: false,
        );
    }

    /**
     * @param  list<DockerContainerData>  $containers
     */
    public static function available(
        array $containers,
    ): self {
        return new self(
            installed: true,
            accessible: true,
            containers: $containers,
        );
    }

    public function runningCount(): int
    {
        return count(
            array_filter(
                $this->containers,
                static fn (
                    DockerContainerData $container,
                ): bool => $container->isRunning(),
            ),
        );
    }

    /**
     * @return array{
     *     installed: bool,
     *     accessible: bool,
     *     running_count: int,
     *     total_count: int,
     *     containers: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'installed' => $this->installed,
            'accessible' => $this->accessible,
            'running_count' => $this->runningCount(),
            'total_count' => count(
                $this->containers,
            ),
            'containers' => array_map(
                static fn (
                    DockerContainerData $container,
                ): array => $container->toArray(),
                $this->containers,
            ),
        ];
    }
}
