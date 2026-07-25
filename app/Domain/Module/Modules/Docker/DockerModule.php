<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Docker;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\Enums\ModuleState;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\Exceptions\ModuleRestartException;
use App\Domain\Module\Exceptions\ModuleStartException;
use App\Domain\Module\Exceptions\ModuleStopException;
use App\Domain\Module\Exceptions\ModuleUninstallException;
use App\Domain\Module\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;
use LogicException;

final readonly class DockerModule extends CommandModule implements StartableInterface
{
    public function type(): ModuleType
    {
        return ModuleType::Docker;
    }

    public function name(): string
    {
        return 'Docker';
    }

    protected function inspectCommand(): string
    {
        return 'docker --version';
    }

    protected function resolveState(): ModuleState
    {
        $result = $this->ssh->executeWithResult(
            'systemctl is-active docker',
        );

        if (
            $result->successful() &&
            trim($result->output) === 'active'
        ) {
            return ModuleState::Running;
        }

        return ModuleState::Installed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        preg_match('/\d+\.\d+\.\d+/', $output, $matches);

        return [
            'version' => $matches[0] ?? null,
        ];
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::Docker),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
curl -fsSL https://get.docker.com | sh
BASH;
    }

    public function start(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl start docker',
        );

        if (! $result->successful()) {
            throw new ModuleStartException(
                'Failed to start Docker.',
            );
        }

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleStartException(
                'Docker did not enter the running state.',
            );
        }
    }

    public function stop(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl stop docker',
        );

        if (! $result->successful()) {
            throw new ModuleStopException(
                'Failed to stop Docker.',
            );
        }

        if ($this->resolveState() !== ModuleState::Installed) {
            throw new ModuleStopException(
                'Docker did not stop successfully.',
            );
        }
    }

    public function restart(): void
    {
        $result = $this->ssh->executeWithResult(
            'systemctl restart docker',
        );

        if (! $result->successful()) {
            throw new ModuleRestartException(
                'Failed to restart Docker.',
            );
        }

        if ($this->resolveState() !== ModuleState::Running) {
            throw new ModuleRestartException(
                'Docker did not restart successfully.',
            );
        }
    }

    protected function uninstallCommand(): string
    {
        throw new ModuleUninstallException(
            'Docker uninstall is not supported yet.'
        );
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DOCKER_INSTALL;
    }
}
