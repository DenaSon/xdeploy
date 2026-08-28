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

final class BuyProviderPeriodPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parspack_buy_workspace_exposes_weekly_and_monthly_periods_only(): void
    {
        config()->set(
            'cloud.default',
            CloudProviderType::ParsPack->value,
        );

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $cloud,
                registeredProviders: [
                    CloudProviderType::ParsPack,
                ],
                purchasableProviders: [
                    CloudProviderType::ParsPack,
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
                CloudProviderPublicIdentity::code(
                    CloudProviderType::ParsPack,
                ),
            )
            ->assertSet(
                'period',
                '7_days',
            )
            ->assertSet(
                'periods',
                [
                    [
                        'id' => '7_days',
                        'label' => '۷ روزه',
                        'hint' => 'مناسب استفاده هفتگی',
                        'recommended' => true,
                    ],
                    [
                        'id' => '1_month',
                        'label' => '۱ ماهه',
                        'hint' => 'مناسب استفاده ماهانه',
                        'recommended' => false,
                    ],
                ],
            );
    }
}
