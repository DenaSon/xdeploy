<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg;

use App\Domain\Application\Shared\Abstracts\DockerComposeApplication;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\Shared\Exceptions\ApplicationInstallationException;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

final readonly class AmneziaWgApplication extends DockerComposeApplication
{
    public function __construct(
        SSHConnectionInterface $ssh,
        PrivilegedCommandExecutor $privileged,
        private InstallerSourceInterface $installerSource,
    ) {
        parent::__construct(
            ssh: $ssh,
            privileged: $privileged,
        );
    }

    public function type(): ApplicationType
    {
        return ApplicationType::AmneziaWg;
    }

    public function name(): string
    {
        return 'AmneziaWG';
    }

    public function requirements(): ApplicationRequirements
    {
        return new ApplicationRequirements(
            systemPackages: [
                'curl',
                'ca-certificates',
                'iproute2',
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
                SoftwareType::AmneziaWg,
            ),
        ];
    }

    protected function inspectCommand(): string
    {
        return <<<'BASH'
marker='/opt/xdeploy/apps/amneziawg/.xdeploy-install-complete'

if ! command -v docker >/dev/null 2>&1; then
    test -f "$marker"
    exit $?
fi

container_id="$(
    docker ps -a \
        --filter "label=com.docker.compose.project=amneziawg" \
        --filter "label=com.docker.compose.service=amneziawg" \
        --format "{{.ID}}" \
        2>/dev/null \
        | head -n 1
)"

if [ -n "$container_id" ]; then
    image="$(
        docker inspect \
            --format '{{.Config.Image}}' \
            "$container_id" 2>/dev/null || true
    )"

    case "$image" in
        amneziavpn/amneziawg-go:0.2.19|\
        amneziavpn/amneziawg-go:0.2.19@sha256:*)
            printf '%s\n' '0.2.19'
            ;;
    esac

    exit 0
fi

test -f "$marker"
BASH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(
        string $output,
    ): array {
        $version = trim($output);

        if (
            $version === ''
            || preg_match(
                '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$/',
                $version,
            ) !== 1
        ) {
            return [];
        }

        return [
            'version' => $version,
        ];
    }

    protected function installCommand(): string
    {
        try {
            return $this->installerSource->buildExecutionCommand(
                relativePath: (string) config(
                    'xdeploy.installers.amneziawg.docker.path',
                ),
                expectedSha256: (string) config(
                    'xdeploy.installers.amneziawg.docker.sha256',
                ),
            );
        } catch (RuntimeException $exception) {
            throw new ApplicationInstallationException(
                message: 'AmneziaWG installer could not be prepared.',
                previous: $exception,
            );
        }
    }

    protected function installSensitive(): bool
    {
        return true;
    }

    protected function uninstallCommand(): string
    {
        return sprintf(
            "%s\n\n%s",
            $this->composeCommand(
                'down --remove-orphans',
            ),
            <<<'BASH'
rm -f /opt/xdeploy/apps/amneziawg/.xdeploy-install-complete
BASH,
        );
    }

    protected function uninstallTimeout(): int
    {
        return SSHTimeout::APPLICATION_UNINSTALL;
    }

    protected function installTimeout(): int
    {
        return SSHTimeout::APPLICATION_INSTALL;
    }

    protected function composeProject(): string
    {
        return 'amneziawg';
    }

    protected function composeService(): string
    {
        return 'amneziawg';
    }

    protected function composeFile(): string
    {
        return '/opt/xdeploy/apps/amneziawg/docker-compose.yml';
    }

    protected function composeEnvFile(): string
    {
        return '/opt/xdeploy/apps/amneziawg/.env';
    }
}
