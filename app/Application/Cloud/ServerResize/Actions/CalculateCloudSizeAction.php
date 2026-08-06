<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;

final readonly class CalculateCloudSizeAction
{
    public function __construct(
        private CloudServerResizeCatalogInterface $catalog,
    ) {}

    public function handle(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudSizeData {
        return $this->catalog->calculateSize(
            region: $region,
            sizeId: $sizeId,
            diskGiB: $diskGiB,
        );
    }
}
