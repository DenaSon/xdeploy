<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudCatalogCapabilities;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvisioner;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use Tests\TestCase;

final class CloudServiceProviderRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cloud.default', 'arvan');

        config()->set('cloud.providers.arvan.base_url', 'https://api.example.test/ecc/v1');
        config()->set('cloud.providers.arvan.api_key', 'test-api-key');
        config()->set('cloud.providers.arvan.timeouts.connect', 5);
        config()->set('cloud.providers.arvan.timeouts.request', 15);
        config()->set('cloud.providers.arvan.defaults.create_type', 'cinder');

        config()->set('cloud.providers.liara.base_url', 'https://iaas-api.example.test');
        config()->set('cloud.providers.liara.api_token', 'test-liara-token');
        config()->set('cloud.providers.liara.timeouts.connect', 5);
        config()->set('cloud.providers.liara.timeouts.request', 15);

        $this->resetCloudResolutionState();
    }

    public function test_liara_only_configuration_boots_without_arvan_credentials(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.arvan.api_key', null);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);
        $liara = $this->app->make(LiaraCloudProvider::class);

        $this->assertSame(
            [CloudProviderType::Liara],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            $liara,
            $registry->resolve(CloudProviderType::Liara),
        );
        $this->assertSame(
            $liara,
            $this->app->make(CloudProviderInterface::class),
        );
    }

    public function test_arvan_only_configuration_boots_without_liara_credentials(): void
    {
        config()->set('cloud.providers.liara.api_token', null);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);
        $arvan = $this->app->make(ArvanCloudProvider::class);

        $this->assertSame(
            [CloudProviderType::Arvan],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            $arvan,
            $registry->resolve(CloudProviderType::Arvan),
        );
        $this->assertSame(
            $arvan,
            $this->app->make(CloudProviderInterface::class),
        );
    }

    public function test_both_configured_providers_are_registered(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            $registry->registeredProviders(),
        );
    }

    public function test_missing_credentials_for_unused_provider_do_not_break_boot(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.arvan.api_key', null);
        $this->resetCloudResolutionState();

        $this->assertInstanceOf(
            LiaraCloudProvider::class,
            $this->app->make(CloudProviderInterface::class),
        );
    }

    public function test_default_provider_must_be_registered(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.liara.api_token', null);
        $this->resetCloudResolutionState();

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'The cloud provider [liara] is not registered.',
        );

        $this->app->make(CloudProviderRegistryInterface::class);
    }

    private function resetCloudResolutionState(): void
    {
        foreach ([
            CloudProviderRegistry::class,
            CloudProviderRegistryInterface::class,
            CloudProviderInterface::class,
            ArvanCloudClient::class,
            ArvanCloudProvider::class,
            ArvanCloudCatalogCapabilities::class,
            ArvanCloudProvisioner::class,
            LiaraCloudClient::class,
            LiaraCloudProvider::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }
}
