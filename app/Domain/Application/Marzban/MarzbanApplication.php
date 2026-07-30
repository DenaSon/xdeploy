<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban;

use App\Domain\Application\Abstracts\CommandApplication;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Enums\ApplicationState;
use App\Domain\Application\Enums\ApplicationType;
use App\Domain\Application\Enums\SoftwareType;
use App\Domain\Application\Exceptions\ApplicationInstallationException;
use App\Domain\Application\Exceptions\ApplicationRestartException;
use App\Domain\Application\Exceptions\ApplicationStartException;
use App\Domain\Application\Exceptions\ApplicationStopException;
use App\Domain\Application\ValueObjects\ApplicationDependency;
use App\Domain\Application\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;
use LogicException;

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

    protected function inspectCommand(): string
    {
        return 'test -d /opt/marzban';
    }

    protected function resolveState(): ApplicationState
    {
        $installed = $this->ssh->executeWithResult(
            'test -d /opt/marzban',
        );

        if (! $installed->successful()) {
            return ApplicationState::NotInstalled;
        }

        $container = $this->ssh->executeWithResult(
            'docker ps --filter "name=marzban" --format "{{.Names}}"',
        );

        return $container->successful()
        && trim($container->output) !== ''
            ? ApplicationState::Running
            : ApplicationState::Installed;
    }

    /**
     * @return array<string,mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        return [];
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

    /**
     * @return list<ApplicationDependency>
     */
    public function dependencies(): array
    {
        return [
            new ApplicationDependency(
                ApplicationType::Docker,
            ),

            new ApplicationDependency(
                ApplicationType::DockerCompose,
            ),
        ];
    }

    protected function checkRequirements(): void
    {
        $root = $this->ssh->executeWithResult(
            'id -u',
        );

        if (
            ! $root->successful()
            || trim($root->output) !== '0'
        ) {
            throw new ApplicationInstallationException(
                'Marzban installation requires root privileges.',
            );
        }

        $curl = $this->ssh->executeWithResult(
            'command -v curl',
        );

        if (! $curl->successful()) {
            throw new ApplicationInstallationException(
                'curl is required for Marzban installation.',
            );
        }
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
bash -c "$(curl -fsSL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install
BASH;
    }

    public function start(): void
    {
        $this->runCommand(
            command: 'marzban up',
            exception: ApplicationStartException::class,
            message: 'Failed to start Marzban.',
        );

        if ($this->resolveState() !== ApplicationState::Running) {
            throw new ApplicationStartException(
                'Marzban did not enter running state.',
            );
        }
    }

    public function stop(): void
    {
        $this->runCommand(
            command: 'marzban down',
            exception: ApplicationStopException::class,
            message: 'Failed to stop Marzban.',
        );

        if ($this->resolveState() !== ApplicationState::Installed) {
            throw new ApplicationStopException(
                'Marzban did not stop successfully.',
            );
        }
    }

    public function restart(): void
    {
        $this->runCommand(
            command: 'marzban restart',
            exception: ApplicationRestartException::class,
            message: 'Failed to restart Marzban.',
        );

        if ($this->resolveState() !== ApplicationState::Running) {
            throw new ApplicationRestartException(
                'Marzban did not restart successfully.',
            );
        }
    }

    public function healthCheck(): void
    {
        if ($this->resolveState() !== ApplicationState::Running) {
            throw new LogicException(
                'Marzban health check failed.',
            );
        }
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

    protected function uninstallCommand(): string
    {
        return <<<'BASH'
printf "y\ny\n" | marzban uninstall
BASH;
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::MODULE_INSTALL;
    }
}
