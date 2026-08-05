<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Docker;

use App\Domain\Platform\Docker\DockerPlatform;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Tests\TestCase;

final class DockerPlatformTest extends TestCase
{
    public function test_it_reports_docker_as_not_installed_when_binary_is_missing(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'command -v docker >/dev/null 2>&1',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('', 1),
            );

        $info = $this->platform($ssh)->inspect();

        $this->assertSame(
            PlatformState::NotInstalled,
            $info->state,
        );
    }

    public function test_it_reports_docker_as_running(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRunningInspection($ssh);

        $info = $this->platform($ssh)->inspect();

        $this->assertSame(
            PlatformState::Running,
            $info->state,
        );

        $this->assertSame(
            '29.1.3',
            $info->metadata['version'],
        );

        $this->assertSame(
            'active',
            $info->metadata['service_state'],
        );
    }

    public function test_it_installs_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool => trim($command)
                        === 'curl -fsSL https://get.docker.com | sh',
                ),
                SSHTimeout::DOCKER_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult('Docker installed.', 0),
            );

        $this->expectRunningInspection($ssh);

        $this->platform($ssh)->install();

        $this->addToAssertionCount(1);
    }

    public function test_it_throws_when_docker_installation_command_fails(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool => trim($command)
                        === 'curl -fsSL https://get.docker.com | sh',
                ),
                SSHTimeout::DOCKER_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult(
                    'Installation failed.',
                    1,
                ),
            );

        $this->expectException(
            PlatformInstallationException::class,
        );

        $this->expectExceptionMessage(
            'Docker installation failed.',
        );

        $this->platform($ssh)->install();
    }

    public function test_it_starts_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl enable --now docker',
                SSHTimeout::NORMAL,
                false,
            )
            ->andReturn(
                new SSHResult('', 0),
            );

        $this->expectRunningInspection($ssh);

        $this->platform($ssh)->start();

        $this->addToAssertionCount(1);
    }

    public function test_it_stops_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl stop docker',
                SSHTimeout::NORMAL,
                false,
            )
            ->andReturn(
                new SSHResult('', 0),
            );

        $this->expectStoppedInspection($ssh);

        $this->platform($ssh)->stop();

        $this->addToAssertionCount(1);
    }

    public function test_it_restarts_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl restart docker',
                SSHTimeout::NORMAL,
                false,
            )
            ->andReturn(
                new SSHResult('', 0),
            );

        $this->expectRunningInspection($ssh);

        $this->platform($ssh)->restart();

        $this->addToAssertionCount(1);
    }

    private function expectRootPreflight(
        SSHConnectionInterface $ssh,
    ): void {
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
    }

    private function expectRunningInspection(
        SSHConnectionInterface $ssh,
    ): void {
        $this->expectDockerBinary($ssh);
        $this->expectDockerVersion($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active docker',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('active', 0),
            );
    }

    private function expectStoppedInspection(
        SSHConnectionInterface $ssh,
    ): void {
        $this->expectDockerBinary($ssh);
        $this->expectDockerVersion($ssh);

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active docker',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('inactive', 3),
            );
    }

    private function expectDockerBinary(
        SSHConnectionInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'command -v docker >/dev/null 2>&1',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('/usr/bin/docker', 0),
            );
    }

    private function expectDockerVersion(
        SSHConnectionInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'docker --version',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'Docker version 29.1.3, build abc123',
                    0,
                ),
            );
    }

    private function platform(
        SSHConnectionInterface $ssh,
    ): DockerPlatform {
        return new DockerPlatform(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    $ssh,
                ),
            ),
        );
    }
}
