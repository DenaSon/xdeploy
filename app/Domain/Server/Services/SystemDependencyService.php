<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Shared\DTOs\InstallMessage;
use App\Domain\Shared\DTOs\InstallReport;
use RuntimeException;

final readonly class SystemDependencyService
{
    public function __construct(
        private SystemPackageManager $packageManager,
    ) {}

    /**
     * Ensure that all required system packages are installed.
     *
     * @param  list<string>  $packages
     */
    public function ensure(array $packages): InstallReport
    {
        $packages = $this->normalize($packages);

        if ($packages === []) {
            return new InstallReport;
        }

        $missingPackages = $this->missing($packages);

        if ($missingPackages !== []) {
            $this->packageManager->install($missingPackages);

            $this->verifyInstalled($missingPackages);
        }

        return $this->buildReport(
            packages: $packages,
            initiallyMissing: $missingPackages,
        );
    }

    /**
     * Return packages that are not currently installed.
     *
     * @param  list<string>  $packages
     * @return list<string>
     */
    public function missing(array $packages): array
    {
        $missingPackages = [];

        foreach ($this->normalize($packages) as $package) {
            if (! $this->packageManager->isInstalled($package)) {
                $missingPackages[] = $package;
            }
        }

        return $missingPackages;
    }

    /**
     * Determine whether all required packages are installed.
     *
     * @param  list<string>  $packages
     */
    public function isSatisfied(array $packages): bool
    {
        return $this->missing($packages) === [];
    }

    /**
     * Verify that every requested package was installed successfully.
     *
     * @param  list<string>  $packages
     */
    private function verifyInstalled(array $packages): void
    {
        $unverifiedPackages = $this->missing($packages);

        if ($unverifiedPackages === []) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'System package installation verification failed: %s.',
                implode(', ', $unverifiedPackages),
            ),
        );
    }

    /**
     * @param  list<string>  $packages
     * @param  list<string>  $initiallyMissing
     */
    private function buildReport(
        array $packages,
        array $initiallyMissing,
    ): InstallReport {
        $report = new InstallReport;

        foreach ($packages as $package) {
            $wasInstalled = in_array(
                $package,
                $initiallyMissing,
                true,
            );

            $report = $report->with(
                new InstallMessage(
                    component: $package,
                    message: $wasInstalled
                        ? 'Installed successfully.'
                        : 'Already installed.',
                ),
            );
        }

        return $report;
    }

    /**
     * @param  array<int, string>  $packages
     * @return list<string>
     */
    private function normalize(array $packages): array
    {
        $normalized = array_map(
            static fn (string $package): string => trim($package),
            $packages,
        );

        $normalized = array_filter(
            $normalized,
            static fn (string $package): bool => $package !== '',
        );

        return array_values(
            array_unique($normalized),
        );
    }
}
