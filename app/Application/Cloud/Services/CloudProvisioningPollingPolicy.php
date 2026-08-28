<?php

declare(strict_types=1);

namespace App\Application\Cloud\Services;

use App\Application\Cloud\DTOs\CloudProvisioningPollingSettings;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;

final readonly class CloudProvisioningPollingPolicy
{
    public function resolve(
        CloudProviderType $provider,
        ?int $maxAttemptsOverride = null,
        ?int $pollDelaySecondsOverride = null,
    ): CloudProvisioningPollingSettings {
        $maxAttempts = $maxAttemptsOverride
            ?? $this->configuredValue(
                provider: $provider,
                setting: 'max_attempts',
                globalFallback: 'cloud.provisioning.max_attempts',
            );

        $pollDelaySeconds = $pollDelaySecondsOverride
            ?? $this->configuredValue(
                provider: $provider,
                setting: 'poll_delay_seconds',
                globalFallback: 'cloud.provisioning.poll_delay_seconds',
            );

        if ($maxAttempts < 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] provisioning max attempts must be greater than zero.',
                    $provider->value,
                ),
            );
        }

        if ($pollDelaySeconds < 0) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] provisioning poll delay cannot be negative.',
                    $provider->value,
                ),
            );
        }

        return new CloudProvisioningPollingSettings(
            maxAttempts: $maxAttempts,
            pollDelaySeconds: $pollDelaySeconds,
        );
    }

    private function configuredValue(
        CloudProviderType $provider,
        string $setting,
        string $globalFallback,
    ): int {
        $value = config(
            sprintf(
                'cloud.providers.%s.provisioning.%s',
                $provider->value,
                $setting,
            ),
        );

        if ($value === null) {
            $value = config($globalFallback);
        }

        if (! is_int($value) && ! is_numeric($value)) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] provisioning setting [%s] must be an integer.',
                    $provider->value,
                    $setting,
                ),
            );
        }

        return (int) $value;
    }
}
