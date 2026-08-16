<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;

interface CloudProvisioningInfrastructureCatalogInterface
{
    /**
     * @return list<CloudNetworkData>
     */
    public function listNetworks(
        string $region,
    ): array;

    /**
     * @return list<CloudSecurityGroupData>
     */
    public function listSecurityGroups(
        string $region,
    ): array;
}
