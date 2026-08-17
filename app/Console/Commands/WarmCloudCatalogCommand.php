<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\Catalog\CloudCatalogReaderResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature(
    'cloud:catalog:warm
        {--provider= : Warm one purchasable provider instead of all purchasable providers}
        {--force : Forget current catalog cache entries and fetch them again}'
)]
#[Description(
    'Warm the cached cloud catalogs used by the VPS purchase page'
)]
final class WarmCloudCatalogCommand extends Command
{
    public function __construct(
        private readonly CloudProviderRegistryInterface $providers,
        private readonly CloudCatalogReaderResolver $catalogs,
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
                'No cloud providers are currently enabled for purchases.',
            );

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $failures = [];
        $warmedRegions = 0;

        foreach ($providers as $provider) {
            $catalog = $this->catalogs->resolve($provider);

            try {
                $regions = $force
                    ? $catalog->refreshRegions()
                    : $catalog->listRegions();
            } catch (Throwable $exception) {
                report($exception);

                $failures[] = $provider->value;

                $this->components->warn(
                    sprintf(
                        'Failed to load catalog regions for provider [%s].',
                        $provider->value,
                    ),
                );

                continue;
            }

            $regions = array_values(
                array_filter(
                    $regions,
                    static fn (CloudRegionData $region): bool => $region->canCreateServers
                        && $region->isVisible,
                ),
            );

            if ($regions === []) {
                $this->components->warn(
                    sprintf(
                        'No purchasable regions are available for provider [%s].',
                        $provider->value,
                    ),
                );

                continue;
            }

            foreach ($regions as $region) {
                try {
                    if ($force) {
                        $catalog->refreshRegion($region->id);
                    } else {
                        $catalog->warmRegion($region->id);
                    }

                    $warmedRegions++;

                    $this->components->info(
                        sprintf(
                            'Warmed %s:%s',
                            $provider->value,
                            $region->id,
                        ),
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    $failures[] = sprintf(
                        '%s:%s',
                        $provider->value,
                        $region->id,
                    );

                    $this->components->warn(
                        sprintf(
                            'Failed to warm %s:%s',
                            $provider->value,
                            $region->id,
                        ),
                    );
                }
            }
        }

        if ($failures !== []) {
            $this->components->error(
                sprintf(
                    'Cloud catalog warm-up completed with %d failure(s).',
                    count($failures),
                ),
            );

            return self::FAILURE;
        }

        $this->components->info(
            sprintf(
                'Cloud catalog cache is warm for %d provider(s) and %d region(s).',
                count($providers),
                $warmedRegions,
            ),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<CloudProviderType>
     */
    private function selectedProviders(): array
    {
        $purchasableProviders = $this->providers->purchasableProviders();
        $option = $this->option('provider');

        if ($option === null) {
            return $purchasableProviders;
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

        if (! in_array($provider, $purchasableProviders, true)) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not enabled for purchases.',
                    $provider->value,
                ),
            );
        }

        return [$provider];
    }
}
