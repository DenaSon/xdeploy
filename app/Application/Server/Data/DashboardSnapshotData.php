<?php

declare(strict_types=1);

namespace App\Application\Server\Data;

final readonly class DashboardSnapshotData
{
    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $cpu
     * @param  array<string, mixed>  $resources
     * @param  list<array<string, mixed>>  $services
     * @param  array<string, mixed>  $docker
     * @param  list<string>  $loadedSegments
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public array $identity = [],
        public array $cpu = [],
        public array $resources = [],
        public array $services = [],
        public array $docker = [],
        public array $loadedSegments = [],
        public array $errors = [],
    ) {}

    public function hasAnySegment(): bool
    {
        return $this->loadedSegments !== [];
    }
}
