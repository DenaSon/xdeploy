<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Orders;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_customer_orders_page(): void
    {
        $this->get(
            route('panel.orders.index'),
        )->assertRedirect(
            route('login'),
        );
    }

    public function test_customer_sees_only_their_orders_and_can_open_details(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownOrder = $this->createOrder(
            user: $user,
            status: OrderStatus::PendingPayment,
        );

        $otherOrder = $this->createOrder(
            user: $other,
            status: OrderStatus::PendingPayment,
        );

        $this->actingAs($user)
            ->get(
                route('panel.orders.index'),
            )
            ->assertOk()
            ->assertSee('سفارش‌ها')
            ->assertSee('در انتظار پرداخت')
            ->assertSee('خرید VPS')
            ->assertSee('#'.$ownOrder->getKey(), false)
            ->assertSee(
                route(
                    'panel.orders.show',
                    $ownOrder,
                ),
                false,
            )
            ->assertDontSee('#'.$otherOrder->getKey(), false);
    }

    public function test_paid_failed_order_is_presented_as_paid_and_needing_review(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            status: OrderStatus::Failed,
            paid: true,
        );

        $order->payments()->create([
            'gateway' => 'zarinpal',
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(
                route('panel.orders.index'),
            )
            ->assertOk()
            ->assertSee('پرداخت انجام شده — نیازمند بررسی')
            ->assertSee('پرداخت مجدد انجام ندهید')
            ->assertSee('موفق');
    }

    public function test_customer_orders_page_has_clear_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(
                route('panel.orders.index'),
            )
            ->assertOk()
            ->assertSee('هنوز سفارشی ندارید')
            ->assertSee('خرید اولین VPS');
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
            'image_distribution' => 'Ubuntu',
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
}
