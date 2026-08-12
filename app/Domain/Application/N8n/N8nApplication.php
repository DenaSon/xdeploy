<?php

declare(strict_types=1);

namespace App\Domain\Application\N8n;

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

final readonly class N8nApplication extends DockerComposeApplication
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
        return ApplicationType::N8n;
    }

    public function name(): string
    {
        return 'n8n';
    }

    public function requirements(): ApplicationRequirements
    {
        return new ApplicationRequirements(
            systemPackages: [
                'curl',
                'ca-certificates',
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
                SoftwareType::N8n,
            ),
        ];
    }

    protected function inspectCommand(): string
    {
        return <<<'BASH'
marker='/opt/n8n/.xdeploy-install-complete'

if ! command -v docker >/dev/null 2>&1; then
    test -f "$marker"
    exit $?
fi

container_id="$(
    docker ps -a \
        --filter "label=com.docker.compose.project=n8n" \
        --filter "label=com.docker.compose.service=n8n" \
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

    if [ -n "$image" ]; then
        printf '%s\n' "${image##*:}"
    fi

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
                    'xdeploy.installers.n8n.docker.path',
                ),
                expectedSha256: (string) config(
                    'xdeploy.installers.n8n.docker.sha256',
                ),
            );
        } catch (RuntimeException $exception) {
            throw new ApplicationInstallationException(
                message: 'n8n installer could not be prepared.',
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
rm -f /opt/n8n/.xdeploy-install-complete
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
        return 'n8n';
    }

    protected function composeService(): string
    {
        return 'n8n';
    }

    protected function composeFile(): string
    {
        return '/opt/n8n/docker-compose.yml';
    }

    protected function composeEnvFile(): string
    {
        return '/opt/n8n/.env';
    }
}
