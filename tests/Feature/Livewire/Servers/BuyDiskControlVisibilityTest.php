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

final class BuyDiskControlVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_liara_purchase_ui_uses_fixed_disk_layout(): void
    {
        $this->bindProvider(CloudProviderType::Liara);

        $this->actingAs(User::factory()->create());

        Livewire::test(Buy::class)
            ->assertSet('provider', CloudProviderType::Liara->value)
            ->assertSeeHtml('cloud-purchase-page--fixed-disk');
    }

    public function test_arvan_purchase_ui_keeps_custom_disk_layout(): void
    {
        $this->bindProvider(CloudProviderType::Arvan);

        $this->actingAs(User::factory()->create());

        Livewire::test(Buy::class)
            ->assertSet('provider', CloudProviderType::Arvan->value)
            ->assertDontSeeHtml('cloud-purchase-page--fixed-disk');
    }

    private function bindProvider(CloudProviderType $providerType): void
    {
        config()->set('cloud.default', $providerType->value);

        $provider = Mockery::mock(CloudProviderInterface::class);

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
                registeredProviders: [$providerType],
                purchasableProviders: [$providerType],
            ),
        );
    }
}
