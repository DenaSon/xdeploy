<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Server\Actions\ListServerSupportHistoryAction;
use App\Application\Server\Actions\RecordSupportAccessAction;
use App\Application\Server\Data\SupportHistoryEntryData;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ServerSupportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_server_support_history_newest_first_and_filter_actions(): void
    {
        $admin = $this->admin();
        $server = $this->server(User::factory()->create());
        $recorder = app(RecordSupportAccessAction::class);

        $recorder->handle(
            admin: $admin,
            server: $server,
            action: SupportAccessAction::SshConnectionTest,
            reason: 'بررسی اتصال',
            successful: true,
            ipAddress: '127.0.0.1',
            userAgent: 'test',
        );

        $ipChange = $recorder->handle(
            admin: $admin,
            server: $server,
            action: SupportAccessAction::ConnectionHostUpdated,
            reason: 'IP توسط Provider تغییر کرد',
            successful: true,
            ipAddress: '127.0.0.1',
            userAgent: 'test',
            metadata: [
                'old_host' => '192.0.2.10',
                'new_host' => '198.51.100.25',
            ],
        );

        $credential = $recorder->handle(
            admin: $admin,
            server: $server,
            action: SupportAccessAction::CredentialRevealed,
            reason: 'بررسی درخواست پشتیبانی',
            successful: true,
            ipAddress: '127.0.0.1',
            userAgent: 'test',
        );

        $history = app(ListServerSupportHistoryAction::class)->handle(
            admin: $admin,
            serverId: $server->id,
        );

        $this->assertCount(3, $history);
        $this->assertContainsOnlyInstancesOf(
            SupportHistoryEntryData::class,
            $history,
        );
        $this->assertSame($credential->id, $history[0]->id);
        $this->assertSame($ipChange->id, $history[1]->id);

        $filtered = app(ListServerSupportHistoryAction::class)->handle(
            admin: $admin,
            serverId: $server->id,
            actions: [SupportAccessAction::ConnectionHostUpdated],
        );

        $this->assertCount(1, $filtered);
        $this->assertSame(
            SupportAccessAction::ConnectionHostUpdated,
            $filtered[0]->action,
        );
        $this->assertSame('تغییر IP سرور', $filtered[0]->title);
        $this->assertSame('IP توسط Provider تغییر کرد', $filtered[0]->reason);
        $this->assertSame(
            [
                'old_host' => '192.0.2.10',
                'new_host' => '198.51.100.25',
            ],
            $filtered[0]->metadata,
        );
    }

    public function test_legacy_ip_change_reason_is_normalized_into_structured_history(): void
    {
        $admin = $this->admin();
        $server = $this->server(User::factory()->create());

        SupportAccessLog::query()->create([
            'admin_user_id' => $admin->id,
            'user_id' => $server->user_id,
            'server_id' => $server->id,
            'action' => SupportAccessAction::ConnectionHostUpdated,
            'reason' => 'IP: 192.0.2.10 → 198.51.100.25 | تغییر IP قدیمی',
            'successful' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'legacy-test',
        ]);

        $entry = app(ListServerSupportHistoryAction::class)->handle(
            admin: $admin,
            serverId: $server->id,
        )[0];

        $this->assertSame('تغییر IP قدیمی', $entry->reason);
        $this->assertSame('192.0.2.10', $entry->metadata['old_host']);
        $this->assertSame('198.51.100.25', $entry->metadata['new_host']);
    }

    public function test_history_remains_available_for_expired_terminated_and_soft_deleted_server(): void
    {
        $admin = $this->admin();
        $server = $this->server(User::factory()->create());
        $server->forceFill([
            'expires_at' => now()->subDay(),
            'terminated_at' => now(),
        ])->save();

        app(RecordSupportAccessAction::class)->handle(
            admin: $admin,
            server: $server,
            action: SupportAccessAction::SshConnectionTest,
            reason: 'بررسی قبل از بایگانی',
            successful: true,
            ipAddress: '127.0.0.1',
            userAgent: 'test',
        );

        $serverId = $server->id;
        $server->delete();

        $this->assertSoftDeleted('servers', [
            'id' => $serverId,
        ]);

        $history = app(ListServerSupportHistoryAction::class)->handle(
            admin: $admin,
            serverId: $serverId,
        );

        $this->assertCount(1, $history);
        $this->assertSame(
            SupportAccessAction::SshConnectionTest,
            $history[0]->action,
        );
    }

    public function test_non_admin_cannot_read_server_support_history(): void
    {
        $user = User::factory()->create();
        $server = $this->server(User::factory()->create());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Server support history is only available to administrators.',
        );

        app(ListServerSupportHistoryAction::class)->handle(
            admin: $user,
            serverId: $server->id,
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function server(User $owner): Server
    {
        return Server::query()->create([
            'user_id' => $owner->id,
            'name' => 'Support History VPS',
            'host' => '192.0.2.10',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'server-password',
            'status' => ServerStatus::Active,
        ]);
    }
}
