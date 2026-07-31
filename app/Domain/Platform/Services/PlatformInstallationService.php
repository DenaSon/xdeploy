<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Platform\Contracts\StartablePlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformDependencyCycleException;
use App\Domain\Platform\Exceptions\PlatformInspectionException;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Services\SystemDependencyService;
use App\Domain\Shared\DTOs\InstallMessage;
use App\Domain\Shared\DTOs\InstallReport;
use Throwable;

final readonly class PlatformInstallationService
{
    private const int INSPECTION_ATTEMPTS = 3;

    private const int INSPECTION_DELAY_MICROSECONDS = 300_000;

    public function __construct(
        private PlatformRegistryInterface $registry,
        private SystemDependencyService $systemDependencies,
    ) {}

    public function ensure(
        PlatformType $type,
    ): InstallReport {
        $resolving = [];
        $resolved = [];

        return $this->ensurePlatform(
            type: $type,
            resolving: $resolving,
            resolved: $resolved,
        );
    }

    /**
     * @param  list<PlatformType>  $resolving
     * @param  array<string, true>  $resolved
     */
    private function ensurePlatform(
        PlatformType $type,
        array &$resolving,
        array &$resolved,
    ): InstallReport {
        if (isset($resolved[$type->value])) {
            return new InstallReport;
        }

        $this->guardAgainstDependencyCycle(
            type: $type,
            resolving: $resolving,
        );

        $resolving[] = $type;

        try {
            $platform = $this->registry->find($type);

            $initialInfo = $this->inspectKnownState(
                platform: $platform,
                type: $type,
            );

            $initiallyInstalled = $initialInfo->isInstalled();
            $initiallyRunning = $initialInfo->isRunning();

            /*
             * Platform dependencies must be prepared before the current
             * platform. Their reports are preserved in execution order.
             */
            $report = $this->ensureDependencies(
                platform: $platform,
                resolving: $resolving,
                resolved: $resolved,
            );

            /*
             * System packages required directly by this platform are ensured
             * in one batch and merged into the complete installation report.
             */
            $report = $report->merge(
                $this->systemDependencies->ensure(
                    $platform->systemPackages(),
                ),
            );

            $currentInfo = $this->inspectKnownState(
                platform: $platform,
                type: $type,
            );

            if (! $currentInfo->isInstalled()) {
                $platform->install();

                $currentInfo = $this->inspectKnownState(
                    platform: $platform,
                    type: $type,
                );
            }

            if (! $currentInfo->isInstalled()) {
                throw new PlatformInstallationException(
                    sprintf(
                        'Platform [%s] installation verification failed.',
                        $type->value,
                    ),
                );
            }

            $report = $report->with(
                new InstallMessage(
                    component: $type->value,
                    message: $initiallyInstalled
                        ? 'Already installed.'
                        : 'Installed successfully.',
                ),
            );

            if ($platform instanceof StartablePlatformInterface) {
                if (! $currentInfo->isRunning()) {
                    $platform->start();

                    $currentInfo = $this->inspectKnownState(
                        platform: $platform,
                        type: $type,
                    );

                    $report = $report->with(
                        new InstallMessage(
                            component: $type->value,
                            message: 'Started successfully.',
                        ),
                    );
                } elseif ($initiallyRunning) {
                    $report = $report->with(
                        new InstallMessage(
                            component: $type->value,
                            message: 'Already running.',
                        ),
                    );
                }
            }

            $this->verifyFinalState(
                platform: $platform,
                type: $type,
                info: $currentInfo,
            );

            $resolved[$type->value] = true;

            return $report;
        } finally {
            array_pop($resolving);
        }
    }

    /**
     * @param  list<PlatformType>  $resolving
     */
    private function guardAgainstDependencyCycle(
        PlatformType $type,
        array $resolving,
    ): void {
        $cycleStart = array_search(
            $type,
            $resolving,
            true,
        );

        if ($cycleStart === false) {
            return;
        }

        $cycle = [
            ...array_slice(
                $resolving,
                (int) $cycleStart,
            ),
            $type,
        ];

        throw new PlatformDependencyCycleException(
            $cycle,
        );
    }

    /**
     * @param  list<PlatformType>  $resolving
     * @param  array<string, true>  $resolved
     */
    private function ensureDependencies(
        PlatformInterface $platform,
        array &$resolving,
        array &$resolved,
    ): InstallReport {
        $report = new InstallReport;

        foreach ($platform->dependencies() as $dependency) {
            $report = $report->merge(
                $this->ensurePlatform(
                    type: $dependency,
                    resolving: $resolving,
                    resolved: $resolved,
                ),
            );
        }

        return $report;
    }

    private function inspectKnownState(
        PlatformInterface $platform,
        PlatformType $type,
    ): PlatformInfo {
        for (
            $attempt = 1;
            $attempt <= self::INSPECTION_ATTEMPTS;
            $attempt++
        ) {
            try {
                $info = $platform->inspect();

                if (! $info->isUnknown()) {
                    return $info;
                }
            } catch (Throwable) {
                /*
                 * Inspection may fail temporarily because of SSH latency,
                 * service startup delay or a transient remote command error.
                 */
            }

            if ($attempt < self::INSPECTION_ATTEMPTS) {
                usleep(
                    self::INSPECTION_DELAY_MICROSECONDS,
                );
            }
        }

        throw new PlatformInspectionException(
            $type,
        );
    }

    private function verifyFinalState(
        PlatformInterface $platform,
        PlatformType $type,
        PlatformInfo $info,
    ): void {
        if (
            $platform instanceof StartablePlatformInterface
            && ! $info->isRunning()
        ) {
            throw new PlatformInstallationException(
                sprintf(
                    'Platform [%s] is installed but not running.',
                    $type->value,
                ),
            );
        }

        if (
            ! $platform instanceof StartablePlatformInterface
            && ! $info->isInstalled()
        ) {
            throw new PlatformInstallationException(
                sprintf(
                    'Platform [%s] is not ready.',
                    $type->value,
                ),
            );
        }
    }
}
