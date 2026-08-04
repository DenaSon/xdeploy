<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
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

        $this->registerProviderContracts();
        $this->registerProvisioningWorkflow();
    }

    private function registerArvanCloudClient(): void
    {
        $this->app->singleton(
            ArvanCloudClient::class,
            function (): ArvanCloudClient {
                return new ArvanCloudClient(
                    baseUrl: $this->requiredStringConfig(
                        'cloud.providers.arvan.base_url',
                        'ArvanCloud base URL is not configured.',
                    ),

                    apiKey: $this->requiredStringConfig(
                        'cloud.providers.arvan.api_key',
                        'ArvanCloud API key is not configured.',
                    ),

                    connectTimeout: $this->positiveIntegerConfig(
                        'cloud.providers.arvan.timeouts.connect',
                        'ArvanCloud connect timeout must be greater than zero.',
                    ),

                    requestTimeout: $this->positiveIntegerConfig(
                        'cloud.providers.arvan.timeouts.request',
                        'ArvanCloud request timeout must be greater than zero.',
                    ),
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
            function (
                Application $app,
            ): ArvanCloudProvider {
                return new ArvanCloudProvider(
                    client: $app->make(
                        ArvanCloudClient::class,
                    ),

                    mapper: $app->make(
                        ArvanCloudResponseMapper::class,
                    ),

                    createType: $this->requiredStringConfig(
                        'cloud.providers.arvan.defaults.create_type',
                        'ArvanCloud create type is not configured.',
                    ),

                    defaultUsername: $this->requiredStringConfig(
                        'cloud.providers.arvan.defaults.username',
                        'ArvanCloud default username is not configured.',
                    ),
                );
            },
        );
    }

    private function registerProviderContracts(): void
    {
        $this->app->singleton(
            CloudProviderInterface::class,
            function (
                Application $app,
            ): CloudProviderInterface {
                return $this->resolveDefaultProvider(
                    $app,
                );
            },
        );

        $this->app->singleton(
            CloudServerProvisionerInterface::class,
            function (
                Application $app,
            ): CloudServerProvisionerInterface {
                return $this->resolveDefaultProvider(
                    $app,
                );
            },
        );
    }

    private function registerProvisioningWorkflow(): void
    {
        $this->app->bind(
            ProvisionCloudServerAction::class,
            function (
                Application $app,
            ): ProvisionCloudServerAction {
                return new ProvisionCloudServerAction(
                    catalog: $app->make(
                        CloudProviderInterface::class,
                    ),

                    provisioner: $app->make(
                        CloudServerProvisionerInterface::class,
                    ),

                    createServer: $app->make(
                        CreateServerAction::class,
                    ),

                    verifySshReadiness: $app->make(
                        VerifyCloudServerSshReadinessAction::class,
                    ),

                    providerName: $this->defaultCloudProvider(),

                    maxAttempts: $this->positiveIntegerConfig(
                        'cloud.provisioning.max_attempts',
                        'Cloud provisioning attempts must be greater than zero.',
                    ),

                    pollDelaySeconds: $this->nonNegativeIntegerConfig(
                        'cloud.provisioning.poll_delay_seconds',
                        'Cloud provisioning poll delay cannot be negative.',
                    ),
                );
            },
        );
    }

    private function resolveDefaultProvider(
        Application $app,
    ): ArvanCloudProvider {
        return match (
            $this->defaultCloudProvider()
        ) {
            'arvan' => $app->make(
                ArvanCloudProvider::class,
            ),

            default => throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not supported.',
                    $this->defaultCloudProvider(),
                ),
            ),
        };
    }

    private function defaultCloudProvider(): string
    {
        return strtolower(
            $this->requiredStringConfig(
                'cloud.default',
                'The default cloud provider is not configured.',
            ),
        );
    }

    private function requiredStringConfig(
        string $key,
        string $message,
    ): string {
        $value = config($key);

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new CloudConfigurationException(
                $message,
            );
        }

        return trim($value);
    }

    private function positiveIntegerConfig(
        string $key,
        string $message,
    ): int {
        $value = config($key);

        if (
            ! is_int($value)
            && ! is_numeric($value)
        ) {
            throw new CloudConfigurationException(
                $message,
            );
        }

        $value = (int) $value;

        if ($value < 1) {
            throw new CloudConfigurationException(
                $message,
            );
        }

        return $value;
    }

    private function nonNegativeIntegerConfig(
        string $key,
        string $message,
    ): int {
        $value = config($key);

        if (
            ! is_int($value)
            && ! is_numeric($value)
        ) {
            throw new CloudConfigurationException(
                $message,
            );
        }

        $value = (int) $value;

        if ($value < 0) {
            throw new CloudConfigurationException(
                $message,
            );
        }

        return $value;
    }
}
