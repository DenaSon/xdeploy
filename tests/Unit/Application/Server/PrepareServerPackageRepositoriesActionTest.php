<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Server;

use App\Application\Server\Actions\PrepareServerPackageRepositoriesAction;
use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Exceptions\ServerPackageRepositoryException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Linux\Packages\PackageManagerLockRetryCommand;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class PrepareServerPackageRepositoriesActionTest extends TestCase
{
    public function test_arvan_ubuntu_uses_reachable_mirror_and_forces_apt_ipv4(): void
    {
        config()->set(
            'cloud.providers.arvan.package_repositories.ubuntu_mirror',
            'https://mirror.arvancloud.ir/ubuntu',
        );

        $ssh = $this->ssh();

        $this->expectRootPreflight(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                Mockery::on(
                    static fn (string $command): bool => str_contains(
                        $command,
                        "mirror='https://mirror.arvancloud.ir/ubuntu'",
                    )
                        && str_contains(
                            $command,
                            'Acquire::ForceIPv4=true',
                        )
                        && str_contains(
                            $command,
                            '/etc/apt/sources.list.d/ubuntu.sources',
                        )
                        && str_contains(
                            $command,
                            '.xdeploy-original',
                        )
                        && str_contains(
                            $command,
                            'apt-get',
                        )
                        && str_contains(
                            $command,
                            PackageManagerLockRetryCommand::BUSY_MARKER,
                        ),
                ),
                SSHTimeout::SYSTEM_PACKAGE_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult(
                    output: '[xDeploy][repositories] Ubuntu package repositories are ready.',
                    exitCode: 0,
                ),
            );

        $this->action(
            $ssh,
        )->handle(
            server: new Server([
                'cloud_provider' => 'arvan',
            ]),
            operatingSystem: $this->ubuntu(),
        );

        $this->assertTrue(true);
    }

    public function test_user_provided_server_does_not_mutate_package_repositories(): void
    {
        $ssh = $this->ssh();

        $ssh->shouldNotReceive(
            'executeWithResult',
        );

        $this->action(
            $ssh,
        )->handle(
            server: new Server,
            operatingSystem: $this->ubuntu(),
        );

        $this->assertTrue(true);
    }

    public function test_non_ubuntu_arvan_server_does_not_mutate_package_repositories(): void
    {
        $ssh = $this->ssh();

        $ssh->shouldNotReceive(
            'executeWithResult',
        );

        $this->action(
            $ssh,
        )->handle(
            server: new Server([
                'cloud_provider' => 'arvan',
            ]),
            operatingSystem: new OperatingSystemInfo(
                id: 'debian',
                name: 'Debian GNU/Linux',
                versionId: '12',
                prettyName: 'Debian GNU/Linux 12',
            ),
        );

        $this->assertTrue(true);
    }

    public function test_repository_lock_exhaustion_uses_package_manager_busy_exception(): void
    {
        $ssh = $this->ssh();

        $this->expectRootPreflight(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                Mockery::type('string'),
                SSHTimeout::SYSTEM_PACKAGE_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult(
                    output: PackageManagerLockRetryCommand::BUSY_MARKER,
                    exitCode: 75,
                ),
            );

        $this->expectException(
            SystemPackageManagerBusyException::class,
        );

        $this->action(
            $ssh,
        )->handle(
            server: new Server([
                'cloud_provider' => 'arvan',
            ]),
            operatingSystem: $this->ubuntu(),
        );
    }

    public function test_repository_failure_reports_only_the_controlled_stage(): void
    {
        $ssh = $this->ssh();

        $this->expectRootPreflight(
            $ssh,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                Mockery::type('string'),
                SSHTimeout::SYSTEM_PACKAGE_INSTALL,
                false,
            )
            ->andReturn(
                new SSHResult(
                    output: implode(
                        "\n",
                        [
                            'large apt output that must not enter the exception',
                            '[xDeploy][repositories][error] stage=apt_update exit_code=100',
                        ],
                    ),
                    exitCode: 100,
                ),
            );

        $this->expectException(
            ServerPackageRepositoryException::class,
        );

        $this->expectExceptionMessage(
            'Server package repository preparation failed during [apt_update].',
        );

        $this->action(
            $ssh,
        )->handle(
            server: new Server([
                'cloud_provider' => 'arvan',
            ]),
            operatingSystem: $this->ubuntu(),
        );
    }

    private function action(
        SSHConnectionInterface $ssh,
    ): PrepareServerPackageRepositoriesAction {
        $preflight = new PrivilegedExecutionPreflight(
            $ssh,
        );

        return new PrepareServerPackageRepositoriesAction(
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: $preflight,
            ),
        );
    }

    private function expectRootPreflight(
        SSHConnectionInterface&MockInterface $ssh,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
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
    }

    private function ubuntu(): OperatingSystemInfo
    {
        return new OperatingSystemInfo(
            id: 'ubuntu',
            name: 'Ubuntu',
            versionId: '26.04',
            prettyName: 'Ubuntu 26.04 LTS',
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
}
