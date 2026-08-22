<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Infrastructure\Cloud\Health\CacheCloudProviderHealthStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CloudHealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CloudProviderHealthStoreInterface::class,
            static fn (): CacheCloudProviderHealthStore =>
                new CacheCloudProviderHealthStore(
                    ttlSeconds: (int) config(
                        'cloud_health.state_ttl_seconds',
                        1_800,
                    ),
                    lockSeconds: (int) config(
                        'cloud_health.lock_seconds',
                        5,
                    ),
                    lockWaitSeconds: (int) config(
                        'cloud_health.lock_wait_seconds',
                        1,
                    ),
                ),
        );

        $this->app->singleton(
            CloudProviderHealthEngine::class,
            static function (Application $app): CloudProviderHealthEngine {
                return new CloudProviderHealthEngine(
                    store: $app->make(
                        CloudProviderHealthStoreInterface::class,
                    ),
                    degradedAfterFailures: (int) config(
                        'cloud_health.thresholds.degraded_after_failures',
                        1,
                    ),
                    unavailableAfterFailures: (int) config(
                        'cloud_health.thresholds.unavailable_after_failures',
                        3,
                    ),
                    recoverySuccesses: (int) config(
                        'cloud_health.thresholds.recovery_successes',
                        2,
                    ),
                );
            },
        );
    }
}
