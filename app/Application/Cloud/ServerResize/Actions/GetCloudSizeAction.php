<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;

final readonly class GetCloudSizeAction
{
    public function __construct(
        private CloudServerResizeCatalogInterface $catalog,
    ) {}

    public function handle(
        string $region,
        string $sizeId,
    ): CloudSizeData {
        return $this->catalog->findSize(
            region: $region,
            sizeId: $sizeId,
        );
    }
}
