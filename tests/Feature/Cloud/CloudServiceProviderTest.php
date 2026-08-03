<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
{
    public function test_cloud_provider_contract_is_registered(): void
    {
        $this->assertTrue(
            $this->app->bound(CloudProviderInterface::class),
        );
    }

    public function test_arvan_cloud_provider_is_registered_as_singleton(): void
    {
        config()->set(
            'cloud.providers.arvan.api_key',
            'test-api-key',
        );

        $first = $this->app->make(
            ArvanCloudProvider::class,
        );

        $second = $this->app->make(
            ArvanCloudProvider::class,
        );

        $this->assertSame($first, $second);
    }

    public function test_arvan_driver_resolves_cloud_provider_contract(): void
    {
        config()->set(
            'cloud.default',
            'arvan',
        );

        config()->set(
            'cloud.providers.arvan.api_key',
            'test-api-key',
        );

        $provider = $this->app->make(
            CloudProviderInterface::class,
        );

        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $provider,
        );

        $this->assertSame(
            $provider,
            $this->app->make(
                CloudProviderInterface::class,
            ),
        );
    }

    public function test_arvan_cloud_client_is_registered_as_singleton(): void
    {
        config()->set(
            'cloud.providers.arvan.api_key',
            'test-api-key',
        );

        $first = $this->app->make(
            ArvanCloudClient::class,
        );

        $second = $this->app->make(
            ArvanCloudClient::class,
        );

        $this->assertSame($first, $second);
    }

    public function test_arvan_cloud_mapper_is_registered_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudResponseMapper::class,
        );

        $second = $this->app->make(
            ArvanCloudResponseMapper::class,
        );

        $this->assertSame($first, $second);
    }

    public function test_unknown_cloud_provider_is_rejected(): void
    {
        Config::set('cloud.default', 'unsupported-provider');

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'The cloud provider [unsupported-provider] is not supported.',
        );

        $this->app->make(CloudProviderInterface::class);
    }

    public function test_empty_cloud_provider_is_rejected(): void
    {
        Config::set('cloud.default', '  ');

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'The default cloud provider is not configured.',
        );

        $this->app->make(CloudProviderInterface::class);
    }
}
