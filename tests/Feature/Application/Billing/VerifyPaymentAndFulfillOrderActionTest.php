<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\FulfillPaidRenewalOrderAction;
use App\Application\Billing\Actions\VerifyPaymentAction;
use App\Application\Billing\Actions\VerifyPaymentAndFulfillOrderAction;
use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class VerifyPaymentAndFulfillOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_payment_keeps_existing_async_fulfillment_path(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = $this->order(
            user: $user,
            type: OrderType::Provisioning,
            server: null,
        );
        $this->payment(
            order: $order,
            reference: 'REF-PROVISION',
        );

        $action = $this->action(
            reference: 'REF-PROVISION',
        );

        $action->execute(
            gatewayReference: 'REF-PROVISION',
        );

        $this->assertSame(
            OrderStatus::Paid,
            $order->fresh()->status,
        );

        Queue::assertPushed(
            ProvisionPaidOrderJob::class,
            static fn (ProvisionPaidOrderJob $job): bool => $job->orderId === $order->id,
        );
    }

    public function test_renewal_payment_is_fulfilled_immediately_without_provisioning_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = $this->server(
            $user,
        );
        $originalExpiry = $server->expires_at;
        $order = $this->order(
            user: $user,
            type: OrderType::Renewal,
            server: $server,
        );
        $this->payment(
            order: $order,
            reference: 'REF-RENEW',
        );

        $this->action(
            reference: 'REF-RENEW',
        )->execute(
            gatewayReference: 'REF-RENEW',
        );

        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
        $this->assertSame(
            $originalExpiry?->addHours(48)->format('Y-m-d H:i:s'),
            $server->fresh()->expires_at?->format('Y-m-d H:i:s'),
        );

        Queue::assertNotPushed(
            ProvisionPaidOrderJob::class,
        );
    }

    public function test_repeated_paid_callback_does_not_extend_renewal_twice(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = $this->server(
            $user,
        );
        $order = $this->order(
            user: $user,
            type: OrderType::Renewal,
            server: $server,
        );
        $this->payment(
            order: $order,
            reference: 'REF-IDEMPOTENT-RENEW',
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );
        $gateway
            ->shouldReceive('name')
            ->twice()
            ->andReturn('fake');
        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                $this->verification(
                    'REF-IDEMPOTENT-RENEW',
                ),
            );

        $action = new VerifyPaymentAndFulfillOrderAction(
            verifyPayment: new VerifyPaymentAction(
                gateway: $gateway,
            ),
            fulfillRenewal: new FulfillPaidRenewalOrderAction,
        );

        $action->execute(
            gatewayReference: 'REF-IDEMPOTENT-RENEW',
        );
        $firstExpiry = $server->fresh()->expires_at;

        $action->execute(
            gatewayReference: 'REF-IDEMPOTENT-RENEW',
        );
        $secondExpiry = $server->fresh()->expires_at;

        $this->assertSame(
            $firstExpiry?->format('Y-m-d H:i:s'),
            $secondExpiry?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
    }

    private function action(
        string $reference,
    ): VerifyPaymentAndFulfillOrderAction {
        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );
        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');
        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                $this->verification(
                    $reference,
                ),
            );

        return new VerifyPaymentAndFulfillOrderAction(
            verifyPayment: new VerifyPaymentAction(
                gateway: $gateway,
            ),
            fulfillRenewal: new FulfillPaidRenewalOrderAction,
        );
    }

    private function verification(
        string $reference,
    ): PaymentVerificationData {
        return new PaymentVerificationData(
            reference: $reference,
            transactionId: 'TX-'.$reference,
            amount: 1_750_000,
            verifiedAt: new DateTimeImmutable(
                '2026-08-11 18:30:00',
            ),
        );
    }

    private function server(
        User $user,
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
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);
    }

    private function order(
        User $user,
        OrderType $type,
        ?Server $server,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'server_id' => $server?->id,
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
            'provider_cost' => 1_000_000,
            'markup_percent' => 75,
            'final_amount' => 1_750_000,
            'currency' => 'IRR',
            'status' => OrderStatus::PendingPayment,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ]);
    }

    private function payment(
        Order $order,
        string $reference,
    ): Payment {
        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'fake',
            'amount' => 1_750_000,
            'currency' => 'IRR',
            'status' => PaymentStatus::Pending,
            'gateway_reference' => $reference,
            'gateway_transaction_id' => null,
            'redirect_url' => 'https://gateway.test/pay',
            'failure_code' => null,
            'verified_at' => null,
        ]);
    }
}
