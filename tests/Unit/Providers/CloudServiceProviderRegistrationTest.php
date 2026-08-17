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

        config()->set('cloud.providers.arvan.enabled', true);
        config()->set('cloud.providers.arvan.purchase_enabled', true);
        config()->set('cloud.providers.arvan.base_url', 'https://api.example.test/ecc/v1');
        config()->set('cloud.providers.arvan.api_key', 'test-api-key');
        config()->set('cloud.providers.arvan.timeouts.connect', 5);
        config()->set('cloud.providers.arvan.timeouts.request', 15);
        config()->set('cloud.providers.arvan.defaults.create_type', 'cinder');

        config()->set('cloud.providers.liara.enabled', true);
        config()->set('cloud.providers.liara.purchase_enabled', true);
        config()->set('cloud.providers.liara.base_url', 'https://iaas-api.example.test');
        config()->set('cloud.providers.liara.api_token', 'test-liara-token');
        config()->set('cloud.providers.liara.timeouts.connect', 5);
        config()->set('cloud.providers.liara.timeouts.request', 15);

        $this->resetCloudResolutionState();
    }

    public function test_liara_only_configuration_boots_when_arvan_is_disabled(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.arvan.enabled', false);
        config()->set('cloud.providers.arvan.purchase_enabled', false);
        config()->set('cloud.providers.arvan.api_key', null);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);
        $liara = $this->app->make(LiaraCloudProvider::class);

        $this->assertSame(
            [CloudProviderType::Liara],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            [CloudProviderType::Liara],
            $registry->purchasableProviders(),
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

    public function test_arvan_only_configuration_boots_when_liara_is_disabled(): void
    {
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', false);
        config()->set('cloud.providers.liara.api_token', null);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);
        $arvan = $this->app->make(ArvanCloudProvider::class);

        $this->assertSame(
            [CloudProviderType::Arvan],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            [CloudProviderType::Arvan],
            $registry->purchasableProviders(),
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

    public function test_both_enabled_providers_are_registered_and_purchasable(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            $registry->registeredProviders(),
        );
        $this->assertSame(
            [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            $registry->purchasableProviders(),
        );
    }

    public function test_purchase_disabled_provider_remains_registered_for_existing_resources(): void
    {
        config()->set('cloud.providers.arvan.purchase_enabled', false);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);

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
        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $registry->resolve(CloudProviderType::Arvan),
        );
    }

    public function test_disabled_provider_is_not_registered_even_when_credentials_exist(): void
    {
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', false);
        $this->resetCloudResolutionState();

        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            [CloudProviderType::Arvan],
            $registry->registeredProviders(),
        );

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'The cloud provider [liara] is not registered.',
        );

        $registry->resolve(CloudProviderType::Liara);
    }

    public function test_missing_credentials_for_disabled_provider_do_not_break_boot(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.arvan.enabled', false);
        config()->set('cloud.providers.arvan.purchase_enabled', false);
        config()->set('cloud.providers.arvan.api_key', null);
        $this->resetCloudResolutionState();

        $this->assertInstanceOf(
            LiaraCloudProvider::class,
            $this->app->make(CloudProviderInterface::class),
        );
    }

    public function test_enabled_provider_requires_its_credentials(): void
    {
        config()->set('cloud.providers.liara.api_token', null);
        $this->resetCloudResolutionState();

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'Liara API token is not configured.',
        );

        $this->app->make(CloudProviderRegistryInterface::class);
    }

    public function test_default_provider_must_be_enabled(): void
    {
        config()->set('cloud.default', 'liara');
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', false);
        $this->resetCloudResolutionState();

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'The default cloud provider [liara] is disabled.',
        );

        $this->app->make(CloudProviderRegistryInterface::class);
    }

    public function test_disabled_provider_cannot_accept_new_purchases(): void
    {
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', true);
        $this->resetCloudResolutionState();

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'The cloud provider [liara] cannot accept purchases while disabled.',
        );

        $this->app->make(CloudProviderRegistryInterface::class);
    }

    public function test_availability_flags_must_be_boolean(): void
    {
        config()->set('cloud.providers.liara.enabled', 'yes');
        $this->resetCloudResolutionState();

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage(
            'Cloud provider [liara] availability flag [enabled] must be boolean.',
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
