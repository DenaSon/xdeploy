<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\DTOs\InstallMessage;
use App\Domain\Application\Shared\DTOs\InstallReport;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use LogicException;

final readonly class ApplicationInstallationService
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
    ) {}

    public function install(ApplicationType $type): InstallReport
    {
        $application = $this->registry->find($type);

        return match ($application->inspect()->state) {
            ApplicationState::Running,
            ApplicationState::Installed => $this->report(
                $type,
                'Already installed.',
            ),

            ApplicationState::NotInstalled => $this->installApplication(
                $application,
                $type,
                new InstallReport,
            ),

            default => throw new LogicException(sprintf(
                'Unsupported application state [%s].',
                $application->inspect()->state->value,
            )),
        };
    }

    private function installApplication(
        ApplicationInterface $application,
        ApplicationType $type,
        InstallReport $report,
    ): InstallReport {
        foreach ($application->dependencies() as $dependency) {
            $report = $report->merge(
                $this->install($dependency->type),
            );
        }

        $application->install();

        $this->verifyInstallation(
            $application,
            $type,
        );

        return $report->merge(
            $this->report(
                $type,
                'Installed successfully.',
            ),
        );
    }

    public function uninstall(ApplicationType $type): void
    {
        $application = $this->registry->find($type);

        if ($application->inspect()->state === ApplicationState::NotInstalled) {
            return;
        }

        $application->uninstall();

        if (
            $application->inspect()->state !== ApplicationState::NotInstalled
        ) {
            throw new LogicException(sprintf(
                'Application [%s] uninstall verification failed.',
                $type->value,
            ));
        }
    }

    private function verifyInstallation(
        ApplicationInterface $application,
        ApplicationType $type,
    ): void {
        $state = $application->inspect()->state;

        if (
            $state !== ApplicationState::Installed &&
            $state !== ApplicationState::Running
        ) {
            throw new LogicException(sprintf(
                'Application [%s] installation verification failed.',
                $type->value,
            ));
        }
    }

    private function report(
        ApplicationType $type,
        string $message,
    ): InstallReport {
        return new InstallReport([
            new InstallMessage(
                application: $type->value,
                message: $message,
            ),
        ]);
    }
}
