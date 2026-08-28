<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Livewire\Servers\Create;
use App\Models\Server;
use App\Models\User;
use App\Support\SSH\SSHTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

final class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_uses_coreflare_brand_copy(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->assertSee('آن را به Coreflare اضافه کن.')
            ->assertSee(
                'اطلاعات SSH موردنیاز برای اتصال Coreflare به سرور را وارد کنید.',
            )
            ->assertSee('روش احراز هویت')
            ->assertSee('رمز عبور')
            ->assertSee('کلید خصوصی SSH')
            ->assertDontSee('فقط Private Key را وارد کنید');
    }

    public function test_switching_to_ssh_key_clears_password_and_invalidates_verified_connection(): void
    {
        $ssh = new ReadyServerSshConnectionFake;

        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('host', '203.0.113.10')
            ->set('port', 22)
            ->set('username', 'root')
            ->set('credential', 'test-password')
            ->call('testConnection')
            ->assertSet(
                'verifiedConnectionFingerprint',
                static fn (?string $fingerprint): bool => is_string($fingerprint)
                    && $fingerprint !== '',
            )
            ->call(
                'selectAuthenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->assertSet(
                'authenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->assertSet('credential', '')
            ->assertSet('verifiedConnectionFingerprint', null)
            ->assertSee('فقط Private Key را وارد کنید')
            ->assertSee('فایل public با پسوند .pub');
    }

    public function test_invalid_private_key_is_rejected_before_connection_attempt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('host', '203.0.113.10')
            ->set('port', 22)
            ->set('username', 'root')
            ->call(
                'selectAuthenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->set('credential', 'ssh-ed25519 this-is-a-public-key')
            ->call('testConnection')
            ->assertHasErrors(['credential'])
            ->assertSet('verifiedConnectionFingerprint', null);
    }

    public function test_ssh_key_connection_can_be_verified_and_persisted_without_exposing_plaintext_storage(): void
    {
        $ssh = new ReadyServerSshConnectionFake;

        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        $user = User::factory()->create();
        $privateKey = RSA::createKey(1024)
            ->toString('PKCS8');

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('host', '203.0.113.10')
            ->set('port', 22)
            ->set('username', 'root')
            ->call(
                'selectAuthenticationType',
                AuthenticationType::SSHKey->value,
            )
            ->set('credential', $privateKey)
            ->call('testConnection')
            ->assertSet(
                'verifiedConnectionFingerprint',
                static fn (?string $fingerprint): bool => is_string($fingerprint)
                    && $fingerprint !== '',
            )
            ->call('save');

        $server = Server::query()
            ->where('user_id', $user->getKey())
            ->sole();

        $this->assertSame(
            AuthenticationType::SSHKey,
            $ssh->authenticationType,
        );
        $this->assertSame(
            AuthenticationType::SSHKey,
            $server->authentication_type,
        );
        $this->assertSame(
            $privateKey,
            $server->credential,
        );
        $this->assertNotSame(
            $privateKey,
            DB::table('servers')
                ->where('id', $server->getKey())
                ->value('credential'),
        );
    }

    public function test_unsupported_operating_system_cannot_be_verified_or_saved(): void
    {
        config()->set(
            'supported_os.matrix',
            [
                'ubuntu' => [
                    '22.04',
                    '24.04',
                    '26.04',
                ],
            ],
        );

        $this->app->forgetInstance(
            SupportedOperatingSystemPolicy::class,
        );

        $this->app->instance(
            SSHConnectionInterface::class,
            new DebianServerSshConnectionFake,
        );

        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test(Create::class)
            ->set('host', '203.0.113.10')
            ->set('port', 22)
            ->set('username', 'root')
            ->set('credential', 'test-secret')
            ->call('testConnection')
            ->assertSet(
                'verifiedConnectionFingerprint',
                null,
            );

        $component
            ->call('save')
            ->assertSet(
                'verifiedConnectionFingerprint',
                null,
            );

        $this->assertSame(
            0,
            Server::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );
    }
}

final class ReadyServerSshConnectionFake implements SSHConnectionInterface
{
    public ?AuthenticationType $authenticationType = null;

    private bool $connected = false;

    public function connect(Server $server): bool
    {
        $this->authenticationType = $server->authentication_type;
        $this->connected = true;

        return true;
    }

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        return $this->executeWithResult(
            command: $command,
            timeout: $timeout,
        )->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        if (str_contains($command, '__xdeploy_ssh_ready__')) {
            return new SSHResult(
                output: '__xdeploy_ssh_ready__',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'os-release')) {
            return new SSHResult(
                output: implode(
                    "\n",
                    [
                        'ID=ubuntu',
                        'NAME="Ubuntu"',
                        'VERSION_ID="24.04"',
                        'PRETTY_NAME="Ubuntu 24.04 LTS"',
                    ],
                ),
                exitCode: 0,
            );
        }

        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        return new SSHResult(
            output: '',
            exitCode: 1,
        );
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}

final class DebianServerSshConnectionFake implements SSHConnectionInterface
{
    private bool $connected = false;

    public function connect(Server $server): bool
    {
        $this->connected = true;

        return true;
    }

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        return $this->executeWithResult(
            command: $command,
            timeout: $timeout,
        )->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        if (str_contains($command, '__xdeploy_ssh_ready__')) {
            return new SSHResult(
                output: '__xdeploy_ssh_ready__',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'os-release')) {
            return new SSHResult(
                output: implode(
                    "\n",
                    [
                        'ID=debian',
                        'NAME="Debian GNU/Linux"',
                        'VERSION_ID="12"',
                        'PRETTY_NAME="Debian GNU/Linux 12 (bookworm)"',
                    ],
                ),
                exitCode: 0,
            );
        }

        return new SSHResult(
            output: '',
            exitCode: 1,
        );
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}
