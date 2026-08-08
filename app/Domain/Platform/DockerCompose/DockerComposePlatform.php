<?php

declare(strict_types=1);

namespace App\Domain\Platform\DockerCompose;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Linux\Services\OperatingSystemInspector;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

final readonly class DockerComposePlatform implements PlatformInterface
{
    private const int COMMAND_TIMEOUT_SECONDS = 5;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
        private OperatingSystemInspector $operatingSystem,
    ) {}

    public function type(): PlatformType
    {
        return PlatformType::DockerCompose;
    }

    public function name(): string
    {
        return 'Docker Compose';
    }

    public function inspect(): PlatformInfo
    {
        try {
            $dockerResult = $this->ssh->executeWithResult(
                command: 'command -v docker >/dev/null 2>&1',
                timeout: SSHTimeout::QUICK,
            );

            if (! $dockerResult->successful()) {
                return $this->notInstalled();
            }

            $versionResult = $this->ssh->executeWithResult(
                command: sprintf(
                    'timeout --signal=TERM --kill-after=2 %d docker compose version 2>/dev/null',
                    self::COMMAND_TIMEOUT_SECONDS,
                ),
                timeout: SSHTimeout::QUICK,
            );

            if ($this->commandTimedOut($versionResult->exitCode)) {
                return $this->unknown();
            }

            if (! $versionResult->successful()) {
                return $this->notInstalled();
            }

            return new PlatformInfo(
                state: PlatformState::Installed,
                metadata: [
                    'version' => $this->extractVersion(
                        $versionResult->output,
                    ),
                ],
            );
        } catch (RuntimeException) {
            return $this->unknown();
        }
    }

    public function install(): void
    {
        $os = $this->operatingSystem->inspect();

        if (
            $os->id !== 'ubuntu'
            || ! in_array($os->versionId, ['22.04', '24.04'], true)
        ) {
            throw new PlatformInstallationException(
                sprintf(
                    'Automatic Docker Compose installation currently supports Ubuntu 22.04 and 24.04 only; detected [%s].',
                    $os->displayName(),
                ),
            );
        }

        $result = $this->privileged->executeWithResult(
            command: <<<'BASH'
set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

apt-get update

if ! apt-cache show docker-compose-v2 >/dev/null 2>&1; then
    apt-get install -y --no-install-recommends software-properties-common
    add-apt-repository -y universe
    apt-get update
fi

apt-get install -y --no-install-recommends docker-compose-v2

docker compose version
BASH,
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        if (! $result->successful()) {
            throw new PlatformInstallationException(
                'Docker Compose V2 installation failed.',
            );
        }

        if (! $this->inspect()->isInstalled()) {
            throw new PlatformInstallationException(
                'Docker Compose V2 installation verification failed.',
            );
        }
    }

    public function dependencies(): array
    {
        return [
            PlatformType::Docker,
        ];
    }

    public function systemPackages(): array
    {
        return [];
    }

    private function commandTimedOut(int $exitCode): bool
    {
        return in_array($exitCode, [124, 137], true);
    }

    private function extractVersion(string $output): ?string
    {
        preg_match(
            '/v?(\d+\.\d+\.\d+)/',
            $output,
            $matches,
        );

        return $matches[1] ?? null;
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
