<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Server\Actions\UpdateServerAction;
use App\Application\Server\Actions\UpdateServerConnectionHostAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Livewire\Admin\Servers\Show;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class AdminServerIpUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_server_ip_from_server_details_and_change_is_audited(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $server = $this->server(
            owner: $customer,
            host: '192.0.2.10',
        );
        $reason = 'Provider آدرس قبلی را جایگزین کرده است';

        $this->actingAs($admin);

        Livewire::test(
            Show::class,
            ['adminServer' => $server],
        )
            ->assertSet('newHost', '192.0.2.10')
            ->set('newHost', '198.51.100.25')
            ->set('hostUpdateReason', $reason)
            ->call('updateServerConnectionHost')
            ->assertHasNoErrors()
            ->assertSet('newHost', '198.51.100.25')
            ->assertSet('hostUpdateReason', '')
            ->assertSee(
                'آدرس IP سرور از 192.0.2.10 به 198.51.100.25 تغییر کرد.',
            );

        $this->assertSame(
            '198.51.100.25',
            $server->refresh()->host,
        );

        $log = SupportAccessLog::query()
            ->where(
                'action',
                SupportAccessAction::ConnectionHostUpdated->value,
            )
            ->sole();

        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame($customer->id, $log->user_id);
        $this->assertSame($server->id, $log->server_id);
        $this->assertTrue($log->successful);
        $this->assertStringContainsString(
            '192.0.2.10',
            $log->reason,
        );
        $this->assertStringContainsString(
            '198.51.100.25',
            $log->reason,
        );
        $this->assertStringContainsString(
            $reason,
            $log->reason,
        );
    }

    public function test_admin_server_ip_update_rejects_invalid_ipv4(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            owner: User::factory()->create(),
            host: '192.0.2.10',
        );

        $this->actingAs($admin);

        Livewire::test(
            Show::class,
            ['adminServer' => $server],
        )
            ->set('newHost', 'server.example.com')
            ->set(
                'hostUpdateReason',
                'Provider آدرس جدید اعلام کرده است',
            )
            ->call('updateServerConnectionHost')
            ->assertHasErrors(['newHost' => 'ipv4']);

        $this->assertSame(
            '192.0.2.10',
            $server->refresh()->host,
        );
        $this->assertDatabaseCount('support_access_logs', 0);
    }

    public function test_admin_server_ip_update_rejects_duplicate_owner_host_and_port(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $server = $this->server(
            owner: $customer,
            host: '192.0.2.10',
        );
        $this->server(
            owner: $customer,
            host: '198.51.100.25',
        );

        $this->actingAs($admin);

        Livewire::test(
            Show::class,
            ['adminServer' => $server],
        )
            ->set('newHost', '198.51.100.25')
            ->set(
                'hostUpdateReason',
                'Provider آدرس جدید اعلام کرده است',
            )
            ->call('updateServerConnectionHost')
            ->assertHasErrors('newHost');

        $this->assertSame(
            '192.0.2.10',
            $server->refresh()->host,
        );
        $this->assertDatabaseCount('support_access_logs', 0);
    }

    public function test_non_admin_cannot_use_server_host_override_action(): void
    {
        $user = User::factory()->create();
        $server = $this->server(
            owner: User::factory()->create(),
            host: '192.0.2.10',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Only an administrator may update a server connection host.',
        );

        app(UpdateServerConnectionHostAction::class)->handle(
            admin: $user,
            server: $server,
            newHost: '198.51.100.25',
            reason: 'Provider آدرس جدید اعلام کرده است',
            ipAddress: '127.0.0.1',
            userAgent: 'test',
        );
    }

    public function test_normal_server_update_still_cannot_change_host(): void
    {
        $owner = User::factory()->create();
        $server = $this->server(
            owner: $owner,
            host: '192.0.2.10',
        );

        app(UpdateServerAction::class)->handle(
            user: $owner,
            server: $server,
            attributes: [
                'name' => 'Renamed VPS',
                'host' => '198.51.100.25',
            ],
        );

        $server->refresh();

        $this->assertSame('Renamed VPS', $server->name);
        $this->assertSame('192.0.2.10', $server->host);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function server(
        User $owner,
        string $host,
    ): Server {
        return Server::query()->create([
            'user_id' => $owner->id,
            'name' => 'Customer VPS',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'server-password',
            'status' => ServerStatus::Active,
        ]);
    }
}
