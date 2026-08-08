<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class ServerOverviewData
{
    /**
     * @param  list<SystemServiceData>  $services
     */
    public function __construct(
        public string $hostname,
        public string $operatingSystem,
        public string $kernel,
        public string $uptime,
        public string $user,
        public string $privateIp,
        public CpuInfoData $cpu,
        public MemoryInfoData $memory,
        public DiskInfoData $disk,
        public LoadAverageData $loadAverage,
        public array $services = [],
        public ?DockerRuntimeData $docker = null,
    ) {}

    public function toArray(): array
    {
        return [
            'hostname' => $this->hostname,
            'operatingSystem' => $this->operatingSystem,
            'kernel' => $this->kernel,
            'uptime' => $this->uptime,
            'user' => $this->user,
            'privateIp' => $this->privateIp,

            'cpu' => $this->cpu->toArray(),
            'memory' => $this->memory->toArray(),
            'disk' => $this->disk->toArray(),
            'loadAverage' => $this->loadAverage->toArray(),

            'services' => array_map(
                static fn (
                    SystemServiceData $service,
                ): array => $service->toArray(),
                $this->services,
            ),

            'docker' => $this->docker?->toArray()
                ?? [
                    'installed' => false,
                    'accessible' => false,
                    'running_count' => 0,
                    'total_count' => 0,
                    'containers' => [],
                ],
        ];
    }
}
