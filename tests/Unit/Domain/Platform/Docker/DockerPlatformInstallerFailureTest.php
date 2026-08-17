<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Docker;

use App\Domain\Platform\Docker\DockerPlatform;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
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

final class DockerPlatformInstallerFailureTest extends TestCase
{
    public function test_it_reports_the_sanitized_installer_stage_on_failure(): void
    {
        $this->expectException(
            PlatformInstallationException::class,
        );

        $this->expectExceptionMessage(
            'Docker installation failed during installer stage [install_docker_ce].',
        );

        $this->installWithResult(
            new SSHResult(
                "apt output that must not be exposed\n[xDeploy][docker][error] stage=install_docker_ce exit_code=100\n",
                100,
            ),
        );
    }

    public function test_it_reports_a_server_side_installer_timeout_without_exposing_output(): void
    {
        $this->expectException(
            PlatformInstallationException::class,
        );

        $this->expectExceptionMessage(
            'Docker installation exceeded the server-side installer time limit.',
        );

        $this->installWithResult(
            new SSHResult(
                'sensitive apt output',
                124,
            ),
        );
    }

    public function test_it_reports_package_manager_lock_exhaustion_separately(): void
    {
        $this->expectException(
            SystemPackageManagerBusyException::class,
        );

        $this->installWithResult(
            new SSHResult(
                PackageManagerLockRetryCommand::BUSY_MARKER,
                75,
            ),
        );
    }

    private function installWithResult(
        SSHResult $installerResult,
    ): void {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'if [ -r /etc/os-release ]; then cat /etc/os-release; '
                .'elif [ -r /usr/lib/os-release ]; then cat /usr/lib/os-release; '
                .'else exit 1; fi',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    "ID=ubuntu\nNAME=\"Ubuntu\"\nVERSION_ID=\"26.04\"\nPRETTY_NAME=\"Ubuntu 26.04 LTS\"\nID_LIKE=debian\n",
                    0,
                ),
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
                'xdeploy-docker-installer-command',
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
                Mockery::on(
                    static fn (string $command): bool => str_contains(
                        $command,
                        'xdeploy-docker-installer-command',
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
            ->andReturn(
                $installerResult,
            );

        $platform = new DockerPlatform(
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

        $platform->install();
    }
}
