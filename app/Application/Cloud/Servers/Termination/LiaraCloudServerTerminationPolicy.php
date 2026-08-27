<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;

final readonly class LiaraCloudServerTerminationPolicy implements CloudServerTerminationPolicy
{
    private const int MINIMUM_EXPIRED_MINUTES = 5;

    private const int MINIMUM_RESOURCE_AGE_HOURS = 24;

    public function __construct(
        private CloudProviderRegistryInterface $providers,
    ) {}

    public function advance(
        Server $server,
    ): CloudServerTerminationDecision {
        $region = $this->requiredMetadata(
            server: $server,
            attribute: 'cloud_region',
        );
        $providerServerId = $this->requiredMetadata(
            server: $server,
            attribute: 'cloud_server_id',
        );

        try {
            $providerServer = $this->findProviderServer(
                region: $region,
                serverId: $providerServerId,
            );
        } catch (CloudResourceNotFoundException) {
            /*
             * The desired external state is already reached. The normal
             * delete action will finalize the local record and keeps the
             * existing not-found semantics in one place.
             */
            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::ReadyForDelete,
                context: [
                    'provider_resource_missing' => true,
                ],
            );
        }

        if ($providerServer->isRunning()) {
            $this->requestPowerOff(
                region: $region,
                serverId: $providerServerId,
            );

            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::PowerOffRequested,
                context: [
                    'power_state' => $providerServer->powerState->value,
                    'provider_server_id' => $providerServerId,
                ],
            );
        }

        if (! $providerServer->isStopped()) {
            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::WaitingForPowerState,
                context: [
                    'power_state' => $providerServer->powerState->value,
                    'provider_server_id' => $providerServerId,
                ],
            );
        }

        $expiresAt = $server->expires_at;

        if ($expiresAt === null) {
            throw new CloudValidationException(
                sprintf(
                    'Expired Cloud Server [%d] has no expires_at timestamp.',
                    $server->getKey(),
                ),
            );
        }

        if (
            $expiresAt->greaterThan(
                now()->subMinutes(self::MINIMUM_EXPIRED_MINUTES),
            )
        ) {
            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::WaitingForExpirationGrace,
                context: [
                    'expires_at' => $expiresAt->toIso8601String(),
                    'minimum_minutes' => self::MINIMUM_EXPIRED_MINUTES,
                ],
            );
        }

        if ($providerServer->createdAt === null) {
            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::WaitingForProviderCreatedAt,
                context: [
                    'provider_server_id' => $providerServerId,
                ],
            );
        }

        $minimumCreatedAt = now()
            ->subHours(self::MINIMUM_RESOURCE_AGE_HOURS)
            ->toDateTimeImmutable();

        if ($providerServer->createdAt > $minimumCreatedAt) {
            return new CloudServerTerminationDecision(
                state: CloudServerTerminationState::WaitingForProviderMinimumAge,
                context: [
                    'provider_created_at' => $providerServer->createdAt->format(DATE_ATOM),
                    'minimum_hours' => self::MINIMUM_RESOURCE_AGE_HOURS,
                ],
            );
        }

        return new CloudServerTerminationDecision(
            state: CloudServerTerminationState::ReadyForDelete,
            context: [
                'power_state' => $providerServer->powerState->value,
                'provider_created_at' => $providerServer->createdAt->format(DATE_ATOM),
            ],
        );
    }

    private function findProviderServer(
        string $region,
        string $serverId,
    ): CloudServerData {
        $provider = $this->providers->resolve(
            CloudProviderType::Liara,
        );

        $lookup = [
            $provider,
            'findServer',
        ];

        if (! is_callable($lookup)) {
            throw new CloudConfigurationException(
                'Liara provider does not expose the server lookup required by the termination policy.',
            );
        }

        $providerServer = $lookup(
            $region,
            $serverId,
        );

        if (! $providerServer instanceof CloudServerData) {
            throw new CloudUnexpectedResponseException(
                'Liara server lookup returned an invalid server payload.',
            );
        }

        return $providerServer;
    }

    private function requestPowerOff(
        string $region,
        string $serverId,
    ): void {
        /** @var CloudServerLifecycleInterface $lifecycle */
        $lifecycle = $this->providers->resolveCapability(
            provider: CloudProviderType::Liara,
            capability: CloudServerLifecycleInterface::class,
        );

        $lifecycle->powerOff(
            region: $region,
            serverId: $serverId,
        );
    }

    private function requiredMetadata(
        Server $server,
        string $attribute,
    ): string {
        $value = $server->getAttribute(
            $attribute,
        );

        if (! is_string($value) || trim($value) === '') {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return trim($value);
    }
}
