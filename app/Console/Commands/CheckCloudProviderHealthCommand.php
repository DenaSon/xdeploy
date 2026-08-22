<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature(
    'cloud:providers:health-check
        {--provider= : Probe one registered provider instead of all registered providers}'
)]
#[Description(
    'Probe registered cloud providers using a lightweight uncached catalog request'
)]
final class CheckCloudProviderHealthCommand extends Command
{
    public function __construct(
        private readonly CloudProviderRegistryInterface $providers,
        private readonly CloudProviderHealthEngine $health,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $providers = $this->selectedProviders();
        } catch (CloudConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        if ($providers === []) {
            $this->components->warn(
                'No cloud providers are currently registered.',
            );

            return self::SUCCESS;
        }

        $failures = [];

        foreach ($providers as $provider) {
            if (! $this->providers->supportsCapability(
                $provider,
                CloudPurchaseCatalogSourceInterface::class,
            )) {
                $this->components->warn(
                    sprintf(
                        'Provider [%s] does not expose a lightweight catalog probe.',
                        $provider->value,
                    ),
                );

                continue;
            }

            /** @var CloudPurchaseCatalogSourceInterface $catalog */
            $catalog = $this->providers->resolveCapability(
                $provider,
                CloudPurchaseCatalogSourceInterface::class,
            );

            try {
                $catalog->listPurchaseRegions();

                $this->components->info(
                    sprintf(
                        'Provider [%s] health probe succeeded.',
                        $provider->value,
                    ),
                );
            } catch (CloudUnexpectedResponseException $exception) {
                $this->recordNonTransportFailure(
                    provider: $provider,
                    category: CloudProviderHealthFailureCategory::UnexpectedResponse,
                    httpStatus: $exception->getCode() > 0
                        ? $exception->getCode()
                        : null,
                );

                report($exception);
                $failures[] = $provider->value;

                $this->components->warn(
                    sprintf(
                        'Provider [%s] health probe returned an unexpected response.',
                        $provider->value,
                    ),
                );
            } catch (CloudConfigurationException $exception) {
                $this->recordNonTransportFailure(
                    provider: $provider,
                    category: CloudProviderHealthFailureCategory::Configuration,
                    httpStatus: null,
                );

                report($exception);
                $failures[] = $provider->value;

                $this->components->warn(
                    sprintf(
                        'Provider [%s] health probe is not correctly configured.',
                        $provider->value,
                    ),
                );
            } catch (Throwable $exception) {
                /*
                 * HTTP failures are already classified and recorded by the
                 * provider HTTP observer. Do not double-count them here.
                 */
                report($exception);
                $failures[] = $provider->value;

                $this->components->warn(
                    sprintf(
                        'Provider [%s] health probe failed.',
                        $provider->value,
                    ),
                );
            }
        }

        return $failures === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @return list<CloudProviderType>
     */
    private function selectedProviders(): array
    {
        $registeredProviders = $this->providers->registeredProviders();
        $option = $this->option('provider');

        if ($option === null) {
            return $registeredProviders;
        }

        if (! is_string($option) || trim($option) === '') {
            throw new CloudConfigurationException(
                'Cloud provider option cannot be empty.',
            );
        }

        $provider = CloudProviderType::tryFrom(
            strtolower(trim($option)),
        );

        if (! $provider instanceof CloudProviderType) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not supported.',
                    trim($option),
                ),
            );
        }

        if (! in_array($provider, $registeredProviders, true)) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not registered.',
                    $provider->value,
                ),
            );
        }

        return [$provider];
    }

    private function recordNonTransportFailure(
        CloudProviderType $provider,
        CloudProviderHealthFailureCategory $category,
        ?int $httpStatus,
    ): void {
        $this->health->recordFailure(
            provider: $provider,
            category: $category,
            httpStatus: $httpStatus,
            latencyMs: null,
            operation: 'health.probe',
        );
    }
}
