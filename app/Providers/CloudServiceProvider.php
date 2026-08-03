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
        $this->registerCloudProvider();
    }

    private function registerArvanCloudClient(): void
    {
        $this->app->singleton(
            ArvanCloudClient::class,
            static fn (): ArvanCloudClient => new ArvanCloudClient(
                baseUrl: (string) config(
                    'cloud.providers.arvan.base_url',
                ),
                apiKey: (string) config(
                    'cloud.providers.arvan.api_key',
                ),
                connectTimeout: (int) config(
                    'cloud.providers.arvan.timeouts.connect',
                ),
                requestTimeout: (int) config(
                    'cloud.providers.arvan.timeouts.request',
                ),
            ),
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

    private function registerCloudProvider(): void
    {
        $this->app->singleton(
            CloudProviderInterface::class,
            function (Application $app): CloudProviderInterface {
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

        if (! is_string($provider) || trim($provider) === '') {
            throw new CloudConfigurationException(
                'The default cloud provider is not configured.',
            );
        }

        return strtolower(trim($provider));
    }
}
