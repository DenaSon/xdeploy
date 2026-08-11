<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CalculateCloudRenewalPriceAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

final class CalculateCloudRenewalPriceActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 75);
        config()->set('money.periods', [
            '2_days' => [
                'label' => '۲ روزه',
                'hours' => 48,
                'pricing' => 'hourly',
            ],
            '14_days' => [
                'label' => '۱۴ روزه',
                'hours' => 336,
                'pricing' => 'hourly',
            ],
            '1_month' => [
                'label' => '۱ ماهه',
                'hours' => 720,
                'pricing' => 'monthly',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_prices_existing_cloud_server_from_current_provider_catalog(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 20:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer($user);
        $this->provisioningOrder($user, $server);

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(),
            ]);

        $resizeCatalog = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $resizeCatalog->shouldNotReceive(
            'calculateDiskPrice',
        );

        $result = $this->action(
            cloud: $cloud,
            resizeCatalog: $resizeCatalog,
        )->execute(
            user: $user,
            serverId: $server->id,
            period: '14_days',
        );

        $this->assertSame('ir-thr-ba1', $result->regionId);
        $this->assertSame('eco-2-2', $result->sizeId);
        $this->assertSame(30, $result->selectedDiskGiB);
        $this->assertSame(336, $result->durationHours);
        $this->assertSame('336000', $result->providerCost);
        $this->assertSame('588000', $result->finalAmount);
        $this->assertSame('IRR', $result->currency);
    }

    public function test_expired_server_is_rejected_before_provider_pricing_call(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 20:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->subMinute(),
        );
        $this->provisioningOrder($user, $server);

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );
        $cloud->shouldNotReceive('listSizes');

        $resizeCatalog = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
        $resizeCatalog->shouldNotReceive('calculateDiskPrice');

        $this->expectException(
            CloudServerRenewalException::class,
        );

        $this->action(
            cloud: $cloud,
            resizeCatalog: $resizeCatalog,
        )->execute(
            user: $user,
            serverId: $server->id,
            period: '14_days',
        );
    }

    private function action(
        CloudProviderInterface $cloud,
        CloudServerResizeCatalogInterface $resizeCatalog,
    ): CalculateCloudRenewalPriceAction {
        return new CalculateCloudRenewalPriceAction(
            calculatePrice: new CalculateCloudPurchasePriceAction(
                cloud: $cloud,
                pricing: $resizeCatalog,
                calculator: new CloudPricingCalculator,
            ),
        );
    }

    private function cloudServer(
        User $user,
        ?Carbon $expiresAt = null,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'renewable-vps',
            'host' => '203.0.113.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'renewable-vps-20',
            'cloud_region' => 'ir-thr-ba1',
            'provisioned_at' => now()->subDay(),
            'expires_at' => $expiresAt ?? now()->addDays(2),
        ]);
    }

    private function provisioningOrder(
        User $user,
        Server $server,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,
            'type' => OrderType::Provisioning,
            'server_id' => $server->id,
            'region_id' => 'ir-thr-ba1',
            'size_id' => 'eco-2-2',
            'image_id' => 'ubuntu-24',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'Ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '2_days',
            'duration_hours' => 48,
            'provider_cost' => 48_000,
            'markup_percent' => 75,
            'final_amount' => 84_000,
            'currency' => 'IRR',
            'status' => OrderStatus::Fulfilled,
            'quote_expires_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);
    }

    private function cloudSize(): CloudSizeData
    {
        return new CloudSizeData(
            id: 'eco-2-2',
            name: 'Eco 2C / 2GB',
            regionId: 'ir-thr-ba1',
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
            category: 'eco',
            hourlyPrice: new CloudPriceData(
                amount: '1000',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: '720000',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }
}
