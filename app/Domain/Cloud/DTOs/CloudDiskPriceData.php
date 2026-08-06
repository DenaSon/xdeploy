<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudDiskPriceData
{
    public function __construct(
        public int $diskGiB,
        public CloudPriceData $hourlyPrice,
        public CloudPriceData $monthlyPrice,
    ) {}
}
