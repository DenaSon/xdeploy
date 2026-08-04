<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Models\User;
use App\Support\SSH\SSHTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class VerifyCloudServerSshReadinessActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_activates_a_server_with_direct_root_access(): void
    {
        $server = $this->createServer();

        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->with(
                Mockery::on(
                    fn (Server $value): bool => $value->is($server),
                ),
            )
            ->andReturnTrue();

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('0', 0),
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        $mode = $this->action($ssh)->handle(
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

        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->andReturnTrue();

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('1000', 0),
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
                new SSHResult('0', 0),
            );

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        $mode = $this->action($ssh)->handle(
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

        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('connect')
            ->once()
            ->andReturnFalse();

        $ssh
            ->shouldReceive('disconnect')
            ->once();

        try {
            $this->action($ssh)->handle(
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
        return new VerifyCloudServerSshReadinessAction(
            ssh: $ssh,

            preflight: new PrivilegedExecutionPreflight(
                $ssh,
            ),
        );
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

                'authentication_type' => AuthenticationType::Password,

                'credential' => 'temporary-generated-password',

                'cloud_provider' => 'arvan',

                'cloud_server_id' => 'provider-server-id',

                'cloud_region' => 'eu-west1-a',

                'provisioned_at' => now(),
            ],

            explicitStatus: ServerStatus::Inactive,
        );
    }
}
