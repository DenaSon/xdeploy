<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Application\Cloud\Services\CloudProviderPurchaseReadinessService;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Livewire\Servers\Buy;
use App\Models\User;
use App\Support\Cloud\CloudProviderPublicIdentity;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class BuyProviderReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_default_provider_is_disabled_and_buy_selects_another_ready_provider_on_initial_load(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $health = $this->healthEngine();
        $health->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $this->bindPurchaseReadiness($health);
        $this->bindRegistry();
        $this->actingAs(User::factory()->create());

        $arvanCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Arvan,
        );
        $liaraCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Liara,
        );

        Livewire::test(Buy::class)
            ->assertSet('provider', $liaraCode)
            ->assertSet(
                'providers',
                static function (array $providers) use ($arvanCode, $liaraCode): bool {
                    $indexed = [];

                    foreach ($providers as $provider) {
                        $indexed[$provider['id']] = $provider;
                    }

                    return ($indexed[$arvanCode]['available'] ?? true) === false
                        && ($indexed[$arvanCode]['readiness'] ?? null) === 'blocked_credentials'
                        && ($indexed[$liaraCode]['available'] ?? false) === true;
                },
            )
            ->assertSee('موقتاً غیرفعال');
    }

    public function test_degraded_provider_remains_selectable_with_warning(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $health = $this->healthEngine();
        $health->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Timeout,
        );

        $this->bindPurchaseReadiness($health);
        $this->bindRegistry();
        $this->actingAs(User::factory()->create());

        $arvanCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Arvan,
        );

        Livewire::test(Buy::class)
            ->assertSet('provider', $arvanCode)
            ->assertSet(
                'providers',
                static function (array $providers) use ($arvanCode): bool {
                    foreach ($providers as $provider) {
                        if (($provider['id'] ?? null) !== $arvanCode) {
                            continue;
                        }

                        return ($provider['available'] ?? false) === true
                            && is_string($provider['warning'] ?? null)
                            && $provider['warning'] !== '';
                    }

                    return false;
                },
            )
            ->assertSee('اختلال نسبی');
    }

    public function test_readiness_refresh_preserves_user_selection_when_provider_becomes_blocked(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $health = $this->healthEngine();
        $this->bindPurchaseReadiness($health);
        $this->bindRegistry();
        $this->actingAs(User::factory()->create());

        $arvanCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Arvan,
        );
        $liaraCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Liara,
        );

        $component = Livewire::test(Buy::class)
            ->assertSet('provider', $arvanCode);

        $health->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $component
            ->call('refreshProviderReadiness')
            ->assertSet('provider', $arvanCode)
            ->assertSet(
                'providers',
                static function (array $providers) use ($arvanCode, $liaraCode): bool {
                    $indexed = [];

                    foreach ($providers as $provider) {
                        $indexed[$provider['id']] = $provider;
                    }

                    return ($indexed[$arvanCode]['available'] ?? true) === false
                        && ($indexed[$liaraCode]['available'] ?? false) === true;
                },
            );
    }

    private function bindRegistry(): void
    {
        $provider = Mockery::mock(CloudProviderInterface::class);

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
                registeredProviders: [
                    CloudProviderType::Arvan,
                    CloudProviderType::Liara,
                ],
                purchasableProviders: [
                    CloudProviderType::Arvan,
                    CloudProviderType::Liara,
                ],
            ),
        );
    }

    private function bindPurchaseReadiness(
        CloudProviderHealthEngine $health,
    ): void {
        $this->app->instance(
            CloudProviderHealthEngine::class,
            $health,
        );
        $this->app->instance(
            CloudProviderPurchaseReadinessService::class,
            new CloudProviderPurchaseReadinessService($health),
        );
    }

    private function healthEngine(): CloudProviderHealthEngine
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
        );
    }
}