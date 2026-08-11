<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateRenewalOrderAction;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CreateRenewalOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private const string REGION = 'eu-west1-a';

    private const string SIZE_ID = 'eco-2-2-0';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 60);
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

    public function test_it_creates_authoritative_renewal_quote_for_owned_cloud_server(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->addHours(20),
        );
        $sourceOrder = $this->provisioningOrder(
            user: $user,
            server: $server,
        );

        $cloud = $this->cloud();
        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $diskPricing = $this->diskPricing();
        $diskPricing
            ->shouldNotReceive('calculateDiskPrice');

        $order = $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
        );

        $this->assertSame(
            OrderType::Renewal,
            $order->type,
        );
        $this->assertSame(
            $server->id,
            $order->server_id,
        );
        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->status,
        );
        $this->assertSame(
            self::REGION,
            $order->region_id,
        );
        $this->assertSame(
            self::SIZE_ID,
            $order->size_id,
        );
        $this->assertSame(
            $sourceOrder->image_id,
            $order->image_id,
        );
        $this->assertSame(
            30,
            $order->selected_disk_gib,
        );
        $this->assertSame(
            48,
            $order->duration_hours,
        );
        $this->assertSame(
            1_113_600,
            $order->provider_cost,
        );
        $this->assertSame(
            1_781_760,
            $order->final_amount,
        );
        $this->assertSame(
            '2026-08-11 18:15:00',
            $order->quote_expires_at?->format('Y-m-d H:i:s'),
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'server_id' => $server->id,
            'type' => OrderType::Renewal->value,
            'status' => OrderStatus::PendingPayment->value,
            'duration_hours' => 48,
            'final_amount' => 1_781_760,
        ]);
    }

    public function test_multiple_historical_orders_can_reference_same_server(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->addDay(),
        );
        $this->provisioningOrder(
            user: $user,
            server: $server,
        );

        $cloud = $this->cloud();
        $cloud
            ->shouldReceive('listSizes')
            ->twice()
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $diskPricing = $this->diskPricing();
        $diskPricing
            ->shouldNotReceive('calculateDiskPrice');

        $action = $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        );

        $first = $action->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
        );
        $second = $action->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
        );

        $this->assertNotSame(
            $first->id,
            $second->id,
        );
        $this->assertSame(
            3,
            Order::query()
                ->where('server_id', $server->id)
                ->count(),
        );
    }

    public function test_it_rejects_expired_server_before_provider_pricing(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->subSecond(),
        );
        $this->provisioningOrder(
            user: $user,
            server: $server,
        );

        $cloud = $this->cloud();
        $cloud->shouldNotReceive('listSizes');

        $this->expectException(
            CloudServerRenewalException::class,
        );

        $this->action(
            cloud: $cloud,
            diskPricing: $this->diskPricing(),
        )->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
        );
    }

    public function test_it_rejects_user_provided_server(): void
    {
        $user = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'manual-vps',
            'host' => '203.0.113.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
        ]);

        $cloud = $this->cloud();
        $cloud->shouldNotReceive('listSizes');

        $this->expectException(
            CloudServerRenewalException::class,
        );

        $this->action(
            cloud: $cloud,
            diskPricing: $this->diskPricing(),
        )->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
        );
    }

    public function test_it_never_renews_another_users_server(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = $this->cloudServer(
            user: $owner,
            expiresAt: now()->addDay(),
        );
        $this->provisioningOrder(
            user: $owner,
            server: $server,
        );

        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->action(
            cloud: $this->cloud(),
            diskPricing: $this->diskPricing(),
        )->execute(
            user: $otherUser,
            serverId: (int) $server->id,
            period: '2_days',
        );
    }

    public function test_it_requires_fulfilled_provisioning_order_snapshot(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->addDay(),
        );

        $this->expectException(
            CloudServerRenewalException::class,
        );

        $this->action(
            cloud: $this->cloud(),
            diskPricing: $this->diskPricing(),
        )->execute(
            user: $user,
            serverId: (int) $server->id,
            period: '2_days',
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

    private function action(
        CloudProviderInterface $cloud,
        CloudServerResizeCatalogInterface $diskPricing,
    ): CreateRenewalOrderAction {
        return new CreateRenewalOrderAction(
            calculatePrice: new CalculateCloudPurchasePriceAction(
                cloud: $cloud,
                pricing: $diskPricing,
                calculator: new CloudPricingCalculator,
            ),
        );
    }

    private function cloudServer(
        User $user,
        \DateTimeInterface $expiresAt,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'cloud-vps',
            'host' => '203.0.113.10',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'cloud-'.uniqid(),
            'cloud_region' => self::REGION,
            'provisioned_at' => now()->subHours(10),
            'expires_at' => $expiresAt,
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
            'provider_cost' => 1_113_600,
            'markup_percent' => 60,
            'final_amount' => 1_781_760,
            'currency' => 'IRR',
            'status' => OrderStatus::Fulfilled,
            'quote_expires_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);
    }

    private function ecoPlan(): CloudSizeData
    {
        return new CloudSizeData(
            id: self::SIZE_ID,
            name: 'eco-small4',
            regionId: self::REGION,
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
            category: 'economic',
            hourlyPrice: new CloudPriceData(
                amount: '23200',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: '16704000',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }
}
