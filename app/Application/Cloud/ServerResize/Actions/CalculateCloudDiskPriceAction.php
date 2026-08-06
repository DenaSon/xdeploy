<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;

final readonly class CalculateCloudDiskPriceAction
{
    public function __construct(
        private CloudServerResizeCatalogInterface $catalog,
    ) {}

    public function handle(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData {
        return $this->catalog->calculateDiskPrice(
            region: $region,
            sizeId: $sizeId,
            diskGiB: $diskGiB,
        );
    }
}
