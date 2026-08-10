<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Cloud\DTOs\ProvisionCloudServerResult;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudProvisioningTimeoutException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudServerNotReadyException;
use App\Domain\Cloud\Exceptions\CloudServerProvisioningException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use InvalidArgumentException;
use Throwable;

final readonly class ProvisionCloudServerAction
{
    public function __construct(
        private CloudProviderInterface $catalog,
        private CloudServerProvisionerInterface $provisioner,
        private CreateServerAction $createServer,
        private VerifyCloudServerSshReadinessAction $verifySshReadiness,
        private string $providerName = 'arvan',
        private int $maxAttempts = 20,
        private int $pollDelaySeconds = 3,
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException(
                'Provisioning attempts must be greater than zero.',
            );
        }

        if ($this->pollDelaySeconds < 0) {
            throw new InvalidArgumentException(
                'Provisioning poll delay cannot be negative.',
            );
        }

        if (trim($this->providerName) === '') {
            throw new InvalidArgumentException(
                'Cloud provider name cannot be empty.',
            );
        }
    }

    /**
     * Full server workflow retained for existing callers that require an
     * xDeploy-ready SSH connection before the Server becomes Active.
     */
    public function handle(
        User $user,
        CreateCloudServerData $data,
    ): ProvisionCloudServerResult {
        $result = $this->provisionProviderResource(
            user: $user,
            data: $data,
        );

        $this->verifySshReadiness->handle(
            $result->server,
        );

        return new ProvisionCloudServerResult(
            server: $result->server->refresh(),
            cloudServer: $result->cloudServer,
            pollAttempts: $result->pollAttempts,
        );
    }

    /**
     * Provider-delivery boundary.
     *
     * This method stops once the provider resource is ready and its public
     * connection information is persisted. SSH reachability is intentionally
     * NOT part of this boundary because an otherwise valid public IP may be
     * unreachable from the xDeploy host/network.
     */
    public function provisionProviderResource(
        User $user,
        CreateCloudServerData $data,
    ): ProvisionCloudServerResult {
        $this->validateSelection($data);

        $createdServer = $this->provisioner
            ->createServer($data);

        $password = $this->generatedPassword(
            $createdServer,
        );

        $username = $this->serverUsername(
            $createdServer,
        );

        $server = $this->persistProvisioningServer(
            user: $user,
            data: $data,
            createdServer: $createdServer,
            username: $username,
            password: $password,
        );

        if ($createdServer->status->isFailed()) {
            throw new CloudServerProvisioningException(
                sprintf(
                    'Cloud server [%s] entered a failed state during creation.',
                    $createdServer->id,
                ),
            );
        }

        return $this->pollUntilReady(
            server: $server,
            regionId: $data->regionId,
            providerServerId: $createdServer->id,
        );
    }

    private function pollUntilReady(
        Server $server,
        string $regionId,
        string $providerServerId,
    ): ProvisionCloudServerResult {
        $lastCloudServer = null;

        for (
            $attempt = 1;
            $attempt <= $this->maxAttempts;
            $attempt++
        ) {
            try {
                $cloudServer = $this->provisioner
                    ->findServer(
                        $regionId,
                        $providerServerId,
                    );
            } catch (CloudResourceNotFoundException) {
                /*
                 * Immediately after creation, the List endpoint may not
                 * expose the server yet. Treat this as eventual consistency.
                 */
                $this->waitBeforeNextAttempt(
                    $attempt,
                );

                continue;
            }

            $lastCloudServer = $cloudServer;

            if ($cloudServer->status->isFailed()) {
                throw new CloudServerProvisioningException(
                    sprintf(
                        'Cloud server [%s] entered a failed state.',
                        $providerServerId,
                    ),
                );
            }

            if (
                $cloudServer->status->isReady()
                && $cloudServer->hasPublicIpv4()
            ) {
                $server = $this->saveConnectionInformation(
                    server: $server,
                    cloudServer: $cloudServer,
                );

                return new ProvisionCloudServerResult(
                    server: $server,
                    cloudServer: $cloudServer,
                    pollAttempts: $attempt,
                );
            }

            $this->waitBeforeNextAttempt(
                $attempt,
            );
        }

        if (
            $lastCloudServer instanceof CloudServerData
            && $lastCloudServer->status->isReady()
        ) {
            throw new CloudServerNotReadyException(
                sprintf(
                    'Cloud server [%s] became active but has no usable public IPv4 address.',
                    $providerServerId,
                ),
            );
        }

        throw new CloudProvisioningTimeoutException(
            sprintf(
                'Cloud server [%s] did not become ready after [%d] attempts.',
                $providerServerId,
                $this->maxAttempts,
            ),
        );
    }

    private function persistProvisioningServer(
        User $user,
        CreateCloudServerData $data,
        CreatedCloudServerData $createdServer,
        string $username,
        string $password,
    ): Server {
        try {
            return $this->createServer->handle(
                user: $user,

                attributes: [
                    'name' => $createdServer->name,

                    'host' => null,

                    'port' => 22,

                    'username' => $username,

                    'authentication_type' => AuthenticationType::Password,

                    'credential' => $password,

                    'cloud_provider' => trim($this->providerName),

                    'cloud_server_id' => $createdServer->id,

                    'cloud_region' => trim($data->regionId),

                    'provisioned_at' => $createdServer->createdAt ?? now(),
                ],

                status: ServerStatus::Inactive,
            );
        } catch (Throwable $exception) {
            /*
             * The provider resource already exists at this point.
             * Delete API is outside this Sprint, so keep its ID in
             * the exception for manual recovery.
             */
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] was created but could not be persisted.',
                    $createdServer->id,
                ),
                previous: $exception,
            );
        }
    }

    private function saveConnectionInformation(
        Server $server,
        CloudServerData $cloudServer,
    ): Server {
        $connectionIp = $cloudServer
            ->firstPublicIpv4();

        if ($connectionIp === null) {
            throw new CloudServerNotReadyException(
                sprintf(
                    'Cloud server [%s] has no usable public IPv4 address.',
                    $cloudServer->id,
                ),
            );
        }

        try {
            $server->forceFill([
                'host' => $connectionIp,

                'username' => $this->normalizedUsername(
                    $cloudServer->username
                    ?? $server->username,
                ),
            ]);

            $server->saveOrFail();

            return $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Connection information for cloud server [%s] could not be persisted.',
                    $cloudServer->id,
                ),
                previous: $exception,
            );
        }
    }

    private function generatedPassword(
        CreatedCloudServerData $createdServer,
    ): string {
        $password = $createdServer
            ->generatedPassword();

        if (
            ! is_string($password)
            || $password === ''
        ) {
            throw new CloudServerProvisioningException(
                sprintf(
                    'Cloud server [%s] has no generated password.',
                    $createdServer->id,
                ),
            );
        }

        return $password;
    }

    private function serverUsername(
        CreatedCloudServerData $createdServer,
    ): string {
        return $this->normalizedUsername(
            $createdServer->username,
        );
    }

    private function normalizedUsername(
        ?string $username,
    ): string {
        $username = trim(
            (string) $username,
        );

        if (
            $username === ''
            || preg_match(
                '/\A[a-z_][a-z0-9_-]*[$]?\z/i',
                $username,
            ) !== 1
        ) {
            throw new CloudServerNotReadyException(
                'Cloud server SSH username is invalid.',
            );
        }

        return $username;
    }

    private function waitBeforeNextAttempt(
        int $attempt,
    ): void {
        if (
            $attempt >= $this->maxAttempts
            || $this->pollDelaySeconds === 0
        ) {
            return;
        }

        sleep(
            $this->pollDelaySeconds,
        );
    }

    private function validateSelection(
        CreateCloudServerData $data,
    ): void {
        if ($data->usesSshKey()) {
            throw new CloudValidationException(
                'Sprint 11 provisioning supports generated passwords only.',
            );
        }

        $regionId = trim(
            $data->regionId,
        );

        $region = $this->findResource(
            resources: $this->catalog->listRegions(),
            id: $regionId,
        );

        if (! $region instanceof CloudRegionData) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud region [%s] is unavailable.',
                    $regionId,
                ),
            );
        }

        if (! $region->canCreateServers) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud region [%s] does not allow server creation.',
                    $regionId,
                ),
            );
        }

        $size = $this->findResource(
            resources: $this->catalog->listSizes(
                $regionId,
            ),
            id: trim($data->sizeId),
        );

        if (! $size instanceof CloudSizeData) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud size [%s] is unavailable.',
                    $data->sizeId,
                ),
            );
        }

        $image = $this->findResource(
            resources: $this->catalog->listImages(
                $regionId,
            ),
            id: trim($data->imageId),
        );

        if (! $image instanceof CloudImageData) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud image [%s] is unavailable.',
                    $data->imageId,
                ),
            );
        }

        if (! $image->supportsPassword) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud image [%s] does not support password authentication.',
                    $image->id,
                ),
            );
        }

        $network = $this->findResource(
            resources: $this->catalog->listNetworks(
                $regionId,
            ),
            id: trim($data->networkId),
        );

        if (! $network instanceof CloudNetworkData) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud network [%s] is unavailable.',
                    $data->networkId,
                ),
            );
        }

        if (! $network->isActive) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud network [%s] is not active.',
                    $network->id,
                ),
            );
        }

        if (
            $network->ipVersion
            !== CloudIpVersion::IPv4
        ) {
            throw new CloudValidationException(
                'Sprint 11 provisioning requires an IPv4 network.',
            );
        }

        $this->validateSecurityGroups(
            data: $data,
            regionId: $regionId,
        );

        $minimumDiskGiB = max(
            $size->diskGiB,
            $image->minDiskGiB ?? 0,
        );

        if ($data->diskGiB < $minimumDiskGiB) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud disk must be at least [%d] GiB.',
                    $minimumDiskGiB,
                ),
            );
        }

        if (
            $image->minMemoryMiB !== null
            && $size->memoryMiB
            < $image->minMemoryMiB
        ) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud size [%s] does not satisfy the image memory requirement.',
                    $size->id,
                ),
            );
        }
    }

    private function validateSecurityGroups(
        CreateCloudServerData $data,
        string $regionId,
    ): void {
        if ($data->securityGroupIds === []) {
            throw new CloudValidationException(
                'At least one cloud security group is required.',
            );
        }

        $availableGroups =
            $this->catalog
                ->listSecurityGroups(
                    $regionId,
                );

        foreach (
            array_unique(
                $data->securityGroupIds,
            ) as $securityGroupId
        ) {
            if (! is_string($securityGroupId)) {
                throw new CloudValidationException(
                    'Cloud security group identifier must be a string.',
                );
            }

            $group = $this->findResource(
                resources: $availableGroups,
                id: trim($securityGroupId),
            );

            if (
                ! $group instanceof CloudSecurityGroupData
            ) {
                throw new CloudValidationException(
                    sprintf(
                        'Cloud security group [%s] is unavailable.',
                        $securityGroupId,
                    ),
                );
            }
        }
    }

    /**
     * @param  list<object>  $resources
     */
    private function findResource(
        array $resources,
        string $id,
    ): ?object {
        foreach ($resources as $resource) {
            if (
                property_exists(
                    $resource,
                    'id',
                )
                && $resource->id === $id
            ) {
                return $resource;
            }
        }

        return null;
    }
}
