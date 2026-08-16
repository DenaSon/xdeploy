<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudQuotaReaderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
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
}
