<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Server\Services;

use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Tests\TestCase;

final class PrivilegedCommandExecutorTest extends TestCase
{
    public function test_it_executes_command_directly_for_root(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
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
                new SSHResult('0', 0),
            );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'apt-get update',
                SSHTimeout::NORMAL,
                false,
            )
            ->andReturn(
                new SSHResult('completed', 0),
            );

        $executor = $this->executor($ssh);

        $result = $executor->executeWithResult(
            command: 'apt-get update',
            timeout: SSHTimeout::NORMAL,
        );

        $this->assertTrue(
            $result->successful(),
        );

        $this->assertSame(
            'completed',
            $result->output,
        );

        $this->assertSame(
            0,
            $result->exitCode,
        );
    }

    public function test_it_wraps_complete_command_with_bash_for_passwordless_sudo(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
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
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                "sudo -n -- bash -lc 'apt-get update && apt-get install -y curl'",
                SSHTimeout::SYSTEM_PACKAGE_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult('completed', 0),
            );

        $executor = $this->executor($ssh);

        $result = $executor->executeWithResult(
            command: 'apt-get update && apt-get install -y curl',
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        $this->assertTrue(
            $result->successful(),
        );

        $this->assertSame(
            'completed',
            $result->output,
        );
    }

    public function test_it_safely_escapes_single_quotes_in_privileged_command(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
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

        $expectedCommand = <<<'COMMAND'
sudo -n -- bash -lc 'echo '"'"'ready'"'"''
COMMAND;

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                $expectedCommand,
                SSHTimeout::NORMAL,
                false,
            )
            ->andReturn(
                new SSHResult('ready', 0),
            );

        $executor = $this->executor($ssh);

        $result = $executor->executeWithResult(
            command: "echo 'ready'",
            timeout: SSHTimeout::NORMAL,
        );

        $this->assertTrue(
            $result->successful(),
        );

        $this->assertSame(
            'ready',
            $result->output,
        );
    }

    public function test_it_forwards_sensitive_flag_to_ssh_connection(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
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
                new SSHResult('0', 0),
            );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'secret-command',
                SSHTimeout::NORMAL,
                true,
            )
            ->andReturn(
                new SSHResult('', 0),
            );

        $executor = $this->executor($ssh);

        $result = $executor->executeWithResult(
            command: 'secret-command',
            timeout: SSHTimeout::NORMAL,
            sensitive: true,
        );

        $this->assertTrue(
            $result->successful(),
        );
    }

    public function test_it_throws_when_root_and_passwordless_sudo_are_unavailable(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
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
                new SSHResult(
                    'sudo: a password is required',
                    1,
                ),
            );

        /*
         * No installation command may be executed after a failed
         * privilege preflight.
         */
        $ssh
            ->shouldNotReceive('executeWithResult')
            ->with(
                Mockery::on(
                    static fn (string $command): bool => str_contains(
                        $command,
                        'apt-get update',
                    ),
                ),
                Mockery::any(),
                Mockery::any(),
            );

        $executor = $this->executor($ssh);

        $this->expectException(
            RootPrivilegesRequiredException::class,
        );

        $executor->executeWithResult(
            command: 'apt-get update',
            timeout: SSHTimeout::NORMAL,
        );
    }

    private function executor(
        SSHConnectionInterface $ssh,
    ): PrivilegedCommandExecutor {
        return new PrivilegedCommandExecutor(
            ssh: $ssh,
            preflight: new PrivilegedExecutionPreflight(
                $ssh,
            ),
        );
    }
}
