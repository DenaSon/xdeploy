<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudQuotaData;

interface CloudQuotaReaderInterface
{
    public function getQuota(
        string $region,
    ): CloudQuotaData;
}
