<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvisioner;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
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
    }

    public function test_it_resolves_default_cloud_provider(): void
    {
        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $this->app->make(CloudProviderInterface::class),
        );
    }

    public function test_registry_resolves_registered_arvan_provider(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            $this->app->make(ArvanCloudProvider::class),
            $registry->resolve(CloudProviderType::Arvan),
        );
    }

    public function test_registry_resolves_arvan_provisioning_capability_override(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            $this->app->make(ArvanCloudProvisioner::class),
            $registry->resolveCapability(
                provider: CloudProviderType::Arvan,
                capability: CloudServerProvisionerInterface::class,
            ),
        );
    }

    public function test_default_provisioner_uses_arvan_provisioning_adapter(): void
    {
        $this->assertSame(
            $this->app->make(ArvanCloudProvisioner::class),
            $this->app->make(CloudServerProvisionerInterface::class),
        );
    }

    public function test_registry_rejects_unregistered_provider(): void
    {
        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('The cloud provider [liara] is not registered.');

        $registry->resolve(CloudProviderType::Liara);
    }

    public function test_it_resolves_concrete_provider_as_singleton(): void
    {
        $this->assertSame(
            $this->app->make(ArvanCloudProvider::class),
            $this->app->make(ArvanCloudProvider::class),
        );
    }

    public function test_default_provider_and_concrete_provider_are_the_same_instance(): void
    {
        $this->assertSame(
            $this->app->make(ArvanCloudProvider::class),
            $this->app->make(CloudProviderInterface::class),
        );
    }

    #[DataProvider('providerBackedCapabilityContractProvider')]
    public function test_provider_backed_capability_contract_resolves_to_arvan_provider(
        string $contract,
    ): void {
        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $this->app->make($contract),
        );
    }

    #[DataProvider('providerBackedCapabilityContractProvider')]
    public function test_provider_backed_capability_contract_uses_default_provider_instance(
        string $contract,
    ): void {
        $this->assertSame(
            $this->app->make(CloudProviderInterface::class),
            $this->app->make($contract),
        );
    }

    public function test_arvan_cloud_client_is_registered_as_singleton(): void
    {
        $this->assertSame(
            $this->app->make(ArvanCloudClient::class),
            $this->app->make(ArvanCloudClient::class),
        );
    }

    public function test_arvan_cloud_mapper_is_registered_as_singleton(): void
    {
        $this->assertSame(
            $this->app->make(ArvanCloudResponseMapper::class),
            $this->app->make(ArvanCloudResponseMapper::class),
        );
    }

    public function test_it_rejects_unsupported_default_provider(): void
    {
        config()->set('cloud.default', 'unsupported-provider');

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('The cloud provider [unsupported-provider] is not supported.');

        $this->app->make(CloudProviderInterface::class);
    }

    public function test_it_rejects_missing_default_provider(): void
    {
        config()->set('cloud.default', null);

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('The default cloud provider is not configured.');

        $this->app->make(CloudProviderInterface::class);
    }

    public function test_it_rejects_missing_arvan_api_key(): void
    {
        config()->set('cloud.providers.arvan.api_key', null);

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('ArvanCloud API key is not configured.');

        $this->app->make(ArvanCloudProvider::class);
    }

    public function test_it_rejects_missing_arvan_base_url(): void
    {
        config()->set('cloud.providers.arvan.base_url', null);

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('ArvanCloud base URL is not configured.');

        $this->app->make(ArvanCloudProvider::class);
    }

    public function test_it_rejects_invalid_create_type_configuration(): void
    {
        config()->set('cloud.providers.arvan.defaults.create_type', '   ');

        $this->expectException(CloudConfigurationException::class);
        $this->expectExceptionMessage('ArvanCloud default create type is not configured.');

        $this->app->make(ArvanCloudProvider::class);
    }

    /**
     * @return array<string, array{contract: class-string}>
     */
    public static function providerBackedCapabilityContractProvider(): array
    {
        return [
            'console' => ['contract' => CloudServerConsoleInterface::class],
            'credential manager' => ['contract' => CloudServerCredentialManagerInterface::class],
            'inventory' => ['contract' => CloudServerInventoryInterface::class],
            'lifecycle' => ['contract' => CloudServerLifecycleInterface::class],
            'networking' => ['contract' => CloudServerNetworkingInterface::class],
            'reports' => ['contract' => CloudServerReportsInterface::class],
            'resize catalog' => ['contract' => CloudServerResizeCatalogInterface::class],
            'resizer' => ['contract' => CloudServerResizerInterface::class],
        ];
    }
}
