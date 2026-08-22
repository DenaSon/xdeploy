<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Services;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use Carbon\CarbonImmutable;
use Closure;
use PHPUnit\Framework\TestCase;

final class CloudProviderHealthEngineTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_success_creates_a_healthy_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-08-22 10:00:00');

        $engine = $this->engine();
        $snapshot = $engine->recordSuccess(
            provider: CloudProviderType::Arvan,
            latencyMs: 420.5,
            operation: 'http.get',
        );

        $this->assertSame(
            CloudProviderHealthStatus::Healthy,
            $snapshot->status,
        );
        $this->assertSame(0, $snapshot->consecutiveAvailabilityFailures);
        $this->assertSame(0, $snapshot->consecutiveSuccesses);
        $this->assertSame(420.5, $snapshot->lastLatencyMs);
        $this->assertSame('http.get', $snapshot->lastOperation);
        $this->assertSame(
            '2026-08-22 10:00:00',
            $snapshot->lastSuccessAt?->format('Y-m-d H:i:s'),
        );
    }

    public function test_transient_failures_degrade_then_make_provider_unavailable(): void
    {
        $engine = $this->engine();

        $first = $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::ProviderServerError,
            httpStatus: 503,
        );
        $second = $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Timeout,
        );
        $third = $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Connection,
        );

        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $first->status,
        );
        $this->assertSame(1, $first->consecutiveAvailabilityFailures);
        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $second->status,
        );
        $this->assertSame(2, $second->consecutiveAvailabilityFailures);
        $this->assertSame(
            CloudProviderHealthStatus::Unavailable,
            $third->status,
        );
        $this->assertSame(3, $third->consecutiveAvailabilityFailures);
    }

    public function test_authentication_failure_does_not_count_as_provider_outage(): void
    {
        $engine = $this->engine();

        $engine->recordSuccess(CloudProviderType::Arvan);
        $snapshot = $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $this->assertSame(
            CloudProviderHealthStatus::Healthy,
            $snapshot->status,
        );
        $this->assertSame(0, $snapshot->consecutiveAvailabilityFailures);
        $this->assertSame(
            CloudProviderHealthFailureCategory::Authentication,
            $snapshot->lastErrorCategory,
        );
        $this->assertSame(401, $snapshot->lastErrorHttpStatus);
    }

    public function test_authentication_failure_without_prior_signal_keeps_status_unknown(): void
    {
        $engine = $this->engine();

        $snapshot = $engine->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $this->assertNull($snapshot->status);
        $this->assertSame(0, $snapshot->consecutiveAvailabilityFailures);
        $this->assertSame(
            CloudProviderHealthFailureCategory::Authentication,
            $snapshot->lastErrorCategory,
        );
    }

    public function test_rate_limit_is_a_degraded_signal_but_not_an_outage_failure(): void
    {
        $engine = $this->engine();

        $engine->recordSuccess(CloudProviderType::Liara);
        $snapshot = $engine->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::RateLimit,
            httpStatus: 429,
        );

        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $snapshot->status,
        );
        $this->assertSame(0, $snapshot->consecutiveAvailabilityFailures);
    }

    public function test_unavailable_provider_requires_two_successes_to_recover(): void
    {
        $engine = $this->engine();

        foreach (range(1, 3) as $_) {
            $engine->recordFailure(
                provider: CloudProviderType::Arvan,
                category: CloudProviderHealthFailureCategory::Timeout,
            );
        }

        $recovering = $engine->recordSuccess(
            CloudProviderType::Arvan,
        );
        $healthy = $engine->recordSuccess(
            CloudProviderType::Arvan,
        );

        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $recovering->status,
        );
        $this->assertSame(1, $recovering->consecutiveSuccesses);
        $this->assertSame(0, $recovering->consecutiveAvailabilityFailures);

        $this->assertSame(
            CloudProviderHealthStatus::Healthy,
            $healthy->status,
        );
        $this->assertSame(0, $healthy->consecutiveSuccesses);
    }

    public function test_unexpected_response_counts_as_availability_failure(): void
    {
        $engine = $this->engine();

        $snapshot = $engine->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::UnexpectedResponse,
        );

        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $snapshot->status,
        );
        $this->assertSame(1, $snapshot->consecutiveAvailabilityFailures);
    }

    private function engine(): CloudProviderHealthEngine
    {
        return new CloudProviderHealthEngine(
            store: $this->store(),
            degradedAfterFailures: 1,
            unavailableAfterFailures: 3,
            recoverySuccesses: 2,
        );
    }

    private function store(): CloudProviderHealthStoreInterface
    {
        return new class implements CloudProviderHealthStoreInterface
        {
            /** @var array<string, CloudProviderHealthSnapshot> */
            private array $snapshots = [];

            public function get(
                CloudProviderType $provider,
            ): ?CloudProviderHealthSnapshot {
                return $this->snapshots[$provider->value] ?? null;
            }

            public function update(
                CloudProviderType $provider,
                Closure $mutator,
            ): CloudProviderHealthSnapshot {
                $next = $mutator($this->get($provider));
                $this->snapshots[$provider->value] = $next;

                return $next;
            }
        };
    }
}
