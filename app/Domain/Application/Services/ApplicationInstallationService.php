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
        $module = $this->registry->find($type);

        return match ($module->inspect()->state) {
            ApplicationState::Running,
            ApplicationState::Installed => $this->report(
                $type,
                'Already installed.',
            ),

            ApplicationState::NotInstalled => $this->installModule(
                $module,
                $type,
                new InstallReport,
            ),

            default => throw new LogicException(sprintf(
                'Unsupported module state [%s].',
                $module->inspect()->state->value,
            )),
        };
    }

    private function installModule(
        ApplicationInterface $module,
        ApplicationType $type,
        InstallReport $report,
    ): InstallReport {
        foreach ($module->dependencies() as $dependency) {
            $report = $report->merge(
                $this->install($dependency->type),
            );
        }

        $module->install();

        $this->verifyInstallation(
            $module,
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
        $module = $this->registry->find($type);

        if ($module->inspect()->state === ApplicationState::NotInstalled) {
            return;
        }

        $module->uninstall();

        if (
            $module->inspect()->state !== ApplicationState::NotInstalled
        ) {
            throw new LogicException(sprintf(
                'Module [%s] uninstall verification failed.',
                $type->value,
            ));
        }
    }

    private function verifyInstallation(
        ApplicationInterface $module,
        ApplicationType $type,
    ): void {
        $state = $module->inspect()->state;

        if (
            $state !== ApplicationState::Installed &&
            $state !== ApplicationState::Running
        ) {
            throw new LogicException(sprintf(
                'Module [%s] installation verification failed.',
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
                module: $type->value,
                message: $message,
            ),
        ]);
    }
}
