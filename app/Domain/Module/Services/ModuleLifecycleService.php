<?php

declare(strict_types=1);

namespace App\Domain\Module\Services;

use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use LogicException;

final readonly class ModuleLifecycleService
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {}

    public function start(ModuleType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        if ($module->inspect()->state === ModuleState::Running) {
            return;
        }

        $module->start();

        if ($module->inspect()->state !== ModuleState::Running) {
            throw new LogicException(sprintf(
                'Module [%s] failed to start.',
                $type->value,
            ));
        }
    }

    public function stop(ModuleType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        if ($module->inspect()->state !== ModuleState::Running) {
            return;
        }

        $module->stop();

        if ($module->inspect()->state !== ModuleState::Installed) {
            throw new LogicException(sprintf(
                'Module [%s] failed to stop.',
                $type->value,
            ));
        }
    }

    public function restart(ModuleType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        $module->restart();

        if ($module->inspect()->state !== ModuleState::Running) {
            throw new LogicException(sprintf(
                'Module [%s] failed to restart.',
                $type->value,
            ));
        }
    }

    private function ensureStartable(
        mixed $module,
    ): void {
        if (! $module instanceof StartableInterface) {
            throw new LogicException(
                'Module does not support lifecycle operations.',
            );
        }
    }
}
