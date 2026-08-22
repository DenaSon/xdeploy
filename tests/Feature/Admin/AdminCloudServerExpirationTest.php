<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Admin\Servers\Index;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminCloudServerExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_expire_cloud_server_without_dispatching_termination(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $server = $this->cloudServer();
        $previousExpiresAt = $server->expires_at;
        $before = now()->subSecond();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('expireNow', $server->id)
            ->assertSet('expirationError', null)
            ->assertSet(
                'expirationMessage',
                'سرور منقضی شد. Scheduler در اجرای بعدی فرایند حذف را انجام می‌دهد.',
            );

        $server->refresh();

        $this->assertNotNull($server->expires_at);
        $this->assertTrue($server->expires_at->greaterThanOrEqualTo($before));
        $this->assertTrue($server->expires_at->lessThanOrEqualTo(now()->addSecond()));
        $this->assertFalse($server->expires_at->equalTo($previousExpiresAt));
        $this->assertSame(ServerStatus::Active, $server->status);
        $this->assertNull($server->termination_started_at);
        $this->assertNull($server->termination_last_attempt_at);
        $this->assertSame(0, $server->termination_attempts);
        $this->assertNull($server->terminated_at);

        Queue::assertNothingPushed();
    }

    public function test_manual_server_cannot_be_expired_from_admin_action(): void
    {
        $admin = $this->admin();
        $server = $this->manualServer();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('expireNow', $server->id)
            ->assertSet(
                'expirationError',
                'این سرور برای انقضای دستی قابل استفاده نیست.',
            );

        $this->assertNull($server->refresh()->expires_at);
    }

    public function test_already_expired_cloud_server_is_left_unchanged(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $server = $this->cloudServer([
            'expires_at' => now()->subHour(),
        ]);
        $expiredAt = $server->expires_at;

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('expireNow', $server->id)
            ->assertSet(
                'expirationMessage',
                'این سرور قبلاً منقضی شده است.',
            );

        $server->refresh();

        $this->assertTrue($server->expires_at?->equalTo($expiredAt) ?? false);
        $this->assertNull($server->termination_started_at);
        Queue::assertNothingPushed();
    }

    public function test_admin_server_list_only_offers_manual_expiration_for_eligible_cloud_server(): void
    {
        $admin = $this->admin();
        $this->manualServer();
        $this->cloudServer();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('منقضی کردن');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cloudServer(array $overrides = []): Server
    {
        $user = User::factory()->create();

        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Cloud VPS',
            'host' => '192.0.2.10',
            'port' => 22,
            'username' => 'ubuntu',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => 'cloud-server-1',
            'cloud_region' => 'ir-thr-ba1',
            'provisioned_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'termination_attempts' => 0,
            ...$overrides,
        ]);
    }

    private function manualServer(): Server
    {
        $user = User::factory()->create();

        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Manual VPS',
            'host' => '192.0.2.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
        ]);
    }
}
