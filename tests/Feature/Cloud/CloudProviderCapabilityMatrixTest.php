<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloudProviderCapabilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cloud.providers.liara.api_token', 'test-liara-token');

        foreach ([
            CloudProviderRegistry::class,
            CloudProviderRegistryInterface::class,
            LiaraCloudClient::class,
            LiaraCloudProvider::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    public function test_registered_providers_expose_expected_capabilities(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertTrue($registry->supportsCapability(CloudProviderType::Arvan, CloudServerLifecycleInterface::class));
        $this->assertTrue($registry->supportsCapability(CloudProviderType::Arvan, CloudServerProvisionerInterface::class));
        $this->assertTrue($registry->supportsCapability(CloudProviderType::Liara, CloudServerLifecycleInterface::class));
        $this->assertTrue($registry->supportsCapability(CloudProviderType::Liara, CloudServerProvisionerInterface::class));
    }

    public function test_unsupported_capability_is_not_reported_as_available(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertFalse(
            $registry->supportsCapability(
                CloudProviderType::Liara,
                CloudServerConsoleInterface::class,
            ),
        );
    }
}
