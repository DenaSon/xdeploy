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
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class DockerPlatform implements PlatformInterface, StartablePlatformInterface
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
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
            'command -v docker >/dev/null 2>&1',
        );

        if (! $existsResult->successful()) {
            return new PlatformInfo(
                state: PlatformState::NotInstalled,
            );
        }

        $versionResult = $this->ssh->executeWithResult(
            'docker --version',
        );

        if (! $versionResult->successful()) {
            return new PlatformInfo(
                state: PlatformState::Unknown,
            );
        }

        $serviceResult = $this->ssh->executeWithResult(
            'systemctl is-active docker',
        );

        $serviceState = trim($serviceResult->output);

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
        $this->preflight->ensureRoot();

        $result = $this->ssh->executeWithResult(
            command: 'curl -fsSL https://get.docker.com | sh',
            timeout: SSHTimeout::DOCKER_INSTALL,
        );

        if (! $result->successful()) {
            throw new PlatformInstallationException(
                'Docker installation failed.',
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
        $this->preflight->ensureRoot();

        $result = $this->ssh->executeWithResult(
            'systemctl enable --now docker',
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
        $this->preflight->ensureRoot();

        $result = $this->ssh->executeWithResult(
            'systemctl stop docker',
        );

        if (! $result->successful()) {
            throw new PlatformStopException(
                'Failed to stop Docker.',
            );
        }

        if ($this->inspect()->state !== PlatformState::Installed) {
            throw new PlatformStopException(
                'Docker did not stop successfully.',
            );
        }
    }

    public function restart(): void
    {
        $this->preflight->ensureRoot();

        $result = $this->ssh->executeWithResult(
            'systemctl restart docker',
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

    private function extractVersion(string $output): ?string
    {
        preg_match(
            '/\d+\.\d+\.\d+/',
            $output,
            $matches,
        );

        return $matches[0] ?? null;
    }
}
