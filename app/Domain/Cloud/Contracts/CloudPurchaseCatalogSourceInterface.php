<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;

interface CloudPurchaseCatalogSourceInterface
{
    /**
     * @return list<CloudRegionData>
     */
    public function listPurchaseRegions(): array;

    /**
     * @return list<CloudSizeData>
     */
    public function listPurchaseSizes(
        string $region,
    ): array;

    /**
     * @return list<CloudImageData>
     */
    public function listPurchaseImages(
        string $region,
    ): array;
}
