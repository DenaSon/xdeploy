<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Exceptions\ApplicationInspectionException;
use App\Domain\Application\Shared\Exceptions\ApplicationRestartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStopException;
use App\Domain\Shared\DTOs\InstallMessage;
use App\Domain\Shared\DTOs\InstallReport;
use LogicException;
use RuntimeException;

final readonly class ApplicationLifecycleService
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
    ) {}

    public function start(
        ApplicationType $type,
    ): InstallReport {
        $application = $this->resolveStartable($type);

        $state = $this->resolveKnownState(
            application: $application,
            type: $type,
            operation: 'start',
        );

        if ($state === ApplicationState::Running) {
            return $this->report(
                application: $application,
                message: 'Already running.',
            );
        }

        if ($state !== ApplicationState::Installed) {
            throw new ApplicationStartException(
                sprintf(
                    'Application [%s] must be installed before it can be started.',
                    $type->value,
                ),
            );
        }

        $application->start();

        $this->verifyState(
            application: $application,
            type: $type,
            expectedState: ApplicationState::Running,
            operation: 'start',
            exception: ApplicationStartException::class,
        );

        return $this->report(
            application: $application,
            message: 'Started successfully.',
        );
    }

    public function stop(
        ApplicationType $type,
    ): void {
        $application = $this->resolveStartable($type);

        $state = $this->resolveKnownState(
            application: $application,
            type: $type,
            operation: 'stop',
        );

        /*
         * Installed means the application exists but is not currently
         * running, so stop is already satisfied.
         */
        if ($state === ApplicationState::Installed) {
            return;
        }

        if ($state !== ApplicationState::Running) {
            throw new ApplicationStopException(
                sprintf(
                    'Application [%s] must be running before it can be stopped.',
                    $type->value,
                ),
            );
        }

        $application->stop();

        $this->verifyState(
            application: $application,
            type: $type,
            expectedState: ApplicationState::Installed,
            operation: 'stop',
            exception: ApplicationStopException::class,
        );
    }

    public function restart(
        ApplicationType $type,
    ): void {
        $application = $this->resolveStartable($type);

        $state = $this->resolveKnownState(
            application: $application,
            type: $type,
            operation: 'restart',
        );

        if ($state !== ApplicationState::Running) {
            throw new ApplicationRestartException(
                sprintf(
                    'Application [%s] must be running before it can be restarted.',
                    $type->value,
                ),
            );
        }

        $application->restart();

        $this->verifyState(
            application: $application,
            type: $type,
            expectedState: ApplicationState::Running,
            operation: 'restart',
            exception: ApplicationRestartException::class,
        );
    }

    private function resolveStartable(
        ApplicationType $type,
    ): ApplicationInterface&StartableInterface {
        $application = $this->registry->find($type);

        if (! $application instanceof StartableInterface) {
            throw new LogicException(
                sprintf(
                    'Application [%s] does not support lifecycle operations.',
                    $type->value,
                ),
            );
        }

        return $application;
    }

    private function resolveKnownState(
        ApplicationInterface $application,
        ApplicationType $type,
        string $operation,
    ): ApplicationState {
        $state = $application->inspect()->state;

        if ($state === ApplicationState::Unknown) {
            throw new ApplicationInspectionException(
                sprintf(
                    'Application [%s] state could not be determined before %s.',
                    $type->value,
                    $operation,
                ),
            );
        }

        return $state;
    }

    /**
     * @param  class-string<RuntimeException>  $exception
     */
    private function verifyState(
        ApplicationInterface $application,
        ApplicationType $type,
        ApplicationState $expectedState,
        string $operation,
        string $exception,
    ): void {
        $state = $application->inspect()->state;

        if ($state === ApplicationState::Unknown) {
            throw new ApplicationInspectionException(
                sprintf(
                    'Application [%s] state could not be determined after %s.',
                    $type->value,
                    $operation,
                ),
            );
        }

        if ($state === $expectedState) {
            return;
        }

        throw new $exception(
            sprintf(
                'Application [%s] failed to %s.',
                $type->value,
                $operation,
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
