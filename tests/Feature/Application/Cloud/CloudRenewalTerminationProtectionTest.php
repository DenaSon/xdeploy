<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Application\Cloud\Servers\TerminateExpiredCloudServerAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class CloudRenewalTerminationProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'money.renewal_payment_protection_minutes',
            30,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_recent_pending_renewal_payment_blocks_termination(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $server = $this->cloudServer();
        $order = $this->renewalOrder(
            server: $server,
            status: OrderStatus::PendingPayment,
        );

        $payment = $this->payment(
            order: $order,
            status: PaymentStatus::Pending,
            createdAt: now()->subMinutes(5),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        $this->assertFalse(
            $this->action($lifecycle)->execute(
                $server->getKey(),
            ),
        );

        $this->assertModelExists(
            $server,
        );

        $this->assertNull(
            $server->fresh()?->termination_started_at,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->fresh()->status,
        );
    }

    public function test_stale_pending_renewal_is_cancelled_then_termination_proceeds(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $server = $this->cloudServer();
        $order = $this->renewalOrder(
            server: $server,
            status: OrderStatus::PendingPayment,
        );

        $payment = $this->payment(
            order: $order,
            status: PaymentStatus::Pending,
            createdAt: now()->subMinutes(31),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->with(
                'eu-west1-a',
                'cloud-server-123',
            );

        $this->assertTrue(
            $this->action($lifecycle)->execute(
                $server->getKey(),
            ),
        );

        $this->assertSoftDeleted(
            'servers',
            [
                'id' => $server->getKey(),
            ],
        );

        $this->assertSame(
            OrderStatus::Expired,
            $order->fresh()->status,
        );

        $this->assertSame(
            PaymentStatus::Cancelled,
            $payment->fresh()->status,
        );

        $this->assertSame(
            'renewal_payment_window_expired',
            $payment->fresh()->failure_code,
        );
    }

    public function test_paid_renewal_order_blocks_termination_until_fulfillment(): void
    {
        $server = $this->cloudServer();

        $order = $this->renewalOrder(
            server: $server,
            status: OrderStatus::Paid,
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        $this->assertFalse(
            $this->action($lifecycle)->execute(
                $server->getKey(),
            ),
        );

        $this->assertSame(
            OrderStatus::Paid,
            $order->fresh()->status,
        );

        $this->assertModelExists(
            $server,
        );
    }

    public function test_unpaid_renewal_quote_is_expired_before_server_termination(): void
    {
        $server = $this->cloudServer();

        $order = $this->renewalOrder(
            server: $server,
            status: OrderStatus::PendingPayment,
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer');

        $this->assertTrue(
            $this->action($lifecycle)->execute(
                $server->getKey(),
            ),
        );

        $this->assertSame(
            OrderStatus::Expired,
            $order->fresh()->status,
        );
    }

    private function action(
        CloudServerLifecycleInterface $lifecycle,
    ): TerminateExpiredCloudServerAction {
        return new TerminateExpiredCloudServerAction(
            deleteCloudServer: new DeleteCloudServerAction(
                lifecycle: $lifecycle,
            ),
        );
    }

    private function mockLifecycle(): CloudServerLifecycleInterface&MockObject
    {
        return $this->createMock(
            CloudServerLifecycleInterface::class,
        );
    }

    private function cloudServer(): Server
    {
        $user = User::factory()->create();

        return $user
            ->servers()
            ->create([
                'name' => 'Expired Cloud Server',
                'host' => '203.0.113.80',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-server-123',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDay(),
                'expires_at' => now()->subMinute(),
            ]);
    }

    private function renewalOrder(
        Server $server,
        OrderStatus $status,
    ): Order {
        return Order::query()->create([
            'type' => OrderType::CloudRenewal,
            'user_id' => $server->user_id,
            'server_id' => $server->getKey(),
            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',
            'image_id' => 'ubuntu-24-04-image',
            'image_name' => 'Ubuntu 24.04 LTS',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '2_days',
            'duration_hours' => 48,
            'provider_cost' => 48_000,
            'markup_percent' => 75,
            'final_amount' => 84_000,
            'currency' => 'IRR',
            'status' => $status,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => $status === OrderStatus::Paid
                ? now()
                : null,
        ]);
    }

    private function payment(
        Order $order,
        PaymentStatus $status,
        Carbon $createdAt,
    ): Payment {
        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'gateway' => 'fake',
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => $status,
            'gateway_reference' => 'REF-RENEW',
            'gateway_transaction_id' => null,
            'redirect_url' => 'https://gateway.test/pay/REF-RENEW',
            'failure_code' => null,
            'verified_at' => null,
        ]);

        $payment->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveOrFail();

        return $payment->fresh();
    }
}
