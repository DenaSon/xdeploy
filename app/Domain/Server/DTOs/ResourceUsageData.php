<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class ResourceUsageData
{
    public function __construct(
        public MemoryInfoData $memory,
        public DiskInfoData $disk,
        public LoadAverageData $loadAverage,
    ) {}

    public function toArray(): array
    {
        return [
            'memory' => $this->memory->toArray(),
            'disk' => $this->disk->toArray(),
            'loadAverage' => $this->loadAverage->toArray(),
        ];
    }
}
