<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminOperationalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_browse_operational_indexes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.servers.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk();
    }

    public function test_admin_can_open_operational_detail_pages(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create([
            'phone' => '09120000001',
        ]);

        $customer->profile()->create([
            'first_name' => 'مشتری',
            'last_name' => 'تست',
        ]);

        $server = Server::query()->create([
            'user_id' => $customer->id,
            'name' => 'Primary VPS',
            'host' => '192.0.2.10',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'type' => OrderType::Provisioning,
            'server_id' => $server->id,
            'region_id' => 'ir-thr',
            'size_id' => 'g1-1-1',
            'image_id' => 'ubuntu-24',
            'image_name' => 'Ubuntu',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 25,
            'selected_disk_gib' => 25,
            'period' => 'monthly',
            'duration_hours' => 720,
            'provider_cost' => 100000,
            'markup_percent' => 10,
            'final_amount' => 110000,
            'currency' => 'IRR',
            'status' => OrderStatus::Fulfilled,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'zarinpal',
            'amount' => 110000,
            'currency' => 'IRR',
            'status' => PaymentStatus::Paid,
            'gateway_reference' => 'AUTH-ADMIN-1',
            'gateway_transaction_id' => 'TX-ADMIN-1',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $customer))
            ->assertOk()
            ->assertSee('مشتری تست');

        $this->actingAs($admin)
            ->get(route('admin.servers.show', $server))
            ->assertOk()
            ->assertSee('Primary VPS');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('سفارش #'.$order->id);

        $this->actingAs($admin)
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee('TX-ADMIN-1');
    }

    public function test_admin_identity_search_uses_profile_names(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create([
            'phone' => '09120000009',
        ]);

        $customer->profile()->create([
            'first_name' => 'سارا',
            'last_name' => 'احمدی',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'سارا احمدی']))
            ->assertOk()
            ->assertSee('09120000009');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }
}
