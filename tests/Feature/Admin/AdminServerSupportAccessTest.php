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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class AdminServerSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_server_page_shows_support_access_without_rendering_credential(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            credential: 'support-secret-password',
        );

        $this->actingAs($admin)
            ->get(route('admin.servers.show', $server))
            ->assertOk()
            ->assertSee('دسترسی پشتیبانی')
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
                ['reason' => 'بررسی مشکل اتصال کاربر'],
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
                    'confirmed_at' => now()->timestamp,
                ],
            ])
            ->postJson(
                route('admin.servers.support.reveal-credential', $server),
                ['reason' => 'بررسی مشکل اتصال کاربر'],
            )
            ->assertForbidden();
    }

    public function test_confirmed_admin_can_reveal_password_with_no_store_headers_and_audit(): void
    {
        $admin = $this->admin();
        $server = $this->server(
            credential: 'support-secret-password',
        );

        $response = $this->actingAs($admin)
            ->withSession([
                AdminSupportAccessSession::SESSION_KEY => [
                    'admin_user_id' => $admin->id,
                    'server_id' => $server->id,
                    'confirmed_at' => now()->timestamp,
                ],
            ])
            ->postJson(
                route('admin.servers.support.reveal-credential', $server),
                ['reason' => 'رفع خطای نصب گزارش شده توسط کاربر'],
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
        $this->assertSame(
            'رفع خطای نصب گزارش شده توسط کاربر',
            $log->reason,
        );
        $this->assertArrayNotHasKey(
            'credential',
            $log->getAttributes(),
        );
    }

    public function test_admin_can_confirm_sensitive_support_access_with_existing_otp_service(): void
    {
        $admin = $this->admin(
            phone: '09120000009',
        );
        $server = $this->server();

        DB::table('otps')->insert([
            'phone' => $admin->phone,
            'code' => password_hash(
                '12345',
                PASSWORD_DEFAULT,
            ),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(
                Show::class,
                ['adminServer' => $server],
            )
            ->set(
                'supportReason',
                'بررسی خطای سرویس کاربر',
            )
            ->set('supportOtp', '12345')
            ->call('confirmSupportOtp')
            ->assertSet('supportAccessConfirmed', true)
            ->assertHasNoErrors('supportOtp');

        $state = session()->get(
            AdminSupportAccessSession::SESSION_KEY,
        );

        $this->assertIsArray($state);
        $this->assertSame($admin->id, $state['admin_user_id']);
        $this->assertSame($server->id, $state['server_id']);
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

        Livewire::actingAs($admin)
            ->test(
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
