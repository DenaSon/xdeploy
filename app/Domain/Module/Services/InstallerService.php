<?php

declare(strict_types=1);

namespace App\Domain\Module\Services;

use App\Domain\Module\Contracts\ModuleInterface;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\DTOs\InstallMessage;
use App\Domain\Module\DTOs\InstallReport;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
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
            ModuleState::Running => $this->report(
                $type,
                'Already running.',
            ),

            ModuleState::Installed => $this->handleInstalledModule(
                $module,
                $type,
                new InstallReport,
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

        $report = $report->merge(
            $this->report(
                $type,
                'Installed successfully.',
            ),
        );

        return $this->handleInstalledModule(
            $module,
            $type,
            $report,
        );
    }

    private function handleInstalledModule(
        ModuleInterface $module,
        ModuleType $type,
        InstallReport $report,
    ): InstallReport {
        if (! $module instanceof StartableInterface) {
            return $report->merge(
                $this->report(
                    $type,
                    'Already installed.',
                ),
            );
        }

        return $this->startModule(
            $module,
            $type,
            $report,
        );
    }

    private function startModule(
        StartableInterface&ModuleInterface $module,
        ModuleType $type,
        InstallReport $report,
    ): InstallReport {
        if ($module->inspect()->state === ModuleState::Running) {
            return $report->merge(
                $this->report(
                    $type,
                    'Already running.',
                ),
            );
        }

        $module->start();

        if ($module->inspect()->state !== ModuleState::Running) {
            throw new LogicException(sprintf(
                'Module [%s] failed to start.',
                $type->value,
            ));
        }

        return $report->merge(
            $this->report(
                $type,
                'Started successfully.',
            ),
        );
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
