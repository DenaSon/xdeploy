<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Caddy;

use App\Domain\Platform\Caddy\CaddyPlatform;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
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

final class CaddyPlatformTest extends TestCase
{
    public function test_it_exposes_caddy_platform_metadata_and_dependencies(): void
    {
        $platform = $this->platform(
            Mockery::mock(
                SSHConnectionInterface::class,
            ),
        );

        $this->assertSame(
            PlatformType::Caddy,
            $platform->type(),
        );

        $this->assertSame(
            'Caddy',
            $platform->name(),
        );

        $this->assertSame(
            [],
            $platform->dependencies(),
        );

        $this->assertSame(
            [
                'apt-transport-https',
                'ca-certificates',
                'curl',
                'debian-archive-keyring',
                'debian-keyring',
                'gnupg',
            ],
            $platform->systemPackages(),
        );
    }

    public function test_it_reports_caddy_as_not_installed_when_binary_is_missing(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                'command -v caddy >/dev/null 2>&1',
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

    public function test_it_reports_caddy_as_running(): void
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
            '2.10.2',
            $info->metadata['version'],
        );

        $this->assertSame(
            'active',
            $info->metadata['service_state'],
        );
    }

    public function test_it_reports_caddy_as_installed_when_service_is_inactive(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectStoppedInspection(
            $ssh,
        );

        $info = $this->platform($ssh)->inspect();

        $this->assertSame(
            PlatformState::Installed,
            $info->state,
        );
    }

    public function test_it_installs_caddy_on_ubuntu_26_04_using_verified_installer(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $installerCommand = 'xdeploy-caddy-installer-command';

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
                'caddy/debian-family.sh',
                (string) config(
                    'xdeploy.installers.caddy.debian_family.sha256',
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
                SSHTimeout::CADDY_INSTALL,
                true,
            )
            ->andReturn(
                new SSHResult(
                    'Caddy installed.',
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
            'The xDeploy Caddy installer does not support [Ubuntu 20.04 LTS].',
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();
    }

    public function test_it_throws_when_caddy_installer_command_fails(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $installerSource = Mockery::mock(
            InstallerSourceInterface::class,
        );

        $installerCommand = 'xdeploy-caddy-installer-command';

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
                'caddy/debian-family.sh',
                (string) config(
                    'xdeploy.installers.caddy.debian_family.sha256',
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
                SSHTimeout::CADDY_INSTALL,
                true,
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
            'Caddy installation using the xDeploy installer failed.',
        );

        $this
            ->platform(
                ssh: $ssh,
                installerSource: $installerSource,
            )
            ->install();
    }

    public function test_it_starts_caddy_using_privileged_executor(): void
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
                'systemctl enable --now caddy',
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

    public function test_it_stops_caddy_using_privileged_executor(): void
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
                'systemctl stop caddy',
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

    public function test_it_restarts_caddy_using_privileged_executor(): void
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
                'systemctl restart caddy',
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

    private function expectRunningInspection(
        SSHConnectionInterface $ssh,
    ): void {
        $this->expectCaddyBinary(
            $ssh,
        );

        $this->expectCaddyVersion(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active caddy',
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
        $this->expectCaddyBinary(
            $ssh,
        );

        $this->expectCaddyVersion(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'systemctl is-active caddy',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'inactive',
                    3,
                ),
            );
    }

    private function expectCaddyBinary(
        SSHConnectionInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'command -v caddy >/dev/null 2>&1',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    '/usr/bin/caddy',
                    0,
                ),
            );
    }

    private function expectCaddyVersion(
        SSHConnectionInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                'caddy version',
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'v2.10.2 h1:example',
                    0,
                ),
            );
    }

    private function platform(
        SSHConnectionInterface $ssh,
        ?InstallerSourceInterface $installerSource = null,
    ): CaddyPlatform {
        $installerSource ??= Mockery::mock(
            InstallerSourceInterface::class,
        );

        return new CaddyPlatform(
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
