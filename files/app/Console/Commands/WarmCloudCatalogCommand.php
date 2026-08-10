<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Infrastructure\Cloud\Catalog\CachedCloudCatalogReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature(
    'cloud:catalog:warm
        {--force : Forget current catalog cache entries and fetch them again}'
)]
#[Description(
    'Warm the cached cloud catalog used by the VPS purchase page'
)]
final class WarmCloudCatalogCommand extends Command
{
    public function handle(
        CachedCloudCatalogReader $catalog,
    ): int {
        $force = (bool) $this->option(
            'force',
        );

        try {
            $regions = $force
                ? $catalog->refreshRegions()
                : $catalog->listRegions();
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->components->error(
                'Cloud regions could not be loaded.',
            );

            return self::FAILURE;
        }

        $regions = array_values(
            array_filter(
                $regions,
                static fn (
                    CloudRegionData $region,
                ): bool => $region->canCreateServers
                    && $region->isVisible,
            ),
        );

        if ($regions === []) {
            $this->components->warn(
                'No purchasable cloud regions are available.',
            );

            return self::SUCCESS;
        }

        $failures = [];

        foreach ($regions as $region) {
            try {
                if ($force) {
                    $catalog->refreshRegion(
                        $region->id,
                    );
                } else {
                    $catalog->warmRegion(
                        $region->id,
                    );
                }

                $this->components->info(
                    sprintf(
                        'Warmed %s',
                        $region->id,
                    ),
                );
            } catch (Throwable $exception) {
                report(
                    $exception,
                );

                $failures[] = $region->id;

                $this->components->warn(
                    sprintf(
                        'Failed to warm %s',
                        $region->id,
                    ),
                );
            }
        }

        if ($failures !== []) {
            $this->components->error(
                sprintf(
                    'Cloud catalog warm-up completed with %d failure(s).',
                    count(
                        $failures,
                    ),
                ),
            );

            return self::FAILURE;
        }

        $this->components->info(
            sprintf(
                'Cloud catalog cache is warm for %d region(s).',
                count(
                    $regions,
                ),
            ),
        );

        return self::SUCCESS;
    }
}
