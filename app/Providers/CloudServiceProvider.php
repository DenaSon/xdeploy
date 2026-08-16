<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudCatalogReaderResolverInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\Contracts\CloudQuotaReaderInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\Contracts\CloudSshKeyCatalogInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudCatalogCapabilities;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvisioner;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use App\Infrastructure\Cloud\Catalog\CloudCatalogReaderResolver;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerArvanCloudClient();
        $this->registerArvanCloudMapper();
        $this->registerArvanCloudProvider();
        $this->registerArvanCloudCatalogCapabilities();
        $this->registerArvanCloudProvisioner();

        $this->registerLiaraCloudClient();
        $this->registerLiaraCloudMapper();
        $this->registerLiaraCloudProvider();

        $this->registerCloudProviderRegistry();
        $this->registerCloudProviderContract();
        $this->registerCloudCatalogReader();
        $this->registerCloudCapabilityContracts();
    }

    private function registerArvanCloudClient(): void
    {
        $this->app->singleton(
            ArvanCloudClient::class,
            static function (): ArvanCloudClient {
                $baseUrl = config('cloud.providers.arvan.base_url');
                $apiKey = config('cloud.providers.arvan.api_key');
                $connectTimeout = config('cloud.providers.arvan.timeouts.connect', 10);
                $requestTimeout = config('cloud.providers.arvan.timeouts.request', 90);

                if (! is_string($baseUrl) || trim($baseUrl) === '') {
                    throw new CloudConfigurationException(
                        'ArvanCloud base URL is not configured.',
                    );
                }

                if (! is_string($apiKey) || trim($apiKey) === '') {
                    throw new CloudConfigurationException(
                        'ArvanCloud API key is not configured.',
                    );
                }

                if (! is_int($connectTimeout) && ! is_numeric($connectTimeout)) {
                    throw new CloudConfigurationException(
                        'ArvanCloud connect timeout must be an integer.',
                    );
                }

                if (! is_int($requestTimeout) && ! is_numeric($requestTimeout)) {
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
        $this->app->singleton(ArvanCloudResponseMapper::class);
    }

    private function registerArvanCloudProvider(): void
    {
        $this->app->singleton(
            ArvanCloudProvider::class,
            static function (Application $app): ArvanCloudProvider {
                $createType = config(
                    'cloud.providers.arvan.defaults.create_type',
                    'cinder',
                );

                if (! is_string($createType) || trim($createType) === '') {
                    throw new CloudConfigurationException(
                        'ArvanCloud default create type is not configured.',
                    );
                }

                return new ArvanCloudProvider(
                    client: $app->make(ArvanCloudClient::class),
                    mapper: $app->make(ArvanCloudResponseMapper::class),
                    createType: trim($createType),
                    defaultUsername: 'ubuntu',
                );
            },
        );
    }

    private function registerArvanCloudCatalogCapabilities(): void
    {
        $this->app->singleton(
            ArvanCloudCatalogCapabilities::class,
            static fn (Application $app): ArvanCloudCatalogCapabilities => new ArvanCloudCatalogCapabilities(
                provider: $app->make(ArvanCloudProvider::class),
            ),
        );
    }

    private function registerArvanCloudProvisioner(): void
    {
        $this->app->singleton(
            ArvanCloudProvisioner::class,
            static fn (Application $app): ArvanCloudProvisioner => new ArvanCloudProvisioner(
                provider: $app->make(ArvanCloudProvider::class),
                resolveInfrastructure: new ResolveCloudProvisioningInfrastructureAction(
                    cloud: $app->make(ArvanCloudCatalogCapabilities::class),
                ),
            ),
        );
    }

    private function registerLiaraCloudClient(): void
    {
        $this->app->singleton(
            LiaraCloudClient::class,
            static function (): LiaraCloudClient {
                $baseUrl = config('cloud.providers.liara.base_url');
                $apiToken = config('cloud.providers.liara.api_token');
                $connectTimeout = config('cloud.providers.liara.timeouts.connect', 10);
                $requestTimeout = config('cloud.providers.liara.timeouts.request', 90);

                if (! is_string($baseUrl) || trim($baseUrl) === '') {
                    throw new CloudConfigurationException(
                        'Liara base URL is not configured.',
                    );
                }

                if (! is_string($apiToken) || trim($apiToken) === '') {
                    throw new CloudConfigurationException(
                        'Liara API token is not configured.',
                    );
                }

                if (! is_int($connectTimeout) && ! is_numeric($connectTimeout)) {
                    throw new CloudConfigurationException(
                        'Liara connect timeout must be an integer.',
                    );
                }

                if (! is_int($requestTimeout) && ! is_numeric($requestTimeout)) {
                    throw new CloudConfigurationException(
                        'Liara request timeout must be an integer.',
                    );
                }

                return new LiaraCloudClient(
                    baseUrl: trim($baseUrl),
                    apiToken: trim($apiToken),
                    connectTimeout: (int) $connectTimeout,
                    requestTimeout: (int) $requestTimeout,
                );
            },
        );
    }

    private function registerLiaraCloudMapper(): void
    {
        $this->app->singleton(LiaraCloudResponseMapper::class);
    }

    private function registerLiaraCloudProvider(): void
    {
        $this->app->singleton(
            LiaraCloudProvider::class,
            static fn (Application $app): LiaraCloudProvider => new LiaraCloudProvider(
                client: $app->make(LiaraCloudClient::class),
                mapper: $app->make(LiaraCloudResponseMapper::class),
            ),
        );
    }

    private function registerCloudProviderRegistry(): void
    {
        $this->app->singleton(
            CloudProviderRegistry::class,
            static function (Application $app): CloudProviderRegistry {
                $arvanCapabilities = $app->make(
                    ArvanCloudCatalogCapabilities::class,
                );

                $providers = [
                    CloudProviderType::Arvan->value => $app->make(
                        ArvanCloudProvider::class,
                    ),
                ];

                $liaraToken = config('cloud.providers.liara.api_token');

                if (is_string($liaraToken) && trim($liaraToken) !== '') {
                    $providers[CloudProviderType::Liara->value] = $app->make(
                        LiaraCloudProvider::class,
                    );
                }

                return new CloudProviderRegistry(
                    providers: $providers,
                    capabilities: [
                        CloudProviderType::Arvan->value => [
                            CloudServerProvisionerInterface::class => $app->make(
                                ArvanCloudProvisioner::class,
                            ),
                            CloudProvisioningInfrastructureCatalogInterface::class => $arvanCapabilities,
                            CloudQuotaReaderInterface::class => $arvanCapabilities,
                            CloudSshKeyCatalogInterface::class => $arvanCapabilities,
                        ],
                    ],
                );
            },
        );

        $this->app->singleton(
            CloudProviderRegistryInterface::class,
            static fn (Application $app): CloudProviderRegistryInterface => $app->make(
                CloudProviderRegistry::class,
            ),
        );
    }

    private function registerCloudProviderContract(): void
    {
        $this->app->singleton(
            CloudProviderInterface::class,
            function (Application $app): CloudProviderInterface {
                return $app->make(CloudProviderRegistryInterface::class)
                    ->resolve($this->defaultCloudProvider());
            },
        );
    }

    private function registerCloudCatalogReader(): void
    {
        $this->app->singleton(
            CloudCatalogReaderResolver::class,
            static fn (Application $app): CloudCatalogReaderResolver => new CloudCatalogReaderResolver(
                providers: $app->make(CloudProviderRegistryInterface::class),
            ),
        );

        $this->app->singleton(
            CloudCatalogReaderResolverInterface::class,
            static fn (Application $app): CloudCatalogReaderResolverInterface => $app->make(
                CloudCatalogReaderResolver::class,
            ),
        );

        $this->app->singleton(
            CloudCatalogReaderInterface::class,
            function (Application $app): CloudCatalogReaderInterface {
                return $app->make(CloudCatalogReaderResolverInterface::class)
                    ->resolve($this->defaultCloudProvider());
            },
        );
    }

    private function registerCloudCapabilityContracts(): void
    {
        $contracts = [
            CloudProvisioningInfrastructureCatalogInterface::class,
            CloudQuotaReaderInterface::class,
            CloudSshKeyCatalogInterface::class,
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
            $this->app->singleton(
                $contract,
                function (Application $app) use ($contract): object {
                    return $app->make(CloudProviderRegistryInterface::class)
                        ->resolveCapability(
                            provider: $this->defaultCloudProvider(),
                            capability: $contract,
                        );
                },
            );
        }
    }

    private function defaultCloudProvider(): CloudProviderType
    {
        $provider = config('cloud.default');

        if (! is_string($provider) || trim($provider) === '') {
            throw new CloudConfigurationException(
                'The default cloud provider is not configured.',
            );
        }

        $normalized = strtolower(trim($provider));
        $providerType = CloudProviderType::tryFrom($normalized);

        if (! $providerType instanceof CloudProviderType) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not supported.',
                    $normalized,
                ),
            );
        }

        return $providerType;
    }
}
