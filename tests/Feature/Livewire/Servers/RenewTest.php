<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentInitiationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Servers\Renew;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class RenewTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_guest_cannot_open_renewal_page(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);

        $this->get(
            route('panel.servers.renew', $server),
        )->assertRedirect(
            route('login'),
        );
    }

    public function test_user_cannot_open_another_users_renewal_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $server = $this->cloudServer($owner);

        $this->actingAs($other)
            ->get(
                route('panel.servers.renew', $server),
            )
            ->assertNotFound();
    }

    public function test_quote_loading_is_deferred_until_livewire_init(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);
        $this->provisioningOrder($user, $server);

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );
        $cloud->shouldNotReceive('listSizes');

        $this->app->instance(
            CloudProviderInterface::class,
            $cloud,
        );

        $this->actingAs($user);

        Livewire::test(
            Renew::class,
            ['server' => $server],
        )
            ->assertSet('quoteLoaded', false)
            ->assertSet('period', '14_days')
            ->assertSee('تمدید سرویس')
            ->assertSee('دوره تمدید');
    }

    public function test_owner_can_load_authoritative_renewal_quote(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 20:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer($user);
        $this->provisioningOrder($user, $server);

        $this->bindPricing(
            expectedCalls: 1,
        );

        $this->actingAs($user);

        Livewire::test(
            Renew::class,
            ['server' => $server],
        )
            ->call('loadQuote')
            ->assertSet('quoteLoaded', true)
            ->assertSet('quote.duration_hours', 336)
            ->assertSet('quote.final_amount', 588_000)
            ->assertSee('تومان')
            ->assertSee('پایان جدید');
    }

    public function test_renew_action_creates_renewal_order_and_redirects_to_gateway(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 20:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer($user);
        $this->provisioningOrder($user, $server);

        /*
         * One pricing call renders the preview and a second authoritative call
         * happens when CreateRenewalOrderAction creates the billable Order.
         */
        $this->bindPricing(
            expectedCalls: 2,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('zarinpal');

        $gateway
            ->shouldReceive('initiate')
            ->once()
            ->andReturn(
                new PaymentInitiationData(
                    reference: 'AUTH-RENEW-UI',
                    redirectUrl: 'https://gateway.test/renew',
                ),
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $this->actingAs($user);

        Livewire::test(
            Renew::class,
            ['server' => $server],
        )
            ->call('loadQuote')
            ->call('renew')
            ->assertRedirect('https://gateway.test/renew');

        $renewalOrder = Order::query()
            ->where('server_id', $server->id)
            ->where('type', OrderType::Renewal)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            OrderStatus::PendingPayment,
            $renewalOrder->status,
        );
        $this->assertSame('14_days', $renewalOrder->period);
        $this->assertSame(336, $renewalOrder->duration_hours);
        $this->assertSame(588_000, $renewalOrder->final_amount);

        $this->assertDatabaseHas('payments', [
            'order_id' => $renewalOrder->id,
            'gateway' => 'zarinpal',
            'status' => PaymentStatus::Pending->value,
            'gateway_reference' => 'AUTH-RENEW-UI',
            'redirect_url' => 'https://gateway.test/renew',
        ]);
    }

    private function bindPricing(
        int $expectedCalls,
    ): void {
        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->times($expectedCalls)
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(),
            ]);

        $this->app->instance(
            CloudProviderInterface::class,
            $cloud,
        );

        $resizeCatalog = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $resizeCatalog->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->app->instance(
            CloudServerResizeCatalogInterface::class,
            $resizeCatalog,
        );
    }

    private function cloudServer(
        User $user,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'renewable-vps',
            'host' => '203.0.113.30',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'renewable-vps-30',
            'cloud_region' => 'ir-thr-ba1',
            'provisioned_at' => now()->subDay(),
            'expires_at' => now()->addDays(2),
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
