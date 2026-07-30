<?php

declare(strict_types=1);

namespace App\Domain\Platform\Docker;

use App\Domain\Application\Shared\Abstracts\CommandApplication;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\Shared\Exceptions\ApplicationRestartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStartException;
use App\Domain\Application\Shared\Exceptions\ApplicationStopException;
use App\Domain\Application\Shared\Exceptions\ApplicationUninstallException;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Support\SSH\SSHTimeout;

final readonly class DockerPlatform extends CommandApplication implements StartableInterface
{
    public function type(): ApplicationType
    {
        return ApplicationType::Docker;
    }

    public function name(): string
    {
        return 'Docker';
    }

    protected function inspectCommand(): string
    {
        return 'docker --version';
    }

    protected function resolveState(): ApplicationState
    {
        $result = $this->ssh->executeWithResult(
            'systemctl is-active docker',
        );

        if (
            $result->successful() &&
            trim($result->output) === 'active'
        ) {
            return ApplicationState::Running;
        }

        return ApplicationState::Installed;
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
            throw new ApplicationStartException(
                'Failed to start Docker.',
            );
        }

        if ($this->resolveState() !== ApplicationState::Running) {
            throw new ApplicationStartException(
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
            throw new ApplicationStopException(
                'Failed to stop Docker.',
            );
        }

        if ($this->resolveState() !== ApplicationState::Installed) {
            throw new ApplicationStopException(
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
            throw new ApplicationRestartException(
                'Failed to restart Docker.',
            );
        }

        if ($this->resolveState() !== ApplicationState::Running) {
            throw new ApplicationRestartException(
                'Docker did not restart successfully.',
            );
        }
    }

    protected function uninstallCommand(): string
    {
        throw new ApplicationUninstallException(
            'Docker uninstall is not supported yet.'
        );
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::DOCKER_INSTALL;
    }
}
