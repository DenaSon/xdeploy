<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;

interface CloudServerProvisionerInterface
{
    public function createServer(
        CreateCloudServerData $data,
    ): CreatedCloudServerData;

    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData;
}
