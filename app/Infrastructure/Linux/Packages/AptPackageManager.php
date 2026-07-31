<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Packages;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Exceptions\InvalidSystemPackageException;
use App\Domain\Server\Exceptions\SystemPackageInstallationException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class AptPackageManager implements SystemPackageManager
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
    ) {}

    public function isInstalled(string $package): bool
    {
        $this->validatePackageName($package);

        $result = $this->ssh->executeWithResult(
            sprintf(
                "dpkg-query -W -f='\${Status}' -- %s 2>/dev/null",
                escapeshellarg($package),
            ),
        );

        return $result->successful()
            && trim($result->output) === 'install ok installed';
    }

    /**
     * @param  list<string>  $packages
     */
    public function install(array $packages): void
    {
        $packages = $this->normalizePackages($packages);

        if ($packages === []) {
            return;
        }

        $this->preflight->ensureRoot();

        $packageArguments = implode(
            ' ',
            array_map(
                static fn (string $package): string => escapeshellarg($package),
                $packages,
            ),
        );

        $result = $this->ssh->executeWithResult(
            command: sprintf(
                'apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y -- %s',
                $packageArguments,
            ),
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        if (! $result->successful()) {
            throw new SystemPackageInstallationException(
                sprintf(
                    'Failed to install system packages [%s].',
                    implode(', ', $packages),
                ),
            );
        }

        foreach ($packages as $package) {
            if (! $this->isInstalled($package)) {
                throw new SystemPackageInstallationException(
                    sprintf(
                        'System package [%s] installation verification failed.',
                        $package,
                    ),
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $packages
     * @return list<string>
     */
    private function normalizePackages(array $packages): array
    {
        $normalized = [];

        foreach ($packages as $package) {
            $package = trim($package);

            $this->validatePackageName($package);

            $normalized[] = $package;
        }

        return array_values(
            array_unique($normalized),
        );
    }

    private function validatePackageName(string $package): void
    {
        if (
            $package === ''
            || preg_match('/^[a-z0-9][a-z0-9+.-]*$/', $package) !== 1
        ) {
            throw new InvalidSystemPackageException($package);
        }
    }
}
