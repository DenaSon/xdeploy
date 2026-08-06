<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\Enums\CloudIpVersion;

interface CloudServerNetworkingInterface
{
    /**
     * @return list<CloudPortData>
     */
    public function listServerPorts(
        string $region,
        string $serverId,
    ): array;

    /**
     * @param list<string> $securityGroupIds
     */
    public function addPublicIp(
        string $region,
        string $serverId,
        CloudIpVersion $version = CloudIpVersion::IPv4,
        array $securityGroupIds = [],
    ): void;

    public function deletePort(
        string $region,
        string $portId,
    ): void;
}
