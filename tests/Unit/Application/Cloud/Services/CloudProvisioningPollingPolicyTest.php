<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Services;

use App\Application\Cloud\Services\CloudProvisioningPollingPolicy;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use Tests\TestCase;

final class CloudProvisioningPollingPolicyTest extends TestCase
{
    public function test_parspack_uses_its_provider_specific_polling_window(): void
    {
        config()->set('cloud.provisioning.max_attempts', 20);
        config()->set('cloud.provisioning.poll_delay_seconds', 3);
        config()->set('cloud.providers.parspack.provisioning.max_attempts', 60);
        config()->set('cloud.providers.parspack.provisioning.poll_delay_seconds', 3);

        $settings = (new CloudProvisioningPollingPolicy())->resolve(
            CloudProviderType::ParsPack,
        );

        $this->assertSame(60, $settings->maxAttempts);
        $this->assertSame(3, $settings->pollDelaySeconds);
    }

    public function test_existing_providers_keep_global_polling_defaults(): void
    {
        config()->set('cloud.provisioning.max_attempts', 20);
        config()->set('cloud.provisioning.poll_delay_seconds', 3);
        config()->set('cloud.providers.arvan.provisioning', null);
        config()->set('cloud.providers.liara.provisioning', null);

        $policy = new CloudProvisioningPollingPolicy();

        foreach ([CloudProviderType::Arvan, CloudProviderType::Liara] as $provider) {
            $settings = $policy->resolve($provider);

            $this->assertSame(20, $settings->maxAttempts);
            $this->assertSame(3, $settings->pollDelaySeconds);
        }
    }

    public function test_explicit_action_overrides_take_precedence_over_provider_configuration(): void
    {
        config()->set('cloud.providers.parspack.provisioning.max_attempts', 60);
        config()->set('cloud.providers.parspack.provisioning.poll_delay_seconds', 3);

        $settings = (new CloudProvisioningPollingPolicy())->resolve(
            provider: CloudProviderType::ParsPack,
            maxAttemptsOverride: 2,
            pollDelaySecondsOverride: 0,
        );

        $this->assertSame(2, $settings->maxAttempts);
        $this->assertSame(0, $settings->pollDelaySeconds);
    }

    public function test_invalid_provider_polling_configuration_is_rejected(): void
    {
        config()->set('cloud.providers.parspack.provisioning.max_attempts', 0);

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'Cloud provider [parspack] provisioning max attempts must be greater than zero.',
        );

        (new CloudProvisioningPollingPolicy())->resolve(
            CloudProviderType::ParsPack,
        );
    }
}
