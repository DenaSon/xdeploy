<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cloud.default',
            'arvan',
        );

        config()->set(
            'cloud.providers.arvan.base_url',
            'https://api.example.test/ecc/v1',
        );

        config()->set(
            'cloud.providers.arvan.api_key',
            'test-api-key',
        );

        config()->set(
            'cloud.providers.arvan.timeouts.connect',
            5,
        );

        config()->set(
            'cloud.providers.arvan.timeouts.request',
            15,
        );

        config()->set(
            'cloud.providers.arvan.defaults.create_type',
            'cinder',
        );
    }

    public function test_it_resolves_default_cloud_provider(): void
    {
        $provider = $this->app->make(
            CloudProviderInterface::class,
        );

        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $provider,
        );
    }

    public function test_it_resolves_concrete_provider_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudProvider::class,
        );

        $second = $this->app->make(
            ArvanCloudProvider::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_default_provider_and_concrete_provider_are_the_same_instance(): void
    {
        $defaultProvider = $this->app->make(
            CloudProviderInterface::class,
        );

        $concreteProvider = $this->app->make(
            ArvanCloudProvider::class,
        );

        $this->assertSame(
            $concreteProvider,
            $defaultProvider,
        );
    }

    #[DataProvider('cloudCapabilityContractProvider')]
    public function test_each_cloud_capability_contract_resolves_to_arvan_provider(
        string $contract,
    ): void {
        $resolved = $this->app->make(
            $contract,
        );

        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $resolved,
        );
    }

    #[DataProvider('cloudCapabilityContractProvider')]
    public function test_each_cloud_capability_contract_uses_the_default_provider_instance(
        string $contract,
    ): void {
        $defaultProvider = $this->app->make(
            CloudProviderInterface::class,
        );

        $capability = $this->app->make(
            $contract,
        );

        $this->assertSame(
            $defaultProvider,
            $capability,
        );
    }

    public function test_all_cloud_contracts_share_one_provider_instance(): void
    {
        $provider = $this->app->make(
            CloudProviderInterface::class,
        );

        $contracts = [
            CloudServerConsoleInterface::class,
            CloudServerCredentialManagerInterface::class,
            CloudServerInventoryInterface::class,
            CloudServerProvisionerInterface::class,
            CloudServerLifecycleInterface::class,
            CloudServerNetworkingInterface::class,
            CloudServerReportsInterface::class,
            CloudServerResizeCatalogInterface::class,
            CloudServerResizerInterface::class,
        ];

        foreach ($contracts as $contract) {
            $this->assertSame(
                $provider,
                $this->app->make(
                    $contract,
                ),
                sprintf(
                    'Contract [%s] did not resolve to the default provider instance.',
                    $contract,
                ),
            );
        }
    }

    public function test_arvan_cloud_client_is_registered_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudClient::class,
        );

        $second = $this->app->make(
            ArvanCloudClient::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_arvan_cloud_mapper_is_registered_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudResponseMapper::class,
        );

        $second = $this->app->make(
            ArvanCloudResponseMapper::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_it_rejects_unsupported_default_provider(): void
    {
        config()->set(
            'cloud.default',
            'unsupported-provider',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'The cloud provider [unsupported-provider] is not supported.',
        );

        $this->app->make(
            CloudProviderInterface::class,
        );
    }

    public function test_it_rejects_missing_default_provider(): void
    {
        config()->set(
            'cloud.default',
            null,
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'The default cloud provider is not configured.',
        );

        $this->app->make(
            CloudProviderInterface::class,
        );
    }

    public function test_it_rejects_missing_arvan_api_key(): void
    {
        config()->set(
            'cloud.providers.arvan.api_key',
            null,
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud API key is not configured.',
        );

        $this->app->make(
            ArvanCloudProvider::class,
        );
    }

    public function test_it_rejects_missing_arvan_base_url(): void
    {
        config()->set(
            'cloud.providers.arvan.base_url',
            null,
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud base URL is not configured.',
        );

        $this->app->make(
            ArvanCloudProvider::class,
        );
    }

    public function test_it_rejects_invalid_create_type_configuration(): void
    {
        config()->set(
            'cloud.providers.arvan.defaults.create_type',
            '   ',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud default create type is not configured.',
        );

        $this->app->make(
            ArvanCloudProvider::class,
        );
    }

    /**
     * @return array<string, array{contract: class-string}>
     */
    public static function cloudCapabilityContractProvider(): array
    {
        return [
            'console' => [
                'contract' => CloudServerConsoleInterface::class,
            ],

            'credential manager' => [
                'contract' => CloudServerCredentialManagerInterface::class,
            ],

            'inventory' => [
                'contract' => CloudServerInventoryInterface::class,
            ],

            'provisioner' => [
                'contract' => CloudServerProvisionerInterface::class,
            ],

            'lifecycle' => [
                'contract' => CloudServerLifecycleInterface::class,
            ],

            'networking' => [
                'contract' => CloudServerNetworkingInterface::class,
            ],

            'reports' => [
                'contract' => CloudServerReportsInterface::class,
            ],

            'resize catalog' => [
                'contract' => CloudServerResizeCatalogInterface::class,
            ],

            'resizer' => [
                'contract' => CloudServerResizerInterface::class,
            ],
        ];
    }
}
