<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerData;

interface CloudServerLookupInterface
{
    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData;
}
