<?php

declare(strict_types=1);

namespace App\Application\Cloud\Networking;

use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Enums\CloudIpVersion;

final readonly class AddCloudServerPublicIpAction
{
    public function __construct(
        private CloudServerNetworkingInterface $networking,
    ) {}

    /**
     * @param list<string> $securityGroupIds
     */
    public function handle(
        string $region,
        string $serverId,
        CloudIpVersion $version = CloudIpVersion::IPv4,
        array $securityGroupIds = [],
    ): void {
        $this->networking->addPublicIp(
            region: $region,
            serverId: $serverId,
            version: $version,
            securityGroupIds: $securityGroupIds,
        );
    }
}
