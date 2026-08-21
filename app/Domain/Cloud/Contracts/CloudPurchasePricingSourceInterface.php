<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudDiskPriceData;

interface CloudPurchasePricingSourceInterface
{
    public function calculatePurchaseDiskPrice(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData;
}
