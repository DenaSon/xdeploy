<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminServerDetailsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_details_use_polished_persian_lifecycle_copy_and_jalali_dates(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        $customer = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $customer->id,
            'name' => 'Production VPS',
            'host' => '192.0.2.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'secret-password',
            'status' => ServerStatus::Active,
            'created_at' => CarbonImmutable::parse(
                '2026-08-27 01:42:00',
            ),
        ]);

        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_server_id' => 'vm-test-123',
            'cloud_region' => 'iran',
            'provisioned_at' => CarbonImmutable::parse(
                '2026-08-27 01:50:00',
            ),
            'expires_at' => CarbonImmutable::parse(
                '2026-09-03 01:50:00',
            ),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.servers.show', $server))
            ->assertOk()
            ->assertSee('اتصال و مالکیت')
            ->assertSee('زیرساخت و چرخه عمر')
            ->assertSee('ارائه‌دهنده')
            ->assertSee('تاریخ انقضا')
            ->assertSee('۱۴۰۵/۰۶/۰۵ — ۰۱:۴۲')
            ->assertSee('۱۴۰۵/۰۶/۱۲ — ۰۱:۵۰')
            ->assertDontSee('Cloud lifecycle');
    }
}
