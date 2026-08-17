<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Linux\Packages;

use App\Domain\Server\Exceptions\InvalidSystemPackageException;
use App\Domain\Server\Exceptions\SystemPackageInstallationException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Linux\Packages\AptPackageManager;
use App\Infrastructure\Linux\Packages\PackageManagerLockRetryCommand;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;
use Mockery;
use Tests\TestCase;

final class AptPackageManagerTest extends TestCase
{
    public function test_it_detects_an_installed_package(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                "dpkg-query -W -f='\${Status}' -- 'curl' 2>/dev/null",
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'install ok installed',
                    0,
                ),
            );

        $installed = $this->manager($ssh)->isInstalled(
            'curl',
        );

        $this->assertTrue($installed);
    }

    public function test_it_reports_a_missing_package_as_not_installed(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->with(
                "dpkg-query -W -f='\${Status}' -- 'curl' 2>/dev/null",
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('', 1),
            );

        $installed = $this->manager($ssh)->isInstalled(
            'curl',
        );

        $this->assertFalse($installed);
    }

    public function test_it_installs_normalized_unique_packages_using_lock_aware_privileged_executor(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $this->expectPackageInstallCommand(
            ssh: $ssh,
            packages: [
                'curl',
                'ca-certificates',
            ],
            result: new SSHResult(
                'Packages installed.',
                0,
            ),
        );

        $this->expectInstalledPackage(
            ssh: $ssh,
            package: 'curl',
        );

        $this->expectInstalledPackage(
            ssh: $ssh,
            package: 'ca-certificates',
        );

        $this->manager($ssh)->install([
            ' curl ',
            'ca-certificates',
            'curl',
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_does_nothing_when_package_list_is_empty(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh->shouldNotReceive(
            'executeWithResult',
        );

        $this->manager($ssh)->install([]);

        $this->addToAssertionCount(1);
    }

    public function test_it_throws_a_specific_exception_when_package_manager_stays_busy(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $this->expectPackageInstallCommand(
            ssh: $ssh,
            packages: ['curl'],
            result: new SSHResult(
                PackageManagerLockRetryCommand::BUSY_MARKER,
                75,
            ),
        );

        $this->expectException(
            SystemPackageManagerBusyException::class,
        );

        $this->manager($ssh)->install([
            'curl',
        ]);
    }

    public function test_it_does_not_misclassify_a_generic_package_failure_as_lock_contention(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $this->expectPackageInstallCommand(
            ssh: $ssh,
            packages: ['curl'],
            result: new SSHResult(
                'Unable to install package.',
                100,
            ),
        );

        $this->expectException(
            SystemPackageInstallationException::class,
        );

        $this->expectExceptionMessage(
            'Failed to install system packages [curl].',
        );

        $this->manager($ssh)->install([
            'curl',
        ]);
    }

    public function test_it_throws_when_installation_verification_fails(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $this->expectRootPreflight($ssh);

        $this->expectPackageInstallCommand(
            ssh: $ssh,
            packages: ['curl'],
            result: new SSHResult(
                'Package command completed.',
                0,
            ),
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                "dpkg-query -W -f='\${Status}' -- 'curl' 2>/dev/null",
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult('', 1),
            );

        $this->expectException(
            SystemPackageInstallationException::class,
        );

        $this->expectExceptionMessage(
            'System package [curl] installation verification failed.',
        );

        $this->manager($ssh)->install([
            'curl',
        ]);
    }

    public function test_it_rejects_an_invalid_package_name(): void
    {
        $ssh = Mockery::mock(
            SSHConnectionInterface::class,
        );

        $ssh->shouldNotReceive(
            'executeWithResult',
        );

        $this->expectException(
            InvalidSystemPackageException::class,
        );

        $this->manager($ssh)->install([
            'curl; rm -rf /',
        ]);
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

    /**
     * @param  list<string>  $packages
     */
    private function expectPackageInstallCommand(
        SSHConnectionInterface $ssh,
        array $packages,
        SSHResult $result,
    ): void {
        $expectedArguments = implode(
            ' ',
            array_map(
                static fn (string $package): string => "'{$package}'",
                $packages,
            ),
        );

        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                Mockery::on(
                    static fn (string $command): bool => str_contains(
                        $command,
                        'PACKAGE_MANAGER_MAX_ATTEMPTS=4',
                    )
                        && str_contains(
                            $command,
                            PackageManagerLockRetryCommand::BUSY_MARKER,
                        )
                        && str_contains(
                            $command,
                            'DPkg::Lock::Timeout=60',
                        )
                        && str_contains(
                            $command,
                            "install -y -- {$expectedArguments}",
                        ),
                ),
                SSHTimeout::SYSTEM_PACKAGE_INSTALL,
                false,
            )
            ->andReturn($result);
    }

    private function expectInstalledPackage(
        SSHConnectionInterface $ssh,
        string $package,
    ): void {
        $ssh
            ->shouldReceive('executeWithResult')
            ->once()
            ->ordered()
            ->with(
                sprintf(
                    "dpkg-query -W -f='\${Status}' -- '%s' 2>/dev/null",
                    $package,
                ),
                SSHTimeout::QUICK,
            )
            ->andReturn(
                new SSHResult(
                    'install ok installed',
                    0,
                ),
            );
    }

    private function manager(
        SSHConnectionInterface $ssh,
    ): AptPackageManager {
        return new AptPackageManager(
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
