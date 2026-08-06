<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cloud.default' => 'arvan',

            'cloud.providers.arvan.base_url' =>
                'https://api.example.test/ecc/v1',

            'cloud.providers.arvan.api_key' =>
                'test-api-key',

            'cloud.providers.arvan.timeouts.connect' =>
                5,

            'cloud.providers.arvan.timeouts.request' =>
                15,

            'cloud.providers.arvan.defaults.create_type' =>
                'cinder',

            'cloud.providers.arvan.defaults.default_username' =>
                'ubuntu',
        ]);
    }

    public function test_it_resolves_arvan_cloud_client_as_singleton(): void
    {
        $first = app(
            ArvanCloudClient::class,
        );

        $second = app(
            ArvanCloudClient::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_it_resolves_response_mapper_as_singleton(): void
    {
        $first = app(
            ArvanCloudResponseMapper::class,
        );

        $second = app(
            ArvanCloudResponseMapper::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_it_resolves_arvan_provider_as_singleton(): void
    {
        $first = app(
            ArvanCloudProvider::class,
        );

        $second = app(
            ArvanCloudProvider::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_all_cloud_contracts_resolve_to_same_provider(): void
    {
        $provider = app(
            ArvanCloudProvider::class,
        );

        $catalog = app(
            CloudProviderInterface::class,
        );

        $provisioner = app(
            CloudServerProvisionerInterface::class,
        );

        $lifecycle = app(
            CloudServerLifecycleInterface::class,
        );

        $networking = app(
            CloudServerNetworkingInterface::class,
        );

        $this->assertSame(
            $provider,
            $catalog,
        );

        $this->assertSame(
            $provider,
            $provisioner,
        );

        $this->assertSame(
            $provider,
            $lifecycle,
        );

        $this->assertSame(
            $provider,
            $networking,
        );
    }

    public function test_resolved_provider_implements_all_cloud_contracts(): void
    {
        $provider = app(
            ArvanCloudProvider::class,
        );

        $this->assertInstanceOf(
            CloudProviderInterface::class,
            $provider,
        );

        $this->assertInstanceOf(
            CloudServerProvisionerInterface::class,
            $provider,
        );

        $this->assertInstanceOf(
            CloudServerLifecycleInterface::class,
            $provider,
        );

        $this->assertInstanceOf(
            CloudServerNetworkingInterface::class,
            $provider,
        );
    }

    public function test_it_rejects_unsupported_default_provider(): void
    {
        config([
            'cloud.default' => 'unsupported-provider',
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'The cloud provider [unsupported-provider] is not supported.',
        );

        app(
            CloudProviderInterface::class,
        );
    }

    public function test_it_rejects_missing_arvan_api_key(): void
    {
        config([
            'cloud.providers.arvan.api_key' => null,
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud API key is not configured.',
        );

        app(
            ArvanCloudProvider::class,
        );
    }

    public function test_it_rejects_missing_default_username(): void
    {
        config([
            'cloud.providers.arvan.defaults.default_username' =>
                '',
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud default username is not configured.',
        );

        app(
            ArvanCloudProvider::class,
        );
    }

    public function test_it_rejects_missing_create_type(): void
    {
        config([
            'cloud.providers.arvan.defaults.create_type' =>
                '',
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud create type is not configured.',
        );

        app(
            ArvanCloudProvider::class,
        );
    }
}
