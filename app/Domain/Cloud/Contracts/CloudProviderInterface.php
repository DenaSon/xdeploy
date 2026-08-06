<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CloudSshKeyData;

interface CloudProviderInterface
{
    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array;

    /**
     * @return list<CloudSizeData>
     */
    public function listSizes(
        string $region,
    ): array;

    /**
     * @return list<CloudImageData>
     */
    public function listImages(
        string $region,
    ): array;

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

    public function getQuota(
        string $region,
    ): CloudQuotaData;

    /**
     * @return list<CloudSshKeyData>
     */
    public function listSshKeys(
        string $region,
    ): array;
}
