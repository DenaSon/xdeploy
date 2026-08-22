<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Cloud\Actions\ResolveCloudImageForOrderAction;
use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Application\Cloud\Services\CloudProviderPurchaseReadinessService;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudProviderPurchaseUnavailableException;
use App\Models\User;
use Closure;
use Mockery;
use ReflectionClass;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class CreateOrderPurchaseReadinessTest extends TestCase
{
    public function test_runtime_purchase_readiness_is_checked_before_provider_catalog_or_order_work(): void
    {
        $provider = Mockery::mock(CloudProviderInterface::class);
        $provider->shouldNotReceive('listSizes');
        $provider->shouldNotReceive('listImages');

        $health = new CloudProviderHealthEngine(
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
        );

        $health->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $action = new CreateOrderAction(
            calculatePrice: (new ReflectionClass(
                CalculateCloudPurchasePriceAction::class,
            ))->newInstanceWithoutConstructor(),
            resolveImage: (new ReflectionClass(
                ResolveCloudImageForOrderAction::class,
            ))->newInstanceWithoutConstructor(),
            providers: new CloudProviderRegistryStub(
                provider: $provider,
                registeredProviders: [
                    CloudProviderType::Arvan,
                ],
                purchasableProviders: [
                    CloudProviderType::Arvan,
                ],
            ),
            purchaseReadiness: new CloudProviderPurchaseReadinessService(
                $health,
            ),
        );

        try {
            $action->execute(
                user: new User,
                region: 'ir-thr-ba1',
                sizeId: 'eco-1-1-0',
                imageId: 'ubuntu-24',
                selectedDiskGiB: 25,
                period: '2_days',
                provider: CloudProviderType::Arvan,
            );

            $this->fail('Expected blocked provider purchase to be rejected.');
        } catch (CloudProviderPurchaseUnavailableException $exception) {
            $this->assertStringContainsString(
                'blocked_credentials',
                $exception->getMessage(),
            );
        }
    }
}
