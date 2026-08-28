<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
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

    public function test_purchase_ui_hides_custom_disk_controls_without_capability(): void
    {
        $this->bindProvider(
            providerType: CloudProviderType::Liara,
            customDisk: false,
        );

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

    public function test_purchase_ui_uses_provider_capability_for_custom_disk_controls(): void
    {
        $this->bindProvider(
            providerType: CloudProviderType::Liara,
            customDisk: true,
        );

        $this->actingAs(User::factory()->create());

        Livewire::test(Buy::class)
            ->assertSet(
                'provider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Liara,
                ),
            )
            ->set('catalogLoaded', true)
            ->assertSeeHtml('wire:click="decreaseDisk"')
            ->assertSeeHtml('wire:click="increaseDisk"');
    }

    private function bindProvider(
        CloudProviderType $providerType,
        bool $customDisk,
    ): void {
        config()->set('cloud.default', $providerType->value);

        $provider = Mockery::mock(CloudProviderInterface::class);
        $capabilities = [];

        if ($customDisk) {
            $capabilities[CloudServerResizerInterface::class] = Mockery::mock(
                CloudServerResizerInterface::class,
            );
        }

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
                capabilities: $capabilities,
                registeredProviders: [$providerType],
                purchasableProviders: [$providerType],
            ),
        );
    }
}
