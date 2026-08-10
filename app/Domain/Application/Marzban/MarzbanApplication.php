<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban;

use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Abstracts\CommandApplication;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\Shared\Exceptions\ApplicationInstallationException;
use App\Domain\Application\Shared\Exceptions\ApplicationRestartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStopException;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use RuntimeException;
use Throwable;

final readonly class MarzbanApplication extends CommandApplication implements StartableInterface
{
    private const int STATE_CHECK_ATTEMPTS = 20;

    private const int STATE_CHECK_DELAY_MICROSECONDS = 500_000;

    private const int CONTAINER_INSPECTION_ATTEMPTS = 3;

    private const int CONTAINER_INSPECTION_DELAY_MICROSECONDS = 500_000;

    public function __construct(
        SSHConnectionInterface $ssh,
        PrivilegedCommandExecutor $privileged,
        private InstallerSourceInterface $installerSource,
    ) {
        parent::__construct(
            ssh: $ssh,
            privileged: $privileged,
        );
    }

    public function type(): ApplicationType
    {
        return ApplicationType::Marzban;
    }

    public function name(): string
    {
        return 'Marzban';
    }

    public function requirements(): ApplicationRequirements
    {
        return new ApplicationRequirements(
            systemPackages: [
                'curl',
                'ca-certificates',
            ],
            platforms: [
                PlatformType::DockerCompose,
            ],
        );
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [
            new ProvidedSoftware(
                SoftwareType::Marzban,
            ),

            new ProvidedSoftware(
                SoftwareType::Xray,
            ),
        ];
    }

    public function start(): void
    {
        $this->executeLifecycleOperation(
            operation: 'up -d --remove-orphans',
            expectedState: ApplicationState::Running,
            exception: ApplicationStartException::class,
            commandFailureMessage: 'Failed to start Marzban.',
            stateFailureMessage: 'Marzban did not enter the running state.',
        );
    }

    public function stop(): void
    {
        $this->executeLifecycleOperation(
            operation: 'stop',
            expectedState: ApplicationState::Installed,
            exception: ApplicationStopException::class,
            commandFailureMessage: 'Failed to stop Marzban.',
            stateFailureMessage: 'Marzban did not stop successfully.',
        );
    }

    public function restart(): void
    {
        $this->executeLifecycleOperation(
            operation: 'up -d --force-recreate --remove-orphans',
            expectedState: ApplicationState::Running,
            exception: ApplicationRestartException::class,
            commandFailureMessage: 'Failed to restart Marzban.',
            stateFailureMessage: 'Marzban did not restart successfully.',
        );
    }

    protected function inspectCommand(): string
    {
        return <<<'BASH'
if [ -f /opt/marzban/.xdeploy-install-complete ]; then
    exit 0
fi

if ! command -v docker >/dev/null 2>&1; then
    exit 1
fi

container_id="$(
    docker ps -a \
        --filter "label=com.docker.compose.project=marzban" \
        --filter "label=com.docker.compose.service=marzban" \
        --format "{{.ID}}" \
        2>/dev/null \
        | head -n 1
)"

test -n "$container_id"
BASH;
    }

    protected function resolveState(): ApplicationState
    {
        return $this->resolveContainerState();
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(
        string $output,
    ): array {
        return [];
    }

    protected function installCommand(): string
    {
        try {
            return $this->installerSource->buildExecutionCommand(
                relativePath: (string) config(
                    'xdeploy.installers.marzban.ubuntu.path',
                ),
                expectedSha256: (string) config(
                    'xdeploy.installers.marzban.ubuntu.sha256',
                ),
            );
        } catch (RuntimeException $exception) {
            throw new ApplicationInstallationException(
                message: 'Marzban installer could not be prepared.',
                previous: $exception,
            );
        }
    }

    protected function installSensitive(): bool
    {
        return true;
    }

    protected function uninstallCommand(): string
    {
        return sprintf(
            "%s\n\n%s",
            $this->composeCommand(
                'down --remove-orphans',
            ),
            <<<'BASH'
rm -f /opt/marzban/.xdeploy-install-complete
BASH,
        );
    }

    protected function uninstallTimeout(): int
    {
        return SSHTimeout::APPLICATION_UNINSTALL;
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::APPLICATION_INSTALL;
    }

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

    private function composeCommand(
        string $operation,
    ): string {
        return sprintf(
            <<<'BASH'
set -euo pipefail

compose_files=(
    -f /opt/marzban/docker-compose.yml
)

if [ -f /opt/marzban/docker-compose.xdeploy.yml ]; then
    compose_files+=(
        -f /opt/marzban/docker-compose.xdeploy.yml
    )
fi

docker compose \
    --env-file /opt/marzban/.env \
    "${compose_files[@]}" \
    -p marzban \
    %s
BASH,
            $operation,
        );
    }

    private function resolveContainerState(): ApplicationState
    {
        for (
            $attempt = 1;
            $attempt <= self::CONTAINER_INSPECTION_ATTEMPTS;
            $attempt++
        ) {
            try {
                $result = $this->privileged->executeWithResult(
                    command: <<<'BASH'
timeout --signal=TERM 8 \
docker ps \
    --filter "label=com.docker.compose.project=marzban" \
    --filter "label=com.docker.compose.service=marzban" \
    --filter "status=running" \
    --format "{{.ID}}"
BASH,
                    timeout: SSHTimeout::QUICK,
                );

                if (
                    $result->successful()
                ) {
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
