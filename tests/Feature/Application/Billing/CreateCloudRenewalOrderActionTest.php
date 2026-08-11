<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateCloudRenewalOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CreateCloudRenewalOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private const string REGION = 'eu-west1-a';

    private const string SIZE_ID = 'eco-2-2-0';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 75);
        config()->set('money.quote_ttl_minutes', 15);
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

    public function test_it_creates_renewal_order_from_purchase_snapshot_with_current_price(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $user = User::factory()->create();

        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-12 12:00:00'),
        );

        $purchase = $this->purchaseOrder(
            user: $user,
            server: $server,
        );

        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->plan(),
            ]);

        $diskPricing->shouldNotReceive(
            'calculateDiskPrice',
        );

        $renewal = $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $user,
            serverId: $server->getKey(),
            period: '2_days',
        );

        $this->assertSame(
            OrderType::CloudRenewal,
            $renewal->type,
        );

        $this->assertSame(
            $server->getKey(),
            $renewal->server_id,
        );

        $this->assertSame(
            $purchase->image_id,
            $renewal->image_id,
        );

        $this->assertSame(
            self::REGION,
            $renewal->region_id,
        );

        $this->assertSame(
            self::SIZE_ID,
            $renewal->size_id,
        );

        $this->assertSame(
            '2_days',
            $renewal->period,
        );

        $this->assertSame(
            48,
            $renewal->duration_hours,
        );

        $this->assertSame(
            48_000,
            $renewal->provider_cost,
        );

        $this->assertSame(
            75,
            $renewal->markup_percent,
        );

        $this->assertSame(
            84_000,
            $renewal->final_amount,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $renewal->status,
        );

        $this->assertSame(
            '2026-08-11 12:15:00',
            $renewal->quote_expires_at?->format('Y-m-d H:i:s'),
        );

        /*
         * The schema must allow the original purchase and later renewal
         * Orders to correlate with the same Server.
         */
        $this->assertSame(
            2,
            Order::query()
                ->where('server_id', $server->getKey())
                ->count(),
        );
    }

    public function test_renewal_quote_never_outlives_server_expiration(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $user = User::factory()->create();

        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-11 12:10:00'),
        );

        $this->purchaseOrder(
            user: $user,
            server: $server,
        );

        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->andReturn([
                $this->plan(),
            ]);

        $diskPricing->shouldNotReceive(
            'calculateDiskPrice',
        );

        $renewal = $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $user,
            serverId: $server->getKey(),
            period: '2_days',
        );

        $this->assertSame(
            '2026-08-11 12:10:00',
            $renewal->quote_expires_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_manual_server_is_not_renewable(): void
    {
        $user = User::factory()->create();

        $server = $user
            ->servers()
            ->create([
                'name' => 'Manual VPS',
                'host' => '192.0.2.10',
                'port' => 22,
                'username' => 'root',
                'status' => ServerStatus::Active,
                'expires_at' => now()->addDay(),
            ]);

        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud->shouldNotReceive('listSizes');
        $diskPricing->shouldNotReceive('calculateDiskPrice');

        $this->expectException(
            CloudServerRenewalException::class,
        );

        $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $user,
            serverId: $server->getKey(),
            period: '2_days',
        );
    }

    public function test_expired_or_terminating_cloud_server_is_not_renewable(): void
    {
        $user = User::factory()->create();

        $expired = $this->cloudServer(
            user: $user,
            expiresAt: now()->subMinute(),
        );

        $terminating = $this->cloudServer(
            user: $user,
            expiresAt: now()->addHour(),
        );

        $terminating->forceFill([
            'termination_started_at' => now(),
        ])->saveOrFail();

        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud->shouldNotReceive('listSizes');
        $diskPricing->shouldNotReceive('calculateDiskPrice');

        foreach ([$expired, $terminating] as $server) {
            try {
                $this->action(
                    cloud: $cloud,
                    diskPricing: $diskPricing,
                )->execute(
                    user: $user,
                    serverId: $server->getKey(),
                    period: '2_days',
                );

                $this->fail(
                    'Expected CloudServerRenewalException was not thrown.',
                );
            } catch (CloudServerRenewalException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount(
            'orders',
            0,
        );
    }

    public function test_other_user_cannot_create_renewal_for_server(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $server = $this->cloudServer(
            user: $owner,
            expiresAt: now()->addDay(),
        );

        $this->purchaseOrder(
            user: $owner,
            server: $server,
        );

        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud->shouldNotReceive('listSizes');
        $diskPricing->shouldNotReceive('calculateDiskPrice');

        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $other,
            serverId: $server->getKey(),
            period: '2_days',
        );
    }

    private function action(
        CloudProviderInterface $cloud,
        CloudServerResizeCatalogInterface $diskPricing,
    ): CreateCloudRenewalOrderAction {
        return new CreateCloudRenewalOrderAction(
            calculatePrice: new CalculateCloudPurchasePriceAction(
                cloud: $cloud,
                pricing: $diskPricing,
                calculator: new CloudPricingCalculator,
            ),
        );
    }

    /**
     * @return CloudProviderInterface&MockInterface
     */
    private function cloud(): CloudProviderInterface
    {
        return Mockery::mock(
            CloudProviderInterface::class,
        );
    }

    /**
     * @return CloudServerResizeCatalogInterface&MockInterface
     */
    private function diskPricing(): CloudServerResizeCatalogInterface
    {
        return Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
    }

    private function plan(): CloudSizeData
    {
        return new CloudSizeData(
            id: self::SIZE_ID,
            name: 'eco-small',
            regionId: self::REGION,
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
            category: 'economic',
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

    private function cloudServer(
        User $user,
        mixed $expiresAt,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => 'Renewable Cloud VPS',
                'host' => '203.0.113.50',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-server-'.uniqid(),
                'cloud_region' => self::REGION,
                'provisioned_at' => now()->subDay(),
                'expires_at' => $expiresAt,
            ]);
    }

    private function purchaseOrder(
        User $user,
        Server $server,
    ): Order {
        return Order::query()->create([
            'type' => OrderType::CloudPurchase,
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'region_id' => self::REGION,
            'size_id' => self::SIZE_ID,
            'image_id' => 'ubuntu-24-04-image',
            'image_name' => 'Ubuntu 24.04 LTS',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '2_days',
            'duration_hours' => 48,
            'provider_cost' => 40_000,
            'markup_percent' => 60,
            'final_amount' => 64_000,
            'currency' => 'IRR',
            'status' => OrderStatus::Fulfilled,
            'quote_expires_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);
    }
}
