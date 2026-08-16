<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\Contracts\CloudQuotaReaderInterface;
use App\Domain\Cloud\Contracts\CloudSshKeyCatalogInterface;
use App\Domain\Cloud\DTOs\CloudQuotaData;

final readonly class ArvanCloudCatalogCapabilities implements CloudProvisioningInfrastructureCatalogInterface, CloudQuotaReaderInterface, CloudSshKeyCatalogInterface
{
    public function __construct(
        private ArvanCloudProvider $provider,
    ) {}

    public function listNetworks(
        string $region,
    ): array {
        return $this->provider->listNetworks(
            $region,
        );
    }

    public function listSecurityGroups(
        string $region,
    ): array {
        return $this->provider->listSecurityGroups(
            $region,
        );
    }

    public function getQuota(
        string $region,
    ): CloudQuotaData {
        return $this->provider->getQuota(
            $region,
        );
    }

    public function listSshKeys(
        string $region,
    ): array {
        return $this->provider->listSshKeys(
            $region,
        );
    }
}
