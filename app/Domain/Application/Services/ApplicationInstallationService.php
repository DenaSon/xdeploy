<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Shared\DTOs\InstallMessage;
use App\Domain\Shared\DTOs\InstallReport;
use LogicException;

final readonly class ApplicationInstallationService
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
    ) {}

    public function install(
        ApplicationType $type,
    ): InstallReport {
        $application = $this->registry->find($type);

        $initialInfo = $application->inspect();

        return match ($initialInfo->state) {
            ApplicationState::Running,
            ApplicationState::Installed => $this->report(
                application: $application,
                message: 'Already installed.',
            ),

            ApplicationState::NotInstalled => $this->installApplication(
                application: $application,
                type: $type,
            ),

            ApplicationState::Unknown => throw new LogicException(
                sprintf(
                    'Application [%s] state is unknown. Installation was stopped.',
                    $type->value,
                ),
            ),
        };
    }

    public function uninstall(
        ApplicationType $type,
    ): void {
        $application = $this->registry->find($type);

        $initialInfo = $application->inspect();

        if (
            $initialInfo->state
            === ApplicationState::NotInstalled
        ) {
            return;
        }

        if (
            $initialInfo->state
            === ApplicationState::Unknown
        ) {
            throw new LogicException(
                sprintf(
                    'Application [%s] state is unknown. Uninstallation was stopped.',
                    $type->value,
                ),
            );
        }

        $application->uninstall();

        $finalInfo = $application->inspect();

        if (
            $finalInfo->state
            !== ApplicationState::NotInstalled
        ) {
            throw new LogicException(
                sprintf(
                    'Application [%s] uninstall verification failed.',
                    $type->value,
                ),
            );
        }
    }

    private function installApplication(
        ApplicationInterface $application,
        ApplicationType $type,
    ): InstallReport {
        $application->install();

        $this->verifyInstallation(
            application: $application,
            type: $type,
        );

        return $this->report(
            application: $application,
            message: 'Installed successfully.',
        );
    }

    private function verifyInstallation(
        ApplicationInterface $application,
        ApplicationType $type,
    ): void {
        $finalInfo = $application->inspect();

        if (
            $finalInfo->state === ApplicationState::Installed
            || $finalInfo->state === ApplicationState::Running
        ) {
            return;
        }

        throw new LogicException(
            sprintf(
                'Application [%s] installation verification failed.',
                $type->value,
            ),
        );
    }

    private function report(
        ApplicationInterface $application,
        string $message,
    ): InstallReport {
        return new InstallReport([
            new InstallMessage(
                component: $application->name(),
                message: $message,
            ),
        ]);
    }
}
