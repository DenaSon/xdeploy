<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use LogicException;

final readonly class ApplicationLifecycleService
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
    ) {}

    public function start(ApplicationType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        if ($module->inspect()->state === ApplicationState::Running) {
            return;
        }

        $module->start();

        if ($module->inspect()->state !== ApplicationState::Running) {
            throw new LogicException(sprintf(
                'Module [%s] failed to start.',
                $type->value,
            ));
        }
    }

    public function stop(ApplicationType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        if ($module->inspect()->state !== ApplicationState::Running) {
            return;
        }

        $module->stop();

        if ($module->inspect()->state !== ApplicationState::Installed) {
            throw new LogicException(sprintf(
                'Module [%s] failed to stop.',
                $type->value,
            ));
        }
    }

    public function restart(ApplicationType $type): void
    {
        $module = $this->registry->find($type);

        $this->ensureStartable($module);

        $module->restart();

        if ($module->inspect()->state !== ApplicationState::Running) {
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
