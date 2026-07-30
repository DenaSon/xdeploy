<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ModuleInterface;
use App\Domain\Application\Contracts\ModuleRegistryInterface;
use App\Domain\Application\DTOs\InstallMessage;
use App\Domain\Application\DTOs\InstallReport;
use App\Domain\Application\Enums\ModuleState;
use App\Domain\Application\Enums\ModuleType;
use LogicException;

final readonly class InstallerService
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {}

    public function install(ModuleType $type): InstallReport
    {
        $module = $this->registry->find($type);

        return match ($module->inspect()->state) {
            ModuleState::Running,
            ModuleState::Installed => $this->report(
                $type,
                'Already installed.',
            ),

            ModuleState::NotInstalled => $this->installModule(
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
        ModuleInterface $module,
        ModuleType $type,
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

    public function uninstall(ModuleType $type): void
    {
        $module = $this->registry->find($type);

        if ($module->inspect()->state === ModuleState::NotInstalled) {
            return;
        }

        $module->uninstall();

        if (
            $module->inspect()->state !== ModuleState::NotInstalled
        ) {
            throw new LogicException(sprintf(
                'Module [%s] uninstall verification failed.',
                $type->value,
            ));
        }
    }

    private function verifyInstallation(
        ModuleInterface $module,
        ModuleType $type,
    ): void {
        $state = $module->inspect()->state;

        if (
            $state !== ModuleState::Installed &&
            $state !== ModuleState::Running
        ) {
            throw new LogicException(sprintf(
                'Module [%s] installation verification failed.',
                $type->value,
            ));
        }
    }

    private function report(
        ModuleType $type,
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
