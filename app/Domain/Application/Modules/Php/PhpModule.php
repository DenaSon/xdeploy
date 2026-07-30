<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Php;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\ValueObjects\ProvidedSoftware;

final readonly class PhpModule extends CommandModule
{
    public function type(): ModuleType
    {
        return ModuleType::Php;
    }

    public function name(): string
    {
        return 'PHP';
    }

    protected function inspectCommand(): string
    {
        return 'php --version';
    }

    protected function metadataFromOutput(string $output): array
    {
        preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $output, $matches);

        return [
            'version' => $matches[1] ?? '',
        ];
    }

    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::PHP),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
apt-get update &&
DEBIAN_FRONTEND=noninteractive apt-get install -y php
BASH;
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

    protected function uninstallCommand(): string
    {
        return <<<'BASH'
apt-get purge -y 'php*' &&
apt-get autoremove -y
BASH;
    }
}
