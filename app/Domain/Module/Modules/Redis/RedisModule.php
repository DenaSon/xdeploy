<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Redis;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\ValueObjects\ProvidedSoftware;

final readonly class RedisModule extends CommandModule
{
    public function type(): ModuleType
    {
        return ModuleType::Redis;
    }

    public function name(): string
    {
        return 'Redis';
    }

    protected function inspectCommand(): string
    {
        return 'redis-server --version';
    }

    protected function metadataFromOutput(string $output): array
    {
        preg_match('/v=(\d+\.\d+\.\d+)/', $output, $matches);

        return [
            'version' => $matches[1] ?? '',
        ];
    }

    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::Redis),
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
