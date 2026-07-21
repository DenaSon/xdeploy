<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Nginx;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\ValueObjects\ProvidedSoftware;

final readonly class NginxModule extends CommandModule
{
    public function type(): ModuleType
    {
        return ModuleType::Nginx;
    }

    public function name(): string
    {
        return 'Nginx';
    }

    protected function inspectCommand(): string
    {
        return 'nginx -v 2>&1';
    }

    protected function metadataFromOutput(string $output): array
    {
        preg_match('/\d+\.\d+\.\d+/', $output, $matches);

        return [
            'version' => $matches[0] ?? '',
        ];
    }

    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::Nginx),
        ];
    }

    protected function installCommand(): string
    {
        throw new \LogicException('Installation is not implemented yet.');
    }

    public function start(): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function stop(): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function restart(): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function uninstall(): void
    {
        throw new \LogicException('Not implemented.');
    }
}
