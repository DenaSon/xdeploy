<?php

declare(strict_types=1);

namespace App\Application\Cloud\Networking;

use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\DTOs\CloudPortData;

final readonly class ListCloudServerPortsAction
{
    public function __construct(
        private CloudServerNetworkingInterface $networking,
    ) {}

    /**
     * @return list<CloudPortData>
     */
    public function handle(
        string $region,
        string $serverId,
    ): array {
        return $this->networking->listServerPorts(
            region: $region,
            serverId: $serverId,
        );
    }
}
