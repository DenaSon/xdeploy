<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationInterface;
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
        $application = $this->registry->find($type);

        $this->ensureStartable($application);

        if ($application->inspect()->state === ApplicationState::Running) {
            return;
        }

        $application->start();

        if ($application->inspect()->state !== ApplicationState::Running) {
            throw new LogicException(sprintf(
                'Application [%s] failed to start.',
                $type->value,
            ));
        }
    }

    public function stop(ApplicationType $type): void
    {
        $application = $this->registry->find($type);

        $this->ensureStartable($application);

        if ($application->inspect()->state !== ApplicationState::Running) {
            return;
        }

        $application->stop();

        if ($application->inspect()->state !== ApplicationState::Installed) {
            throw new LogicException(sprintf(
                'Application [%s] failed to stop.',
                $type->value,
            ));
        }
    }

    public function restart(ApplicationType $type): void
    {
        $application = $this->registry->find($type);

        $this->ensureStartable($application);

        $application->restart();

        if ($application->inspect()->state !== ApplicationState::Running) {
            throw new LogicException(sprintf(
                'Application [%s] failed to restart.',
                $type->value,
            ));
        }
    }

    private function ensureStartable(
        ApplicationInterface $application,
    ): void {
        if (! $application instanceof StartableInterface) {
            throw new LogicException(
                'Application does not support lifecycle operations.',
            );
        }
    }
}
