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

final class BuyProviderPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_page_renders_unified_public_provider_workspace_and_switch_overlay(): void
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
                    CloudProviderType::Arvan,
                    CloudProviderType::Liara,
                ],
            ),
        );

        $this->actingAs(
            User::factory()->create(),
        );

        $arvanCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Arvan,
        );

        $liaraCode = CloudProviderPublicIdentity::code(
            CloudProviderType::Liara,
        );

        Livewire::test(
            Buy::class,
        )
            ->assertSet(
                'provider',
                $arvanCode,
            )
            ->assertSeeHtml(
                'data-buy-workspace',
            )
            ->assertSeeHtml(
                'data-buy-provider-row',
            )
            ->assertSeeHtml(
                sprintf(
                    'data-provider-option="%s"',
                    $arvanCode,
                ),
            )
            ->assertSeeHtml(
                sprintf(
                    'data-provider-option="%s"',
                    $liaraCode,
                ),
            )
            ->assertSeeHtml(
                'cursor-pointer',
            )
            ->assertSeeHtml(
                'data-provider-switch-overlay',
            )
            ->assertDontSeeHtml(
                'data-buy-desktop-placeholder',
            )
            ->assertSee(
                'خرید VPS',
            )
            ->assertSee(
                'در حال تغییر زیرساخت',
            )
            ->assertSee(
                'Core-1',
            )
            ->assertSee(
                'دماوند',
            )
            ->assertSee(
                'زیرساخت ایران',
            )
            ->assertSee(
                'انتخاب‌شده',
            )
            ->assertDontSee(
                'Core-2',
            )
            ->assertDontSee(
                'ابر آروان',
            )
            ->assertDontSee(
                'لیارا',
            );
    }
}
