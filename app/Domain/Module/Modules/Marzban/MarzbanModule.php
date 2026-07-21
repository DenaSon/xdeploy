<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Marzban;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\Exceptions\ModuleRestartException;
use App\Domain\Module\Exceptions\ModuleStartException;
use App\Domain\Module\Exceptions\ModuleStopException;
use App\Domain\Module\ValueObjects\ModuleDependency;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;
use LogicException;

final readonly class MarzbanModule extends CommandModule implements StartableInterface
{
    public function type(): ModuleType
    {
        return ModuleType::Marzban;
    }

    public function name(): string
    {
        return 'Marzban';
    }

    protected function inspectCommand(): string
    {
        return 'test -d /opt/marzban';
    }

    protected function resolveState(): ModuleState
    {
        $result = $this->ssh->executeWithResult(
            'docker ps --filter "name=marzban" --format "{{.Names}}"',
        );

        return $result->output !== ''
            ? ModuleState::Running
            : ModuleState::Installed;
    }

    /**
     * @return array<string, mixed>
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
            new ProvidedSoftware(SoftwareType::Marzban),
            new ProvidedSoftware(SoftwareType::Xray),
        ];
    }

    /**
     * @return list<ModuleDependency>
     */
    public function dependencies(): array
    {
        return [
            new ModuleDependency(ModuleType::Docker),
            new ModuleDependency(ModuleType::DockerCompose),
        ];
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
            exception: ModuleStartException::class,
            message: 'Failed to start Marzban.',
        );

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleStartException(
                'Marzban did not enter the running state.',
            );
        }
    }

    public function stop(): void
    {
        $this->runCommand(
            command: 'marzban down',
            exception: ModuleStopException::class,
            message: 'Failed to stop Marzban.',
        );

        if ($this->resolveState() !== ModuleState::Installed) {
            throw new ModuleStopException(
                'Marzban did not stop successfully.',
            );
        }
    }

    public function restart(): void
    {
        $this->runCommand(
            command: 'marzban restart',
            exception: ModuleRestartException::class,
            message: 'Failed to restart Marzban.',
        );

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleRestartException(
                'Marzban did not restart successfully.',
            );
        }
    }

    public function uninstall(): void
    {
        throw new LogicException('Not implemented.');
    }

    protected function checkRequirements(): void
    {
        // Example:
        // - Verify Linux distribution
        // - Verify root privileges
        // - Verify supported architecture
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

    protected function installTimeout(): int
    {
        return SSHTimeout::MODULE_INSTALL;
    }
}
