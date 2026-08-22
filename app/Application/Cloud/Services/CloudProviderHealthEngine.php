<?php

declare(strict_types=1);

namespace App\Application\Cloud\Services;

use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use Carbon\CarbonImmutable;

final readonly class CloudProviderHealthEngine
{
    public function __construct(
        private CloudProviderHealthStoreInterface $store,
        private int $degradedAfterFailures = 1,
        private int $unavailableAfterFailures = 3,
        private int $recoverySuccesses = 2,
    ) {
        if ($this->degradedAfterFailures < 1) {
            throw new CloudConfigurationException(
                'Cloud provider health degraded threshold must be at least one.',
            );
        }

        if ($this->unavailableAfterFailures < $this->degradedAfterFailures) {
            throw new CloudConfigurationException(
                'Cloud provider health unavailable threshold must not be lower than degraded threshold.',
            );
        }

        if ($this->recoverySuccesses < 1) {
            throw new CloudConfigurationException(
                'Cloud provider health recovery success threshold must be at least one.',
            );
        }
    }

    public function snapshot(
        CloudProviderType $provider,
    ): ?CloudProviderHealthSnapshot {
        return $this->store->get($provider);
    }

    public function recordSuccess(
        CloudProviderType $provider,
        ?float $latencyMs = null,
        ?string $operation = null,
    ): CloudProviderHealthSnapshot {
        return $this->store->update(
            $provider,
            function (?CloudProviderHealthSnapshot $current) use (
                $provider,
                $latencyMs,
                $operation,
            ): CloudProviderHealthSnapshot {
                $now = CarbonImmutable::now();
                $recovering = $current !== null
                    && (
                        $current->status === CloudProviderHealthStatus::Unavailable
                        || $current->consecutiveSuccesses > 0
                    );

                $consecutiveSuccesses = $recovering
                    ? $current->consecutiveSuccesses + 1
                    : 0;

                $status = $recovering
                    && $consecutiveSuccesses < $this->recoverySuccesses
                        ? CloudProviderHealthStatus::Degraded
                        : CloudProviderHealthStatus::Healthy;

                if ($status === CloudProviderHealthStatus::Healthy) {
                    $consecutiveSuccesses = 0;
                }

                return new CloudProviderHealthSnapshot(
                    provider: $provider,
                    status: $status,
                    consecutiveAvailabilityFailures: 0,
                    consecutiveSuccesses: $consecutiveSuccesses,
                    lastSuccessAt: $now,
                    lastFailureAt: $current?->lastFailureAt,
                    lastErrorCategory: $current?->lastErrorCategory,
                    lastErrorHttpStatus: $current?->lastErrorHttpStatus,
                    lastLatencyMs: $latencyMs,
                    lastOperation: $operation,
                    lastObservedAt: $now,
                    statusChangedAt: $this->statusChangedAt(
                        current: $current,
                        next: $status,
                        now: $now,
                    ),
                );
            },
        );
    }

    public function recordFailure(
        CloudProviderType $provider,
        CloudProviderHealthFailureCategory $category,
        ?int $httpStatus = null,
        ?float $latencyMs = null,
        ?string $operation = null,
    ): CloudProviderHealthSnapshot {
        return $this->store->update(
            $provider,
            function (?CloudProviderHealthSnapshot $current) use (
                $provider,
                $category,
                $httpStatus,
                $latencyMs,
                $operation,
            ): CloudProviderHealthSnapshot {
                $now = CarbonImmutable::now();
                $status = $current?->status;
                $availabilityFailures =
                    $current?->consecutiveAvailabilityFailures ?? 0;

                if ($category->isAvailabilityFailure()) {
                    $availabilityFailures++;
                    $status = $this->statusForAvailabilityFailure(
                        current: $status,
                        failures: $availabilityFailures,
                    );
                } elseif ($category->isDegradedSignal()) {
                    if ($status !== CloudProviderHealthStatus::Unavailable) {
                        $status = CloudProviderHealthStatus::Degraded;
                    }
                }

                return new CloudProviderHealthSnapshot(
                    provider: $provider,
                    status: $status,
                    consecutiveAvailabilityFailures: $availabilityFailures,
                    consecutiveSuccesses: 0,
                    lastSuccessAt: $current?->lastSuccessAt,
                    lastFailureAt: $now,
                    lastErrorCategory: $category,
                    lastErrorHttpStatus: $httpStatus,
                    lastLatencyMs: $latencyMs,
                    lastOperation: $operation,
                    lastObservedAt: $now,
                    statusChangedAt: $this->statusChangedAt(
                        current: $current,
                        next: $status,
                        now: $now,
                    ),
                );
            },
        );
    }

    private function statusForAvailabilityFailure(
        ?CloudProviderHealthStatus $current,
        int $failures,
    ): ?CloudProviderHealthStatus {
        if (
            $current === CloudProviderHealthStatus::Unavailable
            || $failures >= $this->unavailableAfterFailures
        ) {
            return CloudProviderHealthStatus::Unavailable;
        }

        if ($failures >= $this->degradedAfterFailures) {
            return CloudProviderHealthStatus::Degraded;
        }

        return $current;
    }

    private function statusChangedAt(
        ?CloudProviderHealthSnapshot $current,
        ?CloudProviderHealthStatus $next,
        CarbonImmutable $now,
    ): ?CarbonImmutable {
        if ($next === null) {
            return $current?->statusChangedAt;
        }

        if ($current?->status !== $next) {
            return $now;
        }

        return $current->statusChangedAt ?? $now;
    }
}
