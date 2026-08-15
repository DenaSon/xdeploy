<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Livewire\Admin\Servers\Show;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use App\Support\Admin\AdminSupportAccessSession;
use App\Support\Admin\PendingSupportPasskeyVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class AdminServerSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_server_page_shows_passkey_support_access_without_rendering_credential(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            credential: 'support-secret-password',
        );

        $this->actingAs($admin)
            ->get(route('admin.servers.show', $server))
            ->assertOk()
            ->assertSee('دسترسی پشتیبانی')
            ->assertSee('تأیید با Passkey')
            ->assertSee('تست اتصال SSH')
            ->assertDontSee('support-secret-password');
    }

    public function test_credential_reveal_requires_recent_confirmation_for_same_server(): void
    {
        $admin = $this->admin();
        $server = $this->server();

        $this->actingAs($admin)
            ->postJson(
                route('admin.servers.support.reveal-credential', $server),
                [],
            )
            ->assertForbidden();

        $otherServer = $this->server(
            name: 'Other VPS',
            host: '192.0.2.11',
        );

        $this->actingAs($admin)
            ->withSession([
                AdminSupportAccessSession::SESSION_KEY => [
                    'admin_user_id' => $admin->id,
                    'server_id' => $otherServer->id,
                    'reason' => 'بررسی سرور دیگر',
                    'confirmed_at' => now()->timestamp,
                ],
            ])
            ->postJson(
                route('admin.servers.support.reveal-credential', $server),
                [],
            )
            ->assertForbidden();
    }

    public function test_confirmed_admin_can_reveal_password_with_bound_reason_no_store_headers_and_audit(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            credential: 'support-secret-password',
        );
        $confirmedReason = 'رفع خطای نصب گزارش شده توسط کاربر';

        $response = $this->actingAs($admin)
            ->withSession([
                AdminSupportAccessSession::SESSION_KEY => [
                    'admin_user_id' => $admin->id,
                    'server_id' => $server->id,
                    'reason' => $confirmedReason,
                    'confirmed_at' => now()->timestamp,
                ],
            ])
            ->postJson(
                route('admin.servers.support.reveal-credential', $server),
                [
                    'reason' => 'این دلیل نباید جایگزین دلیل تأییدشده شود',
                ],
            );

        $response
            ->assertOk()
            ->assertJson([
                'credential' => 'support-secret-password',
                'expires_in' => 30,
            ])
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $cacheControl = (string) $response->headers->get(
            'Cache-Control',
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl,
        );
        $this->assertStringContainsString(
            'private',
            $cacheControl,
        );
        $this->assertStringContainsString(
            'max-age=0',
            $cacheControl,
        );
        $this->assertStringContainsString(
            'must-revalidate',
            $cacheControl,
        );

        $log = SupportAccessLog::query()->sole();

        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame($server->user_id, $log->user_id);
        $this->assertSame($server->id, $log->server_id);
        $this->assertSame(
            SupportAccessAction::CredentialRevealed,
            $log->action,
        );
        $this->assertTrue($log->successful);
        $this->assertSame($confirmedReason, $log->reason);
        $this->assertArrayNotHasKey(
            'credential',
            $log->getAttributes(),
        );
    }

    public function test_admin_can_prepare_server_bound_support_passkey_challenge(): void
    {
        $admin = $this->admin();
        $server = $this->server();
        $reason = 'بررسی خطای سرویس گزارش شده توسط کاربر';

        $this->actingAs($admin);

        Livewire::test(
            Show::class,
            ['adminServer' => $server],
        )
            ->set('supportReason', $reason)
            ->call('prepareSupportPasskeyVerification')
            ->assertHasNoErrors('supportReason')
            ->assertSet('supportAccessConfirmed', false);

        $state = session()->get(
            PendingSupportPasskeyVerification::SESSION_KEY,
        );

        $this->assertIsArray($state);
        $this->assertSame($admin->id, $state['admin_user_id']);
        $this->assertSame($server->id, $state['server_id']);
        $this->assertSame($reason, $state['reason']);
        $this->assertNull($state['options']);

        $this->getJson(
            route('admin.servers.support.passkey.options', $server),
        )
            ->assertOk()
            ->assertJsonPath(
                'options.userVerification',
                'required',
            )
            ->assertJsonCount(
                1,
                'options.allowCredentials',
            )
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $state = session()->get(
            PendingSupportPasskeyVerification::SESSION_KEY,
        );

        $this->assertIsArray($state);
        $this->assertIsString($state['options']);
        $this->assertNotSame('', $state['options']);
    }

    public function test_support_passkey_preparation_cannot_be_reused_for_another_server(): void
    {
        $admin = $this->admin();
        $server = $this->server();
        $otherServer = $this->server(
            name: 'Other VPS',
            host: '192.0.2.12',
        );

        $this->actingAs($admin);

        app(PendingSupportPasskeyVerification::class)->prepare(
            admin: $admin,
            server: $server,
            reason: 'بررسی دسترسی سرور اول',
        );

        $this->getJson(
            route('admin.servers.support.passkey.options', $otherServer),
        )->assertStatus(409);
    }

    public function test_pending_support_passkey_challenge_is_consumed_once(): void
    {
        $admin = $this->admin();
        $server = $this->server();
        $pending = app(
            PendingSupportPasskeyVerification::class,
        );

        $this->actingAs($admin);

        $pending->prepare(
            admin: $admin,
            server: $server,
            reason: 'بررسی خطای سرویس',
        );

        $this->assertTrue(
            $pending->attachOptions(
                admin: $admin,
                server: $server,
                serializedOptions: '{"challenge":"one-time"}',
            ),
        );

        $first = $pending->consume(
            admin: $admin,
            server: $server,
        );
        $second = $pending->consume(
            admin: $admin,
            server: $server,
        );

        $this->assertSame(
            [
                'reason' => 'بررسی خطای سرویس',
                'options' => '{"challenge":"one-time"}',
            ],
            $first,
        );
        $this->assertNull($second);
    }

    public function test_support_connection_test_is_audited_without_revealing_credential(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            credential: 'support-secret-password',
        );

        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh->shouldReceive('connect')
            ->once()
            ->withArgs(
                fn (Server $candidate): bool => $candidate->is($server),
            )
            ->andReturnTrue();

        $ssh->shouldReceive('disconnect')
            ->once();

        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        $this->actingAs($admin);

        Livewire::test(
            Show::class,
            ['adminServer' => $server],
        )
            ->set(
                'supportReason',
                'تست اتصال برای بررسی مشکل پشتیبانی',
            )
            ->call('testSupportConnection')
            ->assertSet('connectionTestPassed', true)
            ->assertSet(
                'connectionTestMessage',
                'اتصال SSH با موفقیت برقرار شد.',
            )
            ->assertDontSee('support-secret-password');

        $this->assertDatabaseHas(
            'support_access_logs',
            [
                'admin_user_id' => $admin->id,
                'server_id' => $server->id,
                'action' => SupportAccessAction::SshConnectionTest->value,
                'successful' => true,
            ],
        );
    }

    private function admin(
        string $phone = '09120000008',
    ): User {
        $admin = User::factory()->create([
            'phone' => $phone,
        ]);

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function server(
        string $name = 'Customer VPS',
        string $host = '192.0.2.10',
        string $credential = 'server-password',
    ): Server {
        $customer = User::factory()->create();

        return Server::query()->create([
            'user_id' => $customer->id,
            'name' => $name,
            'host' => $host,
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => $credential,
            'status' => ServerStatus::Active,
        ]);
    }
}
