<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Livewire\Servers\Buy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class BuyProviderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_page_only_exposes_purchasable_providers(): void
    {
        config()->set(
            'cloud.default',
            CloudProviderType::Arvan->value,
        );

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $cloud,
                registeredProviders: [
                    CloudProviderType::Arvan,
                    CloudProviderType::Liara,
                ],
                purchasableProviders: [
                    CloudProviderType::Liara,
                ],
            ),
        );

        $this->actingAs(
            User::factory()->create(),
        );

        Livewire::test(
            Buy::class,
        )
            ->assertSet(
                'provider',
                CloudProviderType::Liara->value,
            )
            ->assertSet(
                'providers',
                static fn (array $providers): bool => count($providers) === 1
                    && ($providers[0]['id'] ?? null)
                        === CloudProviderType::Liara->value,
            );
    }
}
