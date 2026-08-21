<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Livewire\Servers\Buy;
use App\Models\User;
use App\Support\Cloud\CloudProviderPublicIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class BuyDiskControlVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_liara_purchase_ui_renders_fixed_disk_without_resize_controls(): void
    {
        $this->bindProvider(CloudProviderType::Liara);

        $this->actingAs(User::factory()->create());

        Livewire::test(Buy::class)
            ->assertSet(
                'provider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Liara,
                ),
            )
            ->set('catalogLoaded', true)
            ->assertDontSeeHtml('wire:click="decreaseDisk"')
            ->assertDontSeeHtml('wire:click="increaseDisk"');
    }

    public function test_arvan_purchase_ui_renders_custom_disk_resize_controls(): void
    {
        $this->bindProvider(CloudProviderType::Arvan);

        $this->actingAs(User::factory()->create());

        Livewire::test(Buy::class)
            ->assertSet(
                'provider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Arvan,
                ),
            )
            ->set('catalogLoaded', true)
            ->assertSeeHtml('wire:click="decreaseDisk"')
            ->assertSeeHtml('wire:click="increaseDisk"');
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
