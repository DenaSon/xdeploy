<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerArvanCloudClient();
        $this->registerArvanCloudMapper();
        $this->registerArvanCloudProvider();
        $this->registerCloudProviderContract();
    }

    private function registerArvanCloudClient(): void
    {
        $this->app->singleton(
            ArvanCloudClient::class,
            static function (): ArvanCloudClient {
                $baseUrl = config(
                    'cloud.providers.arvan.base_url',
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
                        'ArvanCloud base URL is not configured.',
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

                return new ArvanCloudClient(
                    baseUrl: trim($baseUrl),
                    apiKey: trim($apiKey),
                    connectTimeout: (int) $connectTimeout,
                    requestTimeout: (int) $requestTimeout,
                );
            },
        );
    }

    private function registerArvanCloudMapper(): void
    {
        $this->app->singleton(
            ArvanCloudResponseMapper::class,
        );
    }

    private function registerArvanCloudProvider(): void
    {
        $this->app->singleton(
            ArvanCloudProvider::class,
        );
    }

    private function registerCloudProviderContract(): void
    {
        $this->app->singleton(
            CloudProviderInterface::class,
            function (
                Application $app,
            ): CloudProviderInterface {
                $provider = $this->defaultCloudProvider();

                return match ($provider) {
                    'arvan' => $app->make(
                        ArvanCloudProvider::class,
                    ),

                    default => throw new CloudConfigurationException(
                        sprintf(
                            'The cloud provider [%s] is not supported.',
                            $provider,
                        ),
                    ),
                };
            },
        );
    }

    private function defaultCloudProvider(): string
    {
        $provider = config('cloud.default');

        if (
            ! is_string($provider)
            || trim($provider) === ''
        ) {
            throw new CloudConfigurationException(
                'The default cloud provider is not configured.',
            );
        }

        return strtolower(
            trim($provider),
        );
    }
}
