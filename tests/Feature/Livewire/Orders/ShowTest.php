<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Orders;

use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_order_status_page(): void
    {
        $user = User::factory()->create();

        $order = $this->order(
            $user,
        );

        $this->get(
            route(
                'panel.orders.show',
                $order,
            ),
        )->assertRedirect(
            route(
                'login',
            ),
        );
    }

    public function test_owner_can_open_pending_order_status_page(): void
    {
        $user = User::factory()->create();

        $order = $this->order(
            $user,
        );

        $this->actingAs(
            $user,
        )
            ->get(
                route(
                    'panel.orders.show',
                    $order,
                ),
            )
            ->assertOk()
            ->assertSee(
                'وضعیت سفارش',
            )
            ->assertSee(
                'در انتظار پرداخت',
            )
            ->assertSee(
                'ادامه پرداخت',
            )
            ->assertSee(
                'Ubuntu',
            )
            ->assertSee(
                '2,165,760',
            );
    }

    public function test_user_cannot_open_another_users_order(): void
    {
        $owner = User::factory()->create();

        $otherUser =
            User::factory()->create();

        $order = $this->order(
            $owner,
        );

        $this->actingAs(
            $otherUser,
        )
            ->get(
                route(
                    'panel.orders.show',
                    $order,
                ),
            )
            ->assertNotFound();
    }

    private function order(
        User $user,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->getKey(),

            'region_id' => 'ir-thr-ba1',
            'size_id' => 'eco-2-2-0',

            'image_id' => 'ubuntu-24-image',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'Ubuntu',
            'image_version' => '24.04',

            'default_disk_gib' => 30,
            'selected_disk_gib' => 50,

            'period' => '2_days',
            'duration_hours' => 48,

            'provider_cost' => 1_353_600,
            'markup_percent' => 60,
            'final_amount' => 2_165_760,

            'currency' => 'IRR',

            'status' => OrderStatus::PendingPayment,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => null,
        ]);
    }
}
