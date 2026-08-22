<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Services;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Application\Cloud\Services\CloudProviderPurchaseReadinessService;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderPurchaseReadinessStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use Closure;
use PHPUnit\Framework\TestCase;

final class CloudProviderPurchaseReadinessServiceTest extends TestCase
{
    public function test_unknown_health_is_optimistically_ready(): void
    {
        $readiness = $this->service($this->engine())->evaluate(
            CloudProviderType::Arvan,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::Ready,
            $readiness->status,
        );
        $this->assertNull($readiness->healthStatus);
        $this->assertTrue($readiness->allowsPurchase());
    }

    public function test_authentication_failure_blocks_new_purchases_without_marking_provider_down(): void
    {
        $engine = $this->engine();
        $engine->recordSuccess(CloudProviderType::Arvan);
        $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $readiness = $this->service($engine)->evaluate(
            CloudProviderType::Arvan,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::BlockedCredentials,
            $readiness->status,
        );
        $this->assertSame(
            CloudProviderHealthStatus::Healthy,
            $readiness->healthStatus,
        );
        $this->assertFalse($readiness->allowsPurchase());
    }

    public function test_success_after_authentication_failure_restores_readiness(): void
    {
        $engine = $this->engine();
        $engine->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );
        $engine->recordSuccess(CloudProviderType::Liara);

        $readiness = $this->service($engine)->evaluate(
            CloudProviderType::Liara,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::Ready,
            $readiness->status,
        );
        $this->assertTrue($readiness->allowsPurchase());
    }

    public function test_configuration_and_balance_failures_have_distinct_blocking_states(): void
    {
        $configurationEngine = $this->engine();
        $configurationEngine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Configuration,
        );

        $balanceEngine = $this->engine();
        $balanceEngine->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::InsufficientBalance,
            httpStatus: 402,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::BlockedConfiguration,
            $this->service($configurationEngine)
                ->evaluate(CloudProviderType::Arvan)
                ->status,
        );
        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::BlockedBalance,
            $this->service($balanceEngine)
                ->evaluate(CloudProviderType::Liara)
                ->status,
        );
    }

    public function test_degraded_provider_remains_purchasable_but_unavailable_provider_is_blocked(): void
    {
        $engine = $this->engine();
        $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Timeout,
        );

        $degraded = $this->service($engine)->evaluate(
            CloudProviderType::Arvan,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::Ready,
            $degraded->status,
        );
        $this->assertTrue($degraded->isDegraded());
        $this->assertTrue($degraded->allowsPurchase());

        $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Timeout,
        );
        $engine->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Connection,
        );

        $unavailable = $this->service($engine)->evaluate(
            CloudProviderType::Arvan,
        );

        $this->assertSame(
            CloudProviderPurchaseReadinessStatus::TemporarilyUnavailable,
            $unavailable->status,
        );
        $this->assertFalse($unavailable->allowsPurchase());
    }

    private function service(
        CloudProviderHealthEngine $engine,
    ): CloudProviderPurchaseReadinessService {
        return new CloudProviderPurchaseReadinessService($engine);
    }

    private function engine(): CloudProviderHealthEngine
    {
        return new CloudProviderHealthEngine(
            store: new class implements CloudProviderHealthStoreInterface
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
            },
            degradedAfterFailures: 1,
            unavailableAfterFailures: 3,
            recoverySuccesses: 2,
        );
    }
}
