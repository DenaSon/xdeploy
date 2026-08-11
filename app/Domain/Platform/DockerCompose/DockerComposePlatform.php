<?php

declare(strict_types=1);

namespace App\Domain\Platform\DockerCompose;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Domain\Platform\Support\SupportedPlatformOperatingSystems;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
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
        private InstallerSourceInterface $installerSource,
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
            ! SupportedPlatformOperatingSystems::supportsDockerStack(
                $os,
            )
        ) {
            throw new PlatformInstallationException(
                sprintf(
                    'Automatic Docker Compose installation does not support [%s]. Supported systems: %s.',
                    $os->displayName(),
                    SupportedPlatformOperatingSystems::dockerStackDisplayList(),
                ),
            );
        }

        try {
            $command = $this->installerSource->buildExecutionCommand(
                relativePath: (string) config(
                    'xdeploy.installers.docker.debian_family.path',
                ),
                expectedSha256: (string) config(
                    'xdeploy.installers.docker.debian_family.sha256',
                ),
            );
        } catch (RuntimeException $exception) {
            throw new PlatformInstallationException(
                message: 'Docker Compose installer could not be prepared.',
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
