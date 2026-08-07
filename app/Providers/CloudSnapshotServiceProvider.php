<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudSnapshotManager;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudV2Client;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudSnapshotResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CloudSnapshotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerArvanCloudV2Client();
        $this->registerSnapshotMapper();
        $this->registerSnapshotManager();
        $this->registerSnapshotContract();
    }

    private function registerArvanCloudV2Client(): void
    {
        $this->app->singleton(
            ArvanCloudV2Client::class,
            static function (): ArvanCloudV2Client {
                $baseUrl = config(
                    'cloud_snapshot.providers.arvan.base_url',
                );

                $apiKey = config(
                    'cloud.providers.arvan.api_key',
                );

                $connectTimeout = config(
                    'cloud.providers.arvan.timeouts.connect',
                    10,
                );

                $requestTimeout = config(
                    'cloud.providers.arvan.timeouts.request',
                    90,
                );

                if (
                    ! is_string($baseUrl)
                    || trim($baseUrl) === ''
                ) {
                    throw new CloudConfigurationException(
                        'ArvanCloud v2 base URL is not configured.',
                    );
                }

                if (
                    ! is_string($apiKey)
                    || trim($apiKey) === ''
                ) {
                    throw new CloudConfigurationException(
                        'ArvanCloud API key is not configured.',
                    );
                }

                if (
                    ! is_int($connectTimeout)
                    && ! is_numeric($connectTimeout)
                ) {
                    throw new CloudConfigurationException(
                        'ArvanCloud connect timeout must be an integer.',
                    );
                }

                if (
                    ! is_int($requestTimeout)
                    && ! is_numeric($requestTimeout)
                ) {
                    throw new CloudConfigurationException(
                        'ArvanCloud request timeout must be an integer.',
                    );
                }

                return new ArvanCloudV2Client(
                    baseUrl: trim($baseUrl),
                    apiKey: trim($apiKey),
                    connectTimeout: (int) $connectTimeout,
                    requestTimeout: (int) $requestTimeout,
                );
            },
        );
    }

    private function registerSnapshotMapper(): void
    {
        $this->app->singleton(
            ArvanCloudSnapshotResponseMapper::class,
        );
    }

    private function registerSnapshotManager(): void
    {
        $this->app->singleton(
            ArvanCloudSnapshotManager::class,
            static function (
                Application $app,
            ): ArvanCloudSnapshotManager {
                return new ArvanCloudSnapshotManager(
                    client: $app->make(
                        ArvanCloudV2Client::class,
                    ),
                    mapper: $app->make(
                        ArvanCloudSnapshotResponseMapper::class,
                    ),
                );
            },
        );
    }

    private function registerSnapshotContract(): void
    {
        $this->app->singleton(
            CloudServerSnapshotManagerInterface::class,
            static function (
                Application $app,
            ): CloudServerSnapshotManagerInterface {
                return $app->make(
                    ArvanCloudSnapshotManager::class,
                );
            },
        );
    }
}
