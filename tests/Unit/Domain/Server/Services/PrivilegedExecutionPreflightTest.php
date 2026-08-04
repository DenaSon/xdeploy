<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Server\Services;

use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Tests\TestCase;

final class PrivilegedExecutionPreflightTest extends TestCase
{
    public function test_it_detects_direct_root_access(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: "0\n",
                    exitCode: 0,
                ),
            );

        $preflight = new PrivilegedExecutionPreflight(
            $ssh,
        );

        $this->assertSame(
            PrivilegedExecutionMode::Root,
            $preflight->detect(),
        );
    }

    public function test_it_detects_passwordless_sudo_access(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: "1000\n",
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'sudo -n id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: "0\n",
                    exitCode: 0,
                ),
            );

        $preflight = new PrivilegedExecutionPreflight(
            $ssh,
        );

        $this->assertSame(
            PrivilegedExecutionMode::PasswordlessSudo,
            $preflight->detect(),
        );
    }

    public function test_it_rejects_a_session_without_privileged_access(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: "1000\n",
                    exitCode: 0,
                ),
            );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'sudo -n id -u',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    output: 'sudo: a password is required',
                    exitCode: 1,
                ),
            );

        $this->expectException(
            RootPrivilegesRequiredException::class,
        );

        new PrivilegedExecutionPreflight(
            $ssh,
        )->detect();
    }
}
