<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudSnapshotManager;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudV2Client;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudSnapshotResponseMapper;
use Tests\TestCase;

final class CloudSnapshotServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cloud_snapshot.providers.arvan.base_url',
            'https://api.example.test/ecc/v2',
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
    }

    public function test_it_resolves_snapshot_contract_to_arvan_manager(): void
    {
        $resolved = $this->app->make(
            CloudServerSnapshotManagerInterface::class,
        );

        $this->assertInstanceOf(
            ArvanCloudSnapshotManager::class,
            $resolved,
        );
    }

    public function test_snapshot_contract_and_concrete_manager_share_one_instance(): void
    {
        $contract = $this->app->make(
            CloudServerSnapshotManagerInterface::class,
        );

        $manager = $this->app->make(
            ArvanCloudSnapshotManager::class,
        );

        $this->assertSame(
            $manager,
            $contract,
        );
    }

    public function test_arvan_cloud_v2_client_is_registered_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudV2Client::class,
        );

        $second = $this->app->make(
            ArvanCloudV2Client::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_snapshot_mapper_is_registered_as_singleton(): void
    {
        $first = $this->app->make(
            ArvanCloudSnapshotResponseMapper::class,
        );

        $second = $this->app->make(
            ArvanCloudSnapshotResponseMapper::class,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_it_rejects_missing_arvan_v2_base_url(): void
    {
        config()->set(
            'cloud_snapshot.providers.arvan.base_url',
            null,
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud v2 base URL is not configured.',
        );

        $this->app->make(
            ArvanCloudV2Client::class,
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
            ArvanCloudV2Client::class,
        );
    }

    public function test_it_rejects_invalid_connect_timeout_configuration(): void
    {
        config()->set(
            'cloud.providers.arvan.timeouts.connect',
            'invalid',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud connect timeout must be an integer.',
        );

        $this->app->make(
            ArvanCloudV2Client::class,
        );
    }

    public function test_it_rejects_invalid_request_timeout_configuration(): void
    {
        config()->set(
            'cloud.providers.arvan.timeouts.request',
            'invalid',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud request timeout must be an integer.',
        );

        $this->app->make(
            ArvanCloudV2Client::class,
        );
    }
}
