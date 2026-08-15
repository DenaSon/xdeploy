<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;

final readonly class ArvanCloudProvisioner implements CloudServerProvisionerInterface
{
    public function __construct(
        private ArvanCloudProvider $provider,
        private ResolveCloudProvisioningInfrastructureAction $resolveInfrastructure,
    ) {}

    public function createServer(
        CreateCloudServerData $data,
    ): CreatedCloudServerData {
        if ($data->hasProvisioningInfrastructure()) {
            return $this->provider->createServer($data);
        }

        $infrastructure = $this->resolveInfrastructure->execute(
            $data->regionId,
        );

        return $this->provider->createServer(
            new CreateCloudServerData(
                name: $data->name,
                regionId: $data->regionId,
                sizeId: $data->sizeId,
                imageId: $data->imageId,
                diskGiB: $data->diskGiB,
                networkId: $infrastructure->networkId,
                securityGroupIds: $infrastructure->securityGroupIds,
                sshKeyName: $data->sshKeyName,
                initializationScript: $data->initializationScript,
                highAvailability: $data->highAvailability,
            ),
        );
    }

    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData {
        return $this->provider->findServer(
            region: $region,
            serverId: $serverId,
        );
    }
}
