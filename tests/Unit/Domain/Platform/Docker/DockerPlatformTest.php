<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Docker;

use App\Domain\Platform\Docker\DockerPlatform;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\Linux\Packages\PackageManagerLockRetryCommand;
use App\Infrastructure\Linux\Services\OperatingSystemInspector;
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

        $this->expectRunningInspection(
            $ssh,
        );

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

    public function test_it_installs_docker_on_ubuntu_26_04_using_verified_installer(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $installerCommand = 'xdeploy-docker-installer-command';

        $this->expectOperatingSystem(
            ssh: $ssh,
            id: 'ubuntu',
            versionId: '26.04',
            name: 'Ubuntu',
            prettyName: 'Ubuntu 26.04 LTS',
        );

        $installerSource
            ->shouldReceive('buildExecutionCommand')
            ->once()
            ->with(
                'docker/debian-family.sh',
                (string) config(
                    'xdeploy.installers.docker.debian_family.sha256',
                ),
            )
            ->andReturn(
                $installerCommand,
            );

        $this->expectRootPreflight(
            $ssh,
        );

        $this->expectLockAwareInstallerCommand(
            ssh: $ssh,
            installerCommand: $installerCommand,
            result: new SSHResult(
                'Docker installed.',
                0,
            ),
        );

        $this->expectRunningInspection(
            $ssh,
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_unsupported_operating_system_before_preparing_installer(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $this->expectOperatingSystem(
            ssh: $ssh,
            id: 'ubuntu',
            versionId: '20.04',
            name: 'Ubuntu',
            prettyName: 'Ubuntu 20.04 LTS',
        );

        $installerSource->shouldNotReceive(
            'buildExecutionCommand',
        );

        $this->expectException(
            PlatformInstallationException::class,
        );

        $this->expectExceptionMessage(
            'The xDeploy Docker installer does not support [Ubuntu 20.04 LTS].',
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();
    }

    public function test_it_throws_when_docker_installer_command_fails(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $installerCommand = 'xdeploy-docker-installer-command';

        $this->expectOperatingSystem(
            ssh: $ssh,
            id: 'ubuntu',
            versionId: '24.04',
            name: 'Ubuntu',
            prettyName: 'Ubuntu 24.04 LTS',
        );

        $installerSource
            ->shouldReceive('buildExecutionCommand')
            ->once()
            ->with(
                'docker/debian-family.sh',
                (string) config(
                    'xdeploy.installers.docker.debian_family.sha256',
                ),
            )
            ->andReturn(
                $installerCommand,
            );

        $this->expectRootPreflight(
            $ssh,
        );

        $this->expectLockAwareInstallerCommand(
            ssh: $ssh,
            installerCommand: $installerCommand,
            result: new SSHResult(
                'Installation failed.',
                1,
            ),
        );

        $this->expectException(
            PlatformInstallationException::class,
        );

        $this->expectExceptionMessage(
            'Docker installation using the xDeploy installer failed.',
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();
    }

    public function test_it_starts_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight(
            $ssh,
        );

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

        $this->expectRunningInspection(
            $ssh,
        );

        $this->platform($ssh)->start();

        $this->addToAssertionCount(1);
    }

    public function test_it_stops_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight(
            $ssh,
        );

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

        $this->expectStoppedInspection(
            $ssh,
        );

        $this->platform($ssh)->stop();

        $this->addToAssertionCount(1);
    }

    public function test_it_restarts_docker_using_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight(
            $ssh,
        );

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

        $this->expectRunningInspection(
            $ssh,
        );

        $this->platform($ssh)->restart();

        $this->addToAssertionCount(1);
    }

    private function expectOperatingSystem(
        SSHConnectionInterface $ssh,
        string $id,
        string $versionId,
        string $name,
        string $prettyName,
    ): void {
        $idLike = $id === 'ubuntu'
            ? 'ID_LIKE=debian'.PHP_EOL
            : '';

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'if [ -r /etc/os-release ]; then cat /etc/os-release; '
                .'elif [ -r /usr/lib/os-release ]; then cat /usr/lib/os-release; '
                .'else exit 1; fi',
                20,
            )
            ->andReturn(
                new SSHResult(
                    sprintf(
                        "ID=%s\nNAME=\"%s\"\nVERSION_ID=\"%s\"\nPRETTY_NAME=\"%s\"\n%s",
                        $id,
                        $name,
                        $versionId,
                        $prettyName,
                        $idLike,
                    ),
                    0,
                ),
            );
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

    private function expectLockAwareInstallerCommand(
        SSHConnectionInterface $ssh,
        string $installerCommand,
        SSHResult $result,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool => str_contains(
                        $command,
                        $installerCommand,
                    )
                        && str_contains(
                            $command,
                            'PACKAGE_MANAGER_MAX_ATTEMPTS=4',
                        )
                        && str_contains(
                            $command,
                            PackageManagerLockRetryCommand::BUSY_MARKER,
                        ),
                ),
                SSHTimeout::DOCKER_INSTALL,
                true,
            )
            ->andReturn($result);
    }

    private function expectRunningInspection(
        SSHConnectionInterface $ssh,
    ): void {
        $this->expectDockerBinary(
            $ssh,
        );

        $this->expectDockerVersion(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active docker',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'active',
                    0,
                ),
            );
    }

    private function expectStoppedInspection(
        SSHConnectionInterface $ssh,
    ): void {
        $this->expectDockerBinary(
            $ssh,
        );

        $this->expectDockerVersion(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active docker',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'inactive',
                    3,
                ),
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
                new SSHResult(
                    '/usr/bin/docker',
                    0,
                ),
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
        ?InstallerSourceInterface $installerSource = null,
    ): DockerPlatform {
        $installerSource ??= Mockery::mock(
            InstallerSourceInterface::class,
        );

        return new DockerPlatform(
            ssh: $ssh,

            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,

                preflight: new PrivilegedExecutionPreflight(
                    $ssh,
                ),
            ),

            operatingSystem: new OperatingSystemInspector(
                $ssh,
            ),

            installerSource: $installerSource,
        );
    }
}
