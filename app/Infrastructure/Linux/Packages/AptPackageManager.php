<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Packages;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Exceptions\InvalidSystemPackageException;
use App\Domain\Server\Exceptions\SystemPackageInstallationException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class AptPackageManager implements SystemPackageManager
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function isInstalled(
        string $package,
    ): bool {
        $package = $this->normalizePackage($package);

        $result = $this->ssh->executeWithResult(
            command: sprintf(
                "dpkg-query -W -f='\${Status}' -- %s 2>/dev/null",
                $this->quoteForPosixShell($package),
            ),
            timeout: SSHTimeout::QUICK,
        );

        return $result->successful()
            && trim($result->output) === 'install ok installed';
    }

    /**
     * @param  list<string>  $packages
     */
    public function install(
        array $packages,
    ): void {
        $packages = $this->normalizePackages($packages);

        if ($packages === []) {
            return;
        }

        $packageArguments = implode(
            ' ',
            array_map(
                fn (string $package): string => $this->quoteForPosixShell(
                    $package,
                ),
                $packages,
            ),
        );

        $command = sprintf(
            <<<'COMMAND'
LC_ALL=C apt-get -o DPkg::Lock::Timeout=60 update \
    && DEBIAN_FRONTEND=noninteractive LC_ALL=C apt-get \
        -o DPkg::Lock::Timeout=60 \
        install -y -- %s
COMMAND,
            $packageArguments,
        );

        $result = $this->privileged->executeWithResult(
            command: PackageManagerLockRetryCommand::wrap(
                $command,
            ),
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        if (! $result->successful()) {
            if (
                str_contains(
                    $result->output,
                    PackageManagerLockRetryCommand::BUSY_MARKER,
                )
            ) {
                throw new SystemPackageManagerBusyException;
            }

            throw new SystemPackageInstallationException(
                sprintf(
                    'Failed to install system packages [%s].',
                    implode(', ', $packages),
                ),
            );
        }

        $this->verifyInstalledPackages($packages);
    }

    /**
     * @param  list<string>  $packages
     */
    private function verifyInstalledPackages(
        array $packages,
    ): void {
        foreach ($packages as $package) {
            if ($this->isInstalled($package)) {
                continue;
            }

            throw new SystemPackageInstallationException(
                sprintf(
                    'System package [%s] installation verification failed.',
                    $package,
                ),
            );
        }
    }

    /**
     * @param  array<int, string>  $packages
     * @return list<string>
     */
    private function normalizePackages(
        array $packages,
    ): array {
        $normalized = [];

        foreach ($packages as $package) {
            $normalized[] = $this->normalizePackage(
                $package,
            );
        }

        return array_values(
            array_unique($normalized),
        );
    }

    private function normalizePackage(
        string $package,
    ): string {
        $package = trim($package);

        $this->validatePackageName($package);

        return $package;
    }

    private function validatePackageName(
        string $package,
    ): void {
        if (
            $package === ''
            || preg_match(
                '/^[a-z0-9][a-z0-9+.-]*$/',
                $package,
            ) !== 1
        ) {
            throw new InvalidSystemPackageException(
                $package,
            );
        }
    }

    /**
     * Quotes a value for the remote POSIX shell.
     *
     * xDeploy can run on Windows while remote commands are executed
     * using Bash on the target Linux server.
     */
    private function quoteForPosixShell(
        string $value,
    ): string {
        return "'"
            .str_replace(
                "'",
                "'\"'\"'",
                $value,
            )
            ."'";
    }
}
