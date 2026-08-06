<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;

interface CloudServerResizeCatalogInterface
{
    /**
     * Return the resize plans available for an existing cloud server.
     *
     * @return list<CloudSizeData>
     */
    public function listServerResizePlans(
        string $region,
        string $serverId,
    ): array;

    /**
     * Return the details of a specific cloud size.
     */
    public function findSize(
        string $region,
        string $sizeId,
    ): CloudSizeData;

    /**
     * Calculate the final size information using a custom disk size.
     */
    public function calculateSize(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudSizeData;

    /**
     * Calculate the disk price for a size and disk capacity.
     */
    public function calculateDiskPrice(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData;
}
