<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Abstracts;

use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Exceptions\ApplicationRestartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStopException;
use App\Support\SSH\SSHTimeout;
use RuntimeException;
use Throwable;

abstract readonly class DockerComposeApplication extends CommandApplication implements StartableInterface
{
    private const int STATE_CHECK_ATTEMPTS = 20;

    private const int STATE_CHECK_DELAY_MICROSECONDS = 500_000;

    private const int CONTAINER_INSPECTION_ATTEMPTS = 3;

    private const int CONTAINER_INSPECTION_DELAY_MICROSECONDS = 500_000;

    final public function start(): void
    {
        $this->executeLifecycleOperation(
            operation: 'up -d --remove-orphans',
            expectedState: ApplicationState::Running,
            exception: ApplicationStartException::class,
            commandFailureMessage: sprintf(
                'Failed to start %s.',
                $this->name(),
            ),
            stateFailureMessage: sprintf(
                '%s did not enter the running state.',
                $this->name(),
            ),
        );
    }

    final public function stop(): void
    {
        $this->executeLifecycleOperation(
            operation: 'stop',
            expectedState: ApplicationState::Installed,
            exception: ApplicationStopException::class,
            commandFailureMessage: sprintf(
                'Failed to stop %s.',
                $this->name(),
            ),
            stateFailureMessage: sprintf(
                '%s did not stop successfully.',
                $this->name(),
            ),
        );
    }

    final public function restart(): void
    {
        $this->executeLifecycleOperation(
            operation: 'up -d --force-recreate --remove-orphans',
            expectedState: ApplicationState::Running,
            exception: ApplicationRestartException::class,
            commandFailureMessage: sprintf(
                'Failed to restart %s.',
                $this->name(),
            ),
            stateFailureMessage: sprintf(
                '%s did not restart successfully.',
                $this->name(),
            ),
        );
    }

    final protected function resolveState(): ApplicationState
    {
        return $this->resolveContainerState();
    }

    final protected function composeCommand(
        string $operation,
    ): string {
        return sprintf(
            <<<'BASH'
set -euo pipefail

docker compose \
    --env-file %s \
    -f %s \
    -p %s \
    %s
BASH,
            $this->composeEnvFile(),
            $this->composeFile(),
            $this->composeProject(),
            $operation,
        );
    }

    abstract protected function composeProject(): string;

    abstract protected function composeService(): string;

    /**
     * Return the Compose services that must move through the lifecycle
     * together for this Application.
     *
     * Existing single-service Applications keep their current behavior by
     * default. Multi-service Applications may opt in without changing the
     * public Application contracts or existing subclasses.
     *
     * @return list<string>
     */
    protected function requiredComposeServices(): array
    {
        return [
            $this->composeService(),
        ];
    }

    abstract protected function composeFile(): string;

    abstract protected function composeEnvFile(): string;

    /**
     * @param  class-string<RuntimeException>  $exception
     */
    private function executeLifecycleOperation(
        string $operation,
        ApplicationState $expectedState,
        string $exception,
        string $commandFailureMessage,
        string $stateFailureMessage,
    ): void {
        $result = $this->privileged->executeWithResult(
            command: $this->composeCommand(
                $operation,
            ),
            timeout: SSHTimeout::DEFAULT,
        );

        if (! $result->successful()) {
            throw new $exception(
                $commandFailureMessage,
            );
        }

        $this->waitForState(
            expectedState: $expectedState,
            exception: $exception,
            message: $stateFailureMessage,
        );
    }

    /**
     * @param  class-string<RuntimeException>  $exception
     */
    private function waitForState(
        ApplicationState $expectedState,
        string $exception,
        string $message,
    ): void {
        for (
            $attempt = 1;
            $attempt <= self::STATE_CHECK_ATTEMPTS;
            $attempt++
        ) {
            if (
                $this->resolveState()
                === $expectedState
            ) {
                return;
            }

            if (
                $attempt
                < self::STATE_CHECK_ATTEMPTS
            ) {
                usleep(
                    self::STATE_CHECK_DELAY_MICROSECONDS,
                );
            }
        }

        throw new $exception(
            $message,
        );
    }

    private function resolveContainerState(): ApplicationState
    {
        $services = $this->requiredComposeServices();

        if ($services === []) {
            return ApplicationState::Unknown;
        }

        $runningServices = 0;

        foreach ($services as $service) {
            $state = $this->resolveComposeServiceState(
                $service,
            );

            if ($state === ApplicationState::Unknown) {
                return ApplicationState::Unknown;
            }

            if ($state === ApplicationState::Running) {
                $runningServices++;
            }
        }

        if ($runningServices === count($services)) {
            return ApplicationState::Running;
        }

        if ($runningServices === 0) {
            return ApplicationState::Installed;
        }

        return ApplicationState::Unknown;
    }

    private function resolveComposeServiceState(
        string $service,
    ): ApplicationState {
        $command = sprintf(
            <<<'BASH'
timeout --signal=TERM 8 \
docker ps \
    --filter "label=com.docker.compose.project=%s" \
    --filter "label=com.docker.compose.service=%s" \
    --filter "status=running" \
    --format "{{.ID}}"
BASH,
            $this->composeProject(),
            $service,
        );

        for (
            $attempt = 1;
            $attempt <= self::CONTAINER_INSPECTION_ATTEMPTS;
            $attempt++
        ) {
            try {
                $result = $this->privileged->executeWithResult(
                    command: $command,
                    timeout: SSHTimeout::QUICK,
                );

                if ($result->successful()) {
                    return trim(
                        $result->output,
                    ) !== ''
                        ? ApplicationState::Running
                        : ApplicationState::Installed;
                }
            } catch (Throwable) {
                // Retry transient Docker or SSH inspection failures.
            }

            if (
                $attempt
                < self::CONTAINER_INSPECTION_ATTEMPTS
            ) {
                usleep(
                    self::CONTAINER_INSPECTION_DELAY_MICROSECONDS,
                );
            }
        }

        return ApplicationState::Unknown;
    }
}
