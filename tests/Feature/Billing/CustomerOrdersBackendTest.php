<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Actions\GetCustomerOrderAction;
use App\Application\Billing\Actions\ListCustomerOrdersAction;
use App\Application\Billing\Actions\ResolveCustomerOrderStateAction;
use App\Domain\Billing\Enums\CustomerOrderState;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerOrdersBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_order_list_is_scoped_to_owner_and_newest_first(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $older = $this->createOrder(
            user: $user,
            status: OrderStatus::PendingPayment,
        );

        $latest = $this->createOrder(
            user: $user,
            status: OrderStatus::Paid,
            paid: true,
        );

        $this->createOrder(
            user: $other,
            status: OrderStatus::Fulfilled,
            paid: true,
        );

        $this->createPayment(
            order: $latest,
            status: PaymentStatus::Paid,
        );

        $orders = app(ListCustomerOrdersAction::class)
            ->execute(
                user: $user,
                perPage: 10,
            );

        $this->assertSame(2, $orders->total());
        $this->assertSame(
            [$latest->getKey(), $older->getKey()],
            $orders->getCollection()
                ->modelKeys(),
        );

        $first = $orders->getCollection()->first();

        $this->assertInstanceOf(Order::class, $first);
        $this->assertTrue($first->relationLoaded('latestPayment'));
        $this->assertTrue($first->relationLoaded('historicalServer'));
        $this->assertSame(
            PaymentStatus::Paid,
            $first->latestPayment?->status,
        );
    }

    public function test_customer_order_detail_is_owner_scoped_and_loads_payment_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            status: OrderStatus::Paid,
            paid: true,
        );

        $firstPayment = $this->createPayment(
            order: $order,
            status: PaymentStatus::Failed,
        );

        $latestPayment = $this->createPayment(
            order: $order,
            status: PaymentStatus::Paid,
        );

        $loaded = app(GetCustomerOrderAction::class)
            ->execute(
                user: $user,
                orderId: (int) $order->getKey(),
            );

        $this->assertTrue($loaded->relationLoaded('payments'));
        $this->assertTrue($loaded->relationLoaded('latestPayment'));
        $this->assertTrue($loaded->relationLoaded('historicalServer'));
        $this->assertSame(
            [$latestPayment->getKey(), $firstPayment->getKey()],
            $loaded->payments->modelKeys(),
        );
        $this->assertSame(
            $latestPayment->getKey(),
            $loaded->latestPayment?->getKey(),
        );

        $this->expectException(ModelNotFoundException::class);

        app(GetCustomerOrderAction::class)
            ->execute(
                user: $other,
                orderId: (int) $order->getKey(),
            );
    }

    public function test_customer_order_state_distinguishes_payment_and_fulfillment_failures(): void
    {
        $user = User::factory()->create();
        $resolver = app(ResolveCustomerOrderStateAction::class);

        $awaitingPayment = $this->createOrder(
            user: $user,
            status: OrderStatus::PendingPayment,
        );

        $this->assertSame(
            CustomerOrderState::AwaitingPayment,
            $resolver->execute($awaitingPayment),
        );

        $paymentPending = $this->createOrder(
            user: $user,
            status: OrderStatus::PendingPayment,
        );
        $this->createPayment(
            order: $paymentPending,
            status: PaymentStatus::Pending,
        );

        $this->assertSame(
            CustomerOrderState::PaymentPending,
            $resolver->execute($paymentPending),
        );

        $paidButOrderNotAdvanced = $this->createOrder(
            user: $user,
            status: OrderStatus::PendingPayment,
        );
        $this->createPayment(
            order: $paidButOrderNotAdvanced,
            status: PaymentStatus::Paid,
        );

        $this->assertSame(
            CustomerOrderState::Processing,
            $resolver->execute($paidButOrderNotAdvanced),
        );

        $failedAfterPayment = $this->createOrder(
            user: $user,
            status: OrderStatus::Failed,
            paid: true,
        );
        $this->createPayment(
            order: $failedAfterPayment,
            status: PaymentStatus::Paid,
        );

        $this->assertSame(
            CustomerOrderState::NeedsAttention,
            $resolver->execute($failedAfterPayment),
        );

        $fulfilled = $this->createOrder(
            user: $user,
            status: OrderStatus::Fulfilled,
            paid: true,
        );

        $this->assertSame(
            CustomerOrderState::Completed,
            $resolver->execute($fulfilled),
        );
    }

    private function createOrder(
        User $user,
        OrderStatus $status,
        bool $paid = false,
    ): Order {
        return $user->orders()->create([
            'type' => OrderType::Provisioning,
            'cloud_provider' => CloudProviderType::Liara,
            'region_id' => 'iran',
            'size_id' => 'standard-base-g2',
            'image_id' => 'ubuntu-26.04',
            'image_name' => 'Ubuntu 26.04',
            'image_distribution' => 'ubuntu',
            'image_version' => '26.04',
            'default_disk_gib' => 20,
            'selected_disk_gib' => 20,
            'period' => '14_days',
            'duration_hours' => 336,
            'provider_cost' => 500_000,
            'markup_percent' => 75,
            'final_amount' => 875_000,
            'currency' => 'IRR',
            'status' => $status,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => $paid ? now() : null,
        ]);
    }

    private function createPayment(
        Order $order,
        PaymentStatus $status,
    ): Payment {
        return $order->payments()->create([
            'gateway' => 'zarinpal',
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => $status,
            'verified_at' => $status === PaymentStatus::Paid
                ? now()
                : null,
        ]);
    }
}
