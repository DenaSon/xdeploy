<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;

interface RefreshableCloudCatalogReaderInterface extends CloudCatalogReaderInterface
{
    /**
     * @return list<CloudRegionData>
     */
    public function refreshRegions(): array;

    /**
     * @return list<CloudSizeData>
     */
    public function refreshSizes(
        string $region,
    ): array;

    /**
     * @return list<CloudImageData>
     */
    public function refreshImages(
        string $region,
    ): array;
}
