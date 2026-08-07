<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

require_once __DIR__
    . '/../../Support/SSH/FakeSshPortSocket.php';

use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Infrastructure\SSH\Services\FakeSshPortSocket;
use App\Models\Server;
use App\Models\User;
use App\Support\SSH\SSHTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class VerifyCloudServerSshReadinessActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeSshPortSocket::ready();
    }

    protected function tearDown(): void
    {
        FakeSshPortSocket::reset();

        parent::tearDown();
    }

    public function test_it_activates_a_server_with_direct_root_access(): void
    {
        $server = $this->createServer();

        $ssh = $this->ssh();

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->with(
                Mockery::on(
                    fn (Server $value): bool =>
                        $value->is($server),
                ),
            )
            ->andReturnTrue();

        $this->expectCommandReadinessProbe(
            $ssh,
        );

        $this->expectSupportedUbuntuInspection(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: '0',
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        $mode = $this->action(
            $ssh,
        )->handle(
            $server,
        );

        $this->assertSame(
            PrivilegedExecutionMode::Root,
            $mode,
        );

        $this->assertSame(
            ServerStatus::Active,
            $server->refresh()->status,
        );
    }

    public function test_it_activates_a_server_with_passwordless_sudo(): void
    {
        $server = $this->createServer();

        $ssh = $this->ssh();

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->andReturnTrue();

        $this->expectCommandReadinessProbe(
            $ssh,
        );

        $this->expectSupportedUbuntuInspection(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: '1000',
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'sudo -n id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: '0',
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        $mode = $this->action(
            $ssh,
        )->handle(
            $server,
        );

        $this->assertSame(
            PrivilegedExecutionMode::PasswordlessSudo,
            $mode,
        );

        $this->assertSame(
            ServerStatus::Active,
            $server->refresh()->status,
        );
    }

    public function test_connection_failure_keeps_server_inactive(): void
    {
        $server = $this->createServer();

        $ssh = $this->ssh();

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->andReturnFalse();

        $ssh
            ->shouldNotReceive(
                'executeWithResult',
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        try {
            $this->action(
                $ssh,
            )->handle(
                $server,
            );

            $this->fail(
                'Expected SSH readiness failure was not thrown.',
            );
        } catch (
            CloudServerSshUnavailableException
        ) {
            $this->assertSame(
                ServerStatus::Inactive,
                $server->refresh()->status,
            );
        }
    }

    private function action(
        SSHConnectionInterface $ssh,
    ): VerifyCloudServerSshReadinessAction {
        /*
         * Resolve the real action graph through Laravel so the test remains
         * aligned with constructor changes in its concrete collaborators.
         *
         * Only the SSH transport itself is replaced with the test mock.
         */
        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        return $this->app->make(
            VerifyCloudServerSshReadinessAction::class,
        );
    }

    private function expectCommandReadinessProbe(
        SSHConnectionInterface&MockInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool =>
                        str_contains(
                            $command,
                            '__xdeploy_ssh_ready__',
                        ),
                ),
                Mockery::type('int'),
            )
            ->andReturn(
                new SSHResult(
                    output: '__xdeploy_ssh_ready__',
                    exitCode: 0,
                ),
            );
    }

    private function expectSupportedUbuntuInspection(
        SSHConnectionInterface&MockInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool =>
                        str_contains(
                            $command,
                            '/etc/os-release',
                        ),
                ),
                Mockery::type('int'),
            )
            ->andReturn(
                new SSHResult(
                    output: implode(
                        "\n",
                        [
                            'NAME="Ubuntu"',
                            'VERSION_ID="24.04"',
                            'ID=ubuntu',
                            'ID_LIKE=debian',
                            'PRETTY_NAME="Ubuntu 24.04.4 LTS"',
                        ],
                    ),
                    exitCode: 0,
                ),
            );
    }

    /**
     * @return SSHConnectionInterface&MockInterface
     */
    private function ssh(): SSHConnectionInterface
    {
        /** @var SSHConnectionInterface&MockInterface $ssh */
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        return $ssh;
    }

    private function createServer(): Server
    {
        $user = User::query()->create([
            'name' => 'SSH Readiness User',
            'phone' => '+4915112345678',
        ]);

        return app(
            CreateServerAction::class,
        )->handle(
            user: $user,

            attributes: [
                'name' => 'xdeploy-cloud-server',

                'host' => '185.204.168.213',

                'port' => 22,

                'username' => 'ubuntu',

                'authentication_type' =>
                    AuthenticationType::Password,

                'credential' =>
                    'temporary-generated-password',

                'cloud_provider' => 'arvan',

                'cloud_server_id' =>
                    'provider-server-id',

                'cloud_region' => 'eu-west1-a',

                'provisioned_at' => now(),
            ],

            explicitStatus:
                ServerStatus::Inactive,
        );
    }
}
