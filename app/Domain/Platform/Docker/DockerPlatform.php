<?php

declare(strict_types=1);

namespace App\Domain\Platform\Docker;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\StartablePlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Platform\Exceptions\PlatformRestartException;
use App\Domain\Platform\Exceptions\PlatformStartException;
use App\Domain\Platform\Exceptions\PlatformStopException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\Linux\Services\OperatingSystemInspector;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

final readonly class DockerPlatform implements PlatformInterface, StartablePlatformInterface
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
        private OperatingSystemInspector $operatingSystem,
        private InstallerSourceInterface $installerSource,
    ) {}

    public function type(): PlatformType
    {
        return PlatformType::Docker;
    }

    public function name(): string
    {
        return 'Docker';
    }

    public function inspect(): PlatformInfo
    {
        $existsResult = $this->ssh->executeWithResult(
            command: 'command -v docker >/dev/null 2>&1',
            timeout: SSHTimeout::QUICK,
        );

        if (! $existsResult->successful()) {
            return $this->notInstalled();
        }

        $versionResult = $this->ssh->executeWithResult(
            command: 'docker --version',
            timeout: SSHTimeout::QUICK,
        );

        if (! $versionResult->successful()) {
            return $this->unknown();
        }

        $serviceResult = $this->ssh->executeWithResult(
            command: 'systemctl is-active docker',
            timeout: SSHTimeout::QUICK,
        );

        $serviceState = trim(
            $serviceResult->output,
        );

        $state = match ($serviceState) {
            'active' => PlatformState::Running,

            'inactive',
            'failed',
            'deactivating' => PlatformState::Installed,

            default => PlatformState::Unknown,
        };

        return new PlatformInfo(
            state: $state,
            metadata: [
                'version' => $this->extractVersion(
                    $versionResult->output,
                ),
                'service_state' => $serviceState,
            ],
        );
    }

    public function install(): void
    {
        $os = $this->operatingSystem->inspect();

        if ($os->id !== 'ubuntu') {
            throw new PlatformInstallationException(
                sprintf(
                    'The xDeploy Docker installer currently supports Ubuntu only; detected [%s].',
                    $os->displayName(),
                ),
            );
        }

        if (
            ! in_array(
                $os->versionId,
                ['22.04', '24.04'],
                true,
            )
        ) {
            throw new PlatformInstallationException(
                sprintf(
                    'The xDeploy Docker installer does not support Ubuntu version [%s].',
                    $os->versionId ?? 'unknown',
                ),
            );
        }

        try {
            $command = $this->installerSource->buildExecutionCommand(
                relativePath: (string) config(
                    'xdeploy.installers.docker.ubuntu.path',
                ),
                expectedSha256: (string) config(
                    'xdeploy.installers.docker.ubuntu.sha256',
                ),
            );
        } catch (RuntimeException $exception) {
            throw new PlatformInstallationException(
                message: 'Docker installer could not be prepared.',
                previous: $exception,
            );
        }

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::DOCKER_INSTALL,
            sensitive: true,
        );

        if (! $result->successful()) {
            throw new PlatformInstallationException(
                'Docker installation using the xDeploy installer failed.',
            );
        }

        $info = $this->inspect();

        if (
            $info->isNotInstalled()
            || $info->isUnknown()
        ) {
            throw new PlatformInstallationException(
                'Docker installation verification failed.',
            );
        }
    }

    /**
     * @return list<PlatformType>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function systemPackages(): array
    {
        return [
            'curl',
            'ca-certificates',
        ];
    }

    public function start(): void
    {
        $result = $this->privileged->executeWithResult(
            command: 'systemctl enable --now docker',
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw new PlatformStartException(
                'Failed to start Docker.',
            );
        }

        if (! $this->inspect()->isRunning()) {
            throw new PlatformStartException(
                'Docker did not enter the running state.',
            );
        }
    }

    public function stop(): void
    {
        $result = $this->privileged->executeWithResult(
            command: 'systemctl stop docker',
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw new PlatformStopException(
                'Failed to stop Docker.',
            );
        }

        if (
            $this->inspect()->state
            !== PlatformState::Installed
        ) {
            throw new PlatformStopException(
                'Docker did not stop successfully.',
            );
        }
    }

    public function restart(): void
    {
        $result = $this->privileged->executeWithResult(
            command: 'systemctl restart docker',
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw new PlatformRestartException(
                'Failed to restart Docker.',
            );
        }

        if (! $this->inspect()->isRunning()) {
            throw new PlatformRestartException(
                'Docker did not restart successfully.',
            );
        }
    }

    private function extractVersion(
        string $output,
    ): ?string {
        preg_match(
            '/\d+\.\d+\.\d+/',
            $output,
            $matches,
        );

        return $matches[0] ?? null;
    }

    private function notInstalled(): PlatformInfo
    {
        return new PlatformInfo(
            state: PlatformState::NotInstalled,
        );
    }

    private function unknown(): PlatformInfo
    {
        return new PlatformInfo(
            state: PlatformState::Unknown,
        );
    }
}
