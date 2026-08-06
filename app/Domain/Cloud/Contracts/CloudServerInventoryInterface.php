<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerData;

interface CloudServerInventoryInterface
{
    /**
     * @return list<CloudServerData>
     */
    public function listServers(
        string $region,
    ): array;
}
