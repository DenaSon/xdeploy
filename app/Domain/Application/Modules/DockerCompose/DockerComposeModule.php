<?php

declare(strict_types=1);

namespace App\Domain\Application\Modules\DockerCompose;

use App\Domain\Application\Abstracts\CommandModule;
use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Enums\SoftwareType;
use App\Domain\Application\Exceptions\ModuleUninstallException;
use App\Domain\Application\ValueObjects\ProvidedSoftware;

final readonly class DockerComposeModule extends CommandModule
{
    public function type(): ModuleType
    {
        return ModuleType::DockerCompose;
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
        throw new ModuleUninstallException(
            'Docker Compose uninstall is not supported yet.',
        );
    }
}
