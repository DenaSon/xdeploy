<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\DockerCompose;

use App\Domain\Platform\DockerCompose\DockerComposePlatform;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\Linux\Services\OperatingSystemInspector;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Tests\TestCase;

final class DockerComposePlatformTest extends TestCase
{
    public function test_it_installs_compose_on_debian_12_using_verified_docker_stack_installer(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $installerCommand = 'xdeploy-docker-stack-installer';

        $this->expectOperatingSystem(
            ssh: $ssh,
            id: 'debian',
            versionId: '12',
            name: 'Debian GNU/Linux',
            prettyName: 'Debian GNU/Linux 12 (bookworm)',
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

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                $installerCommand,
                SSHTimeout::DOCKER_INSTALL,
                true,
            )
            ->andReturn(
                new SSHResult(
                    'Docker stack installed.',
                    0,
                ),
            );

        $this->expectInstalledInspection(
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
            'Automatic Docker Compose installation does not support [Ubuntu 20.04 LTS].',
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();
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

    private function expectInstalledInspection(
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

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'timeout --signal=TERM --kill-after=2 5 docker compose version 2>/dev/null',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'Docker Compose version v2.35.1',
                    0,
                ),
            );
    }

    private function platform(
        SSHConnectionInterface $ssh,
        InstallerSourceInterface $installerSource,
    ): DockerComposePlatform {
        return new DockerComposePlatform(
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
