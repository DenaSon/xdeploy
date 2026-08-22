<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Health;

use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use Closure;
use Illuminate\Support\Facades\Cache;

final readonly class CacheCloudProviderHealthStore implements CloudProviderHealthStoreInterface
{
    private const string KEY_PREFIX = 'cloud:provider-health:';

    public function __construct(
        private int $ttlSeconds = 1_800,
        private int $lockSeconds = 5,
        private int $lockWaitSeconds = 1,
    ) {
        if ($this->ttlSeconds < 1) {
            throw new CloudConfigurationException(
                'Cloud provider health state TTL must be greater than zero.',
            );
        }

        if ($this->lockSeconds < 1) {
            throw new CloudConfigurationException(
                'Cloud provider health lock TTL must be greater than zero.',
            );
        }

        if ($this->lockWaitSeconds < 0) {
            throw new CloudConfigurationException(
                'Cloud provider health lock wait must not be negative.',
            );
        }
    }

    public function get(
        CloudProviderType $provider,
    ): ?CloudProviderHealthSnapshot {
        $value = Cache::get($this->key($provider));

        if ($value instanceof CloudProviderHealthSnapshot) {
            return $value;
        }

        if ($value !== null) {
            Cache::forget($this->key($provider));
        }

        return null;
    }

    public function update(
        CloudProviderType $provider,
        Closure $mutator,
    ): CloudProviderHealthSnapshot {
        $lock = Cache::lock(
            $this->lockKey($provider),
            $this->lockSeconds,
        );

        return $lock->block(
            $this->lockWaitSeconds,
            function () use ($provider, $mutator): CloudProviderHealthSnapshot {
                $next = $mutator($this->get($provider));

                if (! $next instanceof CloudProviderHealthSnapshot) {
                    throw new CloudConfigurationException(
                        'Cloud provider health store mutator returned an invalid snapshot.',
                    );
                }

                if ($next->provider !== $provider) {
                    throw new CloudConfigurationException(
                        'Cloud provider health snapshot provider does not match the store key.',
                    );
                }

                Cache::put(
                    $this->key($provider),
                    $next,
                    $this->ttlSeconds,
                );

                return $next;
            },
        );
    }

    private function key(CloudProviderType $provider): string
    {
        return self::KEY_PREFIX.$provider->value;
    }

    private function lockKey(CloudProviderType $provider): string
    {
        return $this->key($provider).':lock';
    }
}
