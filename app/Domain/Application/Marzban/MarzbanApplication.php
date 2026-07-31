<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban;

use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Abstracts\CommandApplication;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\Shared\Exceptions\ApplicationRestartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStopException;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Domain\Platform\Enums\PlatformType;
use App\Support\SSH\SSHTimeout;

final readonly class MarzbanApplication extends CommandApplication implements StartableInterface
{
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

    protected function inspectCommand(): string
    {
        return 'test -d /opt/marzban';
    }

    protected function resolveState(): ApplicationState
    {
        $installedResult = $this->ssh->executeWithResult(
            command: $this->inspectCommand(),
            timeout: SSHTimeout::QUICK,
        );

        if (! $installedResult->successful()) {
            return ApplicationState::NotInstalled;
        }

        return $this->resolveContainerState();
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        return [];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
set -euo pipefail

INSTALLER="$(mktemp /tmp/xdeploy-marzban-installer.XXXXXX)"

cleanup() {
    rm -f "$INSTALLER"
}

trap cleanup EXIT

curl -fsSL \
    https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh \
    -o "$INSTALLER"

if ! grep -q '^follow_marzban_logs() {' "$INSTALLER"; then
    echo "Unexpected Marzban installer format." >&2
    exit 90
fi

sed -i \
    '/^follow_marzban_logs() {/a\    return 0' \
    "$INSTALLER"

timeout --signal=TERM 600 \
    bash "$INSTALLER" install </dev/null
BASH;
    }

    public function start(): void
    {
        $this->runCommand(
            command: 'marzban up --no-logs',
            exception: ApplicationStartException::class,
            message: 'Failed to start Marzban.',
        );

        $this->waitForState(
            expectedState: ApplicationState::Running,
            exception: ApplicationStartException::class,
            message: 'Marzban did not enter the running state.',
        );
    }

    public function stop(): void
    {
        $this->runCommand(
            command: 'marzban down',
            exception: ApplicationStopException::class,
            message: 'Failed to stop Marzban.',
        );

        $this->waitForState(
            expectedState: ApplicationState::Installed,
            exception: ApplicationStopException::class,
            message: 'Marzban did not stop successfully.',
        );
    }

    public function restart(): void
    {
        $this->runCommand(
            command: 'marzban restart --no-logs',
            exception: ApplicationRestartException::class,
            message: 'Failed to restart Marzban.',
        );

        $this->waitForState(
            expectedState: ApplicationState::Running,
            exception: ApplicationRestartException::class,
            message: 'Marzban did not restart successfully.',
        );
    }

    protected function uninstallCommand(): string
    {
        return <<<'BASH'
timeout --signal=TERM 180 \
    sh -c 'yes y | marzban uninstall'
BASH;
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
     * @param  class-string<\RuntimeException>  $exception
     */
    private function runCommand(
        string $command,
        string $exception,
        string $message,
        int $timeout = SSHTimeout::DEFAULT,
    ): void {
        $result = $this->ssh->executeWithResult(
            command: $command,
            timeout: $timeout,
        );

        if (! $result->successful()) {
            throw new $exception($message);
        }
    }

    /**
     * @param  class-string<\RuntimeException>  $exception
     */
    private function waitForState(
        ApplicationState $expectedState,
        string $exception,
        string $message,
        int $attempts = 20,
        int $delayMilliseconds = 500,
    ): void {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($this->resolveState() === $expectedState) {
                return;
            }

            if ($attempt < $attempts) {
                usleep($delayMilliseconds * 1_000);
            }
        }

        throw new $exception($message);
    }

    private function resolveContainerState(): ApplicationState
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $result = $this->ssh->executeWithResult(
                    command: <<<'BASH'
timeout --signal=TERM 8 \
docker ps \
    --filter "name=marzban" \
    --format "{{.Names}}"
BASH,
                    timeout: SSHTimeout::QUICK,
                );

                if ($result->successful()) {
                    return trim($result->output) !== ''
                        ? ApplicationState::Running
                        : ApplicationState::Installed;
                }
            } catch (\Throwable) {
                // Retry transient Docker/SSH inspection failures.
            }

            if ($attempt < 3) {
                usleep(500_000);
            }
        }

        return ApplicationState::Unknown;
    }
}
