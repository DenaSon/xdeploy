<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;

final readonly class CloudServerTerminationPolicyResolver
{
    public function __construct(
        private ?CloudProviderRegistryInterface $providers = null,
    ) {}

    public function advance(
        Server $server,
    ): CloudServerTerminationDecision {
        $provider = $server->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new CloudValidationException(
                'Cloud server provider metadata is incomplete.',
            );
        }

        return $this->policyFor(
            $provider,
        )->advance(
            $server,
        );
    }

    private function policyFor(
        CloudProviderType $provider,
    ): CloudServerTerminationPolicy {
        return match ($provider) {
            CloudProviderType::Arvan => new ImmediateCloudServerTerminationPolicy,
            CloudProviderType::Liara => new LiaraCloudServerTerminationPolicy(
                providers: $this->requiredProviderRegistry(),
            ),
        };
    }

    private function requiredProviderRegistry(): CloudProviderRegistryInterface
    {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            return $this->providers;
        }

        throw new CloudConfigurationException(
            'Cloud provider registry is required for provider-specific termination policies.',
        );
    }
}
