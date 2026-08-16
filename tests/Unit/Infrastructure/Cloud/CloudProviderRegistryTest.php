<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudQuotaReaderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use Mockery;
use Tests\TestCase;

final class CloudProviderRegistryTest extends TestCase
{
    public function test_capability_override_can_differ_from_provider_instance(): void
    {
        $provider = Mockery::mock(CloudProviderInterface::class);
        $provisioner = Mockery::mock(CloudServerProvisionerInterface::class);

        $registry = new CloudProviderRegistry(
            providers: [
                CloudProviderType::Arvan->value => $provider,
            ],
            capabilities: [
                CloudProviderType::Arvan->value => [
                    CloudServerProvisionerInterface::class => $provisioner,
                ],
            ],
        );

        $this->assertSame($provider, $registry->resolve(CloudProviderType::Arvan));
        $this->assertSame(
            $provisioner,
            $registry->resolveCapability(
                provider: CloudProviderType::Arvan,
                capability: CloudServerProvisionerInterface::class,
            ),
        );
        $this->assertTrue(
            $registry->supportsCapability(
                provider: CloudProviderType::Arvan,
                capability: CloudServerProvisionerInterface::class,
            ),
        );
        $this->assertFalse(
            $registry->supportsCapability(
                provider: CloudProviderType::Arvan,
                capability: CloudQuotaReaderInterface::class,
            ),
        );
    }

    public function test_omitted_purchase_list_defaults_to_registered_providers(): void
    {
        $arvan = Mockery::mock(CloudProviderInterface::class);
        $liara = Mockery::mock(CloudProviderInterface::class);

        $registry = new CloudProviderRegistry(
            providers: [
                CloudProviderType::Arvan->value => $arvan,
                CloudProviderType::Liara->value => $liara,
            ],
        );

        $this->assertSame(
            [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            $registry->purchasableProviders(),
        );
    }

    public function test_purchase_disabled_provider_remains_registered_for_operations(): void
    {
        $arvan = Mockery::mock(CloudProviderInterface::class);
        $liara = Mockery::mock(CloudProviderInterface::class);

        $registry = new CloudProviderRegistry(
            providers: [
                CloudProviderType::Arvan->value => $arvan,
                CloudProviderType::Liara->value => $liara,
            ],
            purchasableProviders: [
                CloudProviderType::Liara->value,
            ],
        );

        $this->assertSame(
            [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            [CloudProviderType::Liara],
            $registry->purchasableProviders(),
        );
        $this->assertSame(
            $arvan,
            $registry->resolve(CloudProviderType::Arvan),
        );
    }

    public function test_purchasable_provider_must_also_be_registered(): void
    {
        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'The purchasable cloud provider [liara] is not registered.',
        );

        new CloudProviderRegistry(
            providers: [
                CloudProviderType::Arvan->value => Mockery::mock(
                    CloudProviderInterface::class,
                ),
            ],
            purchasableProviders: [
                CloudProviderType::Liara->value,
            ],
        );
    }
}
