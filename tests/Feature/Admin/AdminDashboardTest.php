<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_overview_only_counts_verified_paid_orders(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();

        $paidOrder = $this->createOrder(
            user: $customer,
            providerCost: 100_000,
            markupPercent: 75,
            finalAmount: 175_000,
            status: OrderStatus::Fulfilled,
        );

        Payment::query()->create([
            'order_id' => $paidOrder->id,
            'gateway' => 'test',
            'amount' => 175_000,
            'currency' => 'IRR',
            'status' => PaymentStatus::Paid,
            'gateway_reference' => 'verified-payment',
            'verified_at' => now(),
        ]);

        $pendingOrder = $this->createOrder(
            user: $customer,
            providerCost: 200_000,
            markupPercent: 75,
            finalAmount: 350_000,
            status: OrderStatus::PendingPayment,
        );

        Payment::query()->create([
            'order_id' => $pendingOrder->id,
            'gateway' => 'test',
            'amount' => 350_000,
            'currency' => 'IRR',
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'pending-payment',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('فروش تأییدشده')
            ->assertSee('175,000')
            ->assertSee('100,000')
            ->assertSee('75,000')
            ->assertSee('75.0٪');
    }

    public function test_dashboard_limits_each_recent_collection_to_ten_records(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create();

        User::factory()->count(11)->create();

        foreach (range(1, 11) as $index) {
            Server::query()->create([
                'user_id' => $owner->id,
                'name' => "Server {$index}",
                'host' => "10.20.30.{$index}",
                'username' => 'root',
                'status' => ServerStatus::Active,
            ]);

            $this->createOrder(
                user: $owner,
                providerCost: 100_000,
                markupPercent: 75,
                finalAmount: 175_000,
                status: OrderStatus::PendingPayment,
                suffix: (string) $index,
            );
        }

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas(
                'recentUsers',
                fn ($users): bool => $users->count() === 10,
            )
            ->assertViewHas(
                'recentOrders',
                fn ($orders): bool => $orders->count() === 10,
            )
            ->assertViewHas(
                'recentServers',
                fn ($servers): bool => $servers->count() === 10,
            );
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function createOrder(
        User $user,
        int $providerCost,
        int $markupPercent,
        int $finalAmount,
        OrderStatus $status,
        string $suffix = 'base',
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,
            'type' => OrderType::Provisioning,
            'region_id' => "region-{$suffix}",
            'size_id' => "size-{$suffix}",
            'image_id' => "image-{$suffix}",
            'image_name' => 'Ubuntu',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 20,
            'selected_disk_gib' => 20,
            'period' => 'monthly',
            'duration_hours' => 720,
            'provider_cost' => $providerCost,
            'markup_percent' => $markupPercent,
            'final_amount' => $finalAmount,
            'currency' => 'IRR',
            'status' => $status,
            'quote_expires_at' => now()->addHour(),
            'paid_at' => $status === OrderStatus::PendingPayment
                ? null
                : now(),
        ]);
    }
}
