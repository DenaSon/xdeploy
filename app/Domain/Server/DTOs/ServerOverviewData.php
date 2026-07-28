<?php

namespace App\Domain\Server\DTOs;

readonly class ServerOverviewData
{
    /**
     * @param  ServiceStatusData[]  $services
     */
    /**
     * @param  ServiceStatusData[]  $services
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
    ) {}

    /**
     * Convert the DTO to an array.
     */
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
                fn (ServiceStatusData $service) => $service->toArray(),
                $this->services,
            ),
        ];
    }
}
