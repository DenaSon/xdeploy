<?php

declare(strict_types=1);

namespace App\Domain\Platform\DockerCompose;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformInstallationException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

final readonly class DockerComposePlatform implements PlatformInterface
{
    private const COMMAND_TIMEOUT_SECONDS = 5;

    public function __construct(
        private SSHConnectionInterface $ssh,
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
        throw new PlatformInstallationException(
            'Docker Compose is unavailable after installing the docker-compose-plugin system package.',
        );
    }

    /**
     * @return list<PlatformType>
     */
    public function dependencies(): array
    {
        return [
            PlatformType::Docker,
        ];
    }

    /**
     * @return list<string>
     */
    public function systemPackages(): array
    {
        return [
            'docker-compose-plugin',
        ];
    }

    private function commandTimedOut(int $exitCode): bool
    {
        return in_array(
            $exitCode,
            [124, 137],
            true,
        );
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
