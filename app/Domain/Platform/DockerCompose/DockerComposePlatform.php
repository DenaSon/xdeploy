<?php

declare(strict_types=1);

namespace App\Domain\Platform\DockerCompose;

use App\Domain\Application\Shared\Abstracts\CommandApplication;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\Shared\Exceptions\ApplicationUninstallException;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;

final readonly class DockerComposePlatform extends CommandApplication
{
    public function type(): ApplicationType
    {
        return ApplicationType::DockerCompose;
    }

    public function name(): string
    {
        return 'Docker Compose';
    }

    protected function inspectCommand(): string
    {
        return 'docker compose version';
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(string $output): array
    {
        preg_match('/v?(\d+\.\d+\.\d+)/', $output, $matches);

        return [
            'version' => $matches[1] ?? null,
        ];
    }

    /**
     * @return list<ProvidedSoftware>
     */
    public function provides(): array
    {
        return [
            new ProvidedSoftware(
                SoftwareType::DockerCompose,
            ),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
apt-get update &&
apt-get install -y docker-compose-plugin
BASH;
    }

    protected function uninstallCommand(): string
    {
        throw new ApplicationUninstallException(
            'Docker Compose uninstall is not supported yet.',
        );
    }
}
