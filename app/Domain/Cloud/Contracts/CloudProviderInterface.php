<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;

interface CloudProviderInterface
{
    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array;

    /**
     * @return list<CloudSizeData>
     */
    public function listSizes(
        string $region,
    ): array;

    /**
     * @return list<CloudImageData>
     */
    public function listImages(
        string $region,
    ): array;
}
