<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Cloud\DTOs\ProvisionCloudServerResult;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerBootstrapCredentialData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
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
        private ?CloudProviderRegistryInterface $providers = null,
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('Provisioning attempts must be greater than zero.');
        }

        if ($this->pollDelaySeconds < 0) {
            throw new InvalidArgumentException('Provisioning poll delay cannot be negative.');
        }

        if (trim($this->providerName) === '') {
            throw new InvalidArgumentException('Cloud provider name cannot be empty.');
        }
    }

    public function handle(
        User $user,
        CreateCloudServerData $data,
        ?CloudProviderType $provider = null,
    ): ProvisionCloudServerResult {
        $result = $this->provisionProviderResource(
            user: $user,
            data: $data,
            provider: $provider,
        );

        $this->verifySshReadiness->handle($result->server);

        return new ProvisionCloudServerResult(
            server: $result->server->refresh(),
            cloudServer: $result->cloudServer,
            pollAttempts: $result->pollAttempts,
        );
    }

    public function provisionProviderResource(
        User $user,
        CreateCloudServerData $data,
        ?CloudProviderType $provider = null,
    ): ProvisionCloudServerResult {
        $provider = $this->resolveProviderType($provider);
        $catalog = $this->catalogFor($provider);
        $provisioner = $this->provisionerFor($provider);

        $this->validateSelection(
            data: $data,
            catalog: $catalog,
            provider: $provider,
        );

        $createdServer = $provisioner->createServer($data);
        $username = $this->serverUsername($createdServer);
        $bootstrapCredential = $this->bootstrapCredentialFor(
            provider: $provider,
            request: $data,
            createdServer: $createdServer,
        );

        $server = $this->persistProvisioningServer(
            user: $user,
            data: $data,
            createdServer: $createdServer,
            username: $username,
            bootstrapCredential: $bootstrapCredential,
            provider: $provider,
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
            provisioner: $provisioner,
        );
    }

    private function pollUntilReady(
        Server $server,
        string $regionId,
        string $providerServerId,
        CloudServerProvisionerInterface $provisioner,
    ): ProvisionCloudServerResult {
        $lastCloudServer = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $cloudServer = $provisioner->findServer(
                    $regionId,
                    $providerServerId,
                );
            } catch (CloudResourceNotFoundException) {
                $this->waitBeforeNextAttempt($attempt);

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

            $server = $this->syncProviderAccessInformation(
                server: $server,
                cloudServer: $cloudServer,
            );

            if (
                $cloudServer->status->isReady()
                && $cloudServer->hasPublicIpv4()
                && $server->hasConnectionHost()
                && $server->hasCredential()
            ) {
                return new ProvisionCloudServerResult(
                    server: $server,
                    cloudServer: $cloudServer,
                    pollAttempts: $attempt,
                );
            }

            $this->waitBeforeNextAttempt($attempt);
        }

        if ($lastCloudServer instanceof CloudServerData && $lastCloudServer->status->isReady()) {
            if (! $lastCloudServer->hasPublicIpv4()) {
                throw new CloudServerNotReadyException(
                    sprintf(
                        'Cloud server [%s] became active but has no usable public IPv4 address.',
                        $providerServerId,
                    ),
                );
            }

            if (! $server->hasCredential()) {
                throw new CloudServerNotReadyException(
                    sprintf(
                        'Cloud server [%s] became active but its bootstrap credential is not available yet.',
                        $providerServerId,
                    ),
                );
            }
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
        ?CloudServerBootstrapCredentialData $bootstrapCredential,
        CloudProviderType $provider,
    ): Server {
        try {
            return $this->createServer->handle(
                user: $user,
                attributes: [
                    'name' => $createdServer->name,
                    'host' => null,
                    'port' => 22,
                    'username' => $username,
                    'authentication_type' => $bootstrapCredential?->authenticationType
                        ?? AuthenticationType::Password,
                    'credential' => $bootstrapCredential?->credential(),
                    'cloud_provider' => $provider->value,
                    'cloud_server_id' => $createdServer->id,
                    'cloud_region' => trim($data->regionId),
                    'provisioned_at' => $createdServer->createdAt ?? now(),
                ],
                status: ServerStatus::Inactive,
            );
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] was created but could not be persisted.',
                    $createdServer->id,
                ),
                previous: $exception,
            );
        }
    }

    private function bootstrapCredentialFor(
        CloudProviderType $provider,
        CreateCloudServerData $request,
        CreatedCloudServerData $createdServer,
    ): ?CloudServerBootstrapCredentialData {
        $generatedPassword = $createdServer->generatedPassword();

        if (is_string($generatedPassword) && $generatedPassword !== '') {
            return new CloudServerBootstrapCredentialData(
                authenticationType: AuthenticationType::Password,
                credential: $generatedPassword,
            );
        }

        if ($this->providers instanceof CloudProviderRegistryInterface) {
            if (! $this->providers->supportsCapability(
                provider: $provider,
                capability: CloudServerBootstrapCredentialSourceInterface::class,
            )) {
                return null;
            }

            /** @var CloudServerBootstrapCredentialSourceInterface $source */
            $source = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerBootstrapCredentialSourceInterface::class,
            );

            return $source->bootstrapCredential(
                request: $request,
                server: $createdServer,
            );
        }

        foreach ([$this->provisioner, $this->catalog] as $candidate) {
            if (! $candidate instanceof CloudServerBootstrapCredentialSourceInterface) {
                continue;
            }

            return $candidate->bootstrapCredential(
                request: $request,
                server: $createdServer,
            );
        }

        return null;
    }

    private function syncProviderAccessInformation(
        Server $server,
        CloudServerData $cloudServer,
    ): Server {
        $attributes = [];
        $connectionIp = $cloudServer->firstPublicIpv4();

        if ($connectionIp !== null && $connectionIp !== $server->host) {
            $attributes['host'] = $connectionIp;
        }

        $providerUsername = trim((string) $cloudServer->username);

        if ($providerUsername !== '' && $providerUsername !== $server->username) {
            $attributes['username'] = $this->normalizedUsername(
                $providerUsername,
            );
        }

        if (
            $server->authentication_type === AuthenticationType::Password
            && ! $server->hasCredential()
            && $cloudServer->hasGeneratedPassword()
        ) {
            $attributes['credential'] = $cloudServer->generatedPassword();
        }

        if ($attributes === []) {
            return $server;
        }

        try {
            $server->forceFill($attributes);
            $server->saveOrFail();

            return $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Provider access information for cloud server [%s] could not be persisted.',
                    $cloudServer->id,
                ),
                previous: $exception,
            );
        }
    }

    private function serverUsername(
        CreatedCloudServerData $createdServer,
    ): string {
        return $this->normalizedUsername($createdServer->username);
    }

    private function normalizedUsername(?string $username): string
    {
        $username = trim((string) $username);

        if (
            $username === ''
            || preg_match('/\A[a-z_][a-z0-9_-]*[$]?\z/i', $username) !== 1
        ) {
            throw new CloudServerNotReadyException('Cloud server SSH username is invalid.');
        }

        return $username;
    }

    private function waitBeforeNextAttempt(int $attempt): void
    {
        if ($attempt >= $this->maxAttempts || $this->pollDelaySeconds === 0) {
            return;
        }

        sleep($this->pollDelaySeconds);
    }

    private function validateSelection(
        CreateCloudServerData $data,
        CloudProviderInterface $catalog,
        CloudProviderType $provider,
    ): void {
        if ($data->usesSshKey()) {
            throw new CloudValidationException(
                'Sprint 11 provisioning supports generated passwords only.',
            );
        }

        $regionId = trim($data->regionId);

        $region = $this->findResource(
            resources: $catalog->listRegions(),
            id: $regionId,
        );

        if (! $region instanceof CloudRegionData) {
            throw new CloudValidationException(
                sprintf('Cloud region [%s] is unavailable.', $regionId),
            );
        }

        if (! $region->canCreateServers) {
            throw new CloudValidationException(
                sprintf('Cloud region [%s] does not allow server creation.', $regionId),
            );
        }

        $size = $this->findResource(
            resources: $catalog->listSizes($regionId),
            id: trim($data->sizeId),
        );

        if (! $size instanceof CloudSizeData) {
            throw new CloudValidationException(
                sprintf('Cloud size [%s] is unavailable.', $data->sizeId),
            );
        }

        $image = $this->findResource(
            resources: $catalog->listImages($regionId),
            id: trim($data->imageId),
        );

        if (! $image instanceof CloudImageData) {
            throw new CloudValidationException(
                sprintf('Cloud image [%s] is unavailable.', $data->imageId),
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

        if ($data->hasAnyProvisioningInfrastructure()) {
            if (! $data->hasProvisioningInfrastructure()) {
                throw new CloudValidationException(
                    'Cloud network and security groups must be provided together.',
                );
            }

            $infrastructure = $this->infrastructureCatalogFor(
                provider: $provider,
                fallback: $catalog,
            );

            $network = $this->findResource(
                resources: $infrastructure->listNetworks($regionId),
                id: trim((string) $data->networkId),
            );

            if (! $network instanceof CloudNetworkData) {
                throw new CloudValidationException(
                    sprintf('Cloud network [%s] is unavailable.', $data->networkId),
                );
            }

            if (! $network->isActive) {
                throw new CloudValidationException(
                    sprintf('Cloud network [%s] is not active.', $network->id),
                );
            }

            if ($network->ipVersion !== CloudIpVersion::IPv4) {
                throw new CloudValidationException(
                    'Sprint 11 provisioning requires an IPv4 network.',
                );
            }

            $this->validateSecurityGroups(
                data: $data,
                regionId: $regionId,
                catalog: $infrastructure,
            );
        }

        $minimumDiskGiB = max(
            $size->diskGiB,
            $image->minDiskGiB ?? 0,
        );

        if ($data->diskGiB < $minimumDiskGiB) {
            throw new CloudValidationException(
                sprintf('Cloud disk must be at least [%d] GiB.', $minimumDiskGiB),
            );
        }

        if (
            $image->minMemoryMiB !== null
            && $size->memoryMiB < $image->minMemoryMiB
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
        CloudProvisioningInfrastructureCatalogInterface $catalog,
    ): void {
        if ($data->securityGroupIds === []) {
            throw new CloudValidationException(
                'At least one cloud security group is required.',
            );
        }

        $availableGroups = $catalog->listSecurityGroups($regionId);

        foreach (array_unique($data->securityGroupIds) as $securityGroupId) {
            if (! is_string($securityGroupId)) {
                throw new CloudValidationException(
                    'Cloud security group identifier must be a string.',
                );
            }

            $group = $this->findResource(
                resources: $availableGroups,
                id: trim($securityGroupId),
            );

            if (! $group instanceof CloudSecurityGroupData) {
                throw new CloudValidationException(
                    sprintf(
                        'Cloud security group [%s] is unavailable.',
                        $securityGroupId,
                    ),
                );
            }
        }
    }

    private function infrastructureCatalogFor(
        CloudProviderType $provider,
        CloudProviderInterface $fallback,
    ): CloudProvisioningInfrastructureCatalogInterface {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            /** @var CloudProvisioningInfrastructureCatalogInterface $catalog */
            $catalog = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudProvisioningInfrastructureCatalogInterface::class,
            );

            return $catalog;
        }

        if ($fallback instanceof CloudProvisioningInfrastructureCatalogInterface) {
            return $fallback;
        }

        throw new CloudConfigurationException(
            sprintf(
                'Cloud provider [%s] cannot validate provisioning infrastructure without the infrastructure catalog capability.',
                $provider->value,
            ),
        );
    }

    private function resolveProviderType(
        ?CloudProviderType $provider,
    ): CloudProviderType {
        if ($provider instanceof CloudProviderType) {
            return $provider;
        }

        $normalized = strtolower(trim($this->providerName));
        $resolved = CloudProviderType::tryFrom($normalized);

        if (! $resolved instanceof CloudProviderType) {
            throw new CloudConfigurationException(
                sprintf('The cloud provider [%s] is not supported.', $normalized),
            );
        }

        return $resolved;
    }

    private function catalogFor(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            return $this->providers->resolve($provider);
        }

        $this->assertLegacyProviderMatches($provider);

        return $this->catalog;
    }

    private function provisionerFor(
        CloudProviderType $provider,
    ): CloudServerProvisionerInterface {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            /** @var CloudServerProvisionerInterface $provisioner */
            $provisioner = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerProvisionerInterface::class,
            );

            return $provisioner;
        }

        $this->assertLegacyProviderMatches($provider);

        return $this->provisioner;
    }

    private function assertLegacyProviderMatches(
        CloudProviderType $provider,
    ): void {
        if (strtolower(trim($this->providerName)) !== $provider->value) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] cannot be resolved without the provider registry.',
                    $provider->value,
                ),
            );
        }
    }

    /** @param list<object> $resources */
    private function findResource(array $resources, string $id): ?object
    {
        foreach ($resources as $resource) {
            if (property_exists($resource, 'id') && $resource->id === $id) {
                return $resource;
            }
        }

        return null;
    }
}
