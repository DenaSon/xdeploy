<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudSizeData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public int $vCpu,
        public int $memoryMiB,
        public int $diskGiB,
        public ?string $category,
        public ?CloudPriceData $hourlyPrice,
        public ?CloudPriceData $monthlyPrice,
    ) {}
}
