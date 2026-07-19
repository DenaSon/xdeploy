<?php

declare(strict_types=1);

namespace App\Domain\Server\Abstracts;

use App\Domain\Module\Enums\ModuleType;
use App\Domain\Server\Contracts\Distribution;
use App\Domain\Server\Enums\LinuxCommand;
use App\Domain\Server\Exceptions\UnsupportedCommandException;

abstract class AbstractDistribution implements Distribution
{
    public function command(
        ModuleType $module,
        LinuxCommand $command,
    ): string {
        $commands = $this->commands();
        $key = self::key($module, $command);

        if (! isset($commands[$key])) {
            throw new UnsupportedCommandException($key);
        }

        return $commands[$key];
    }

    /**
     * @return array<string, non-empty-string>
     */
    abstract protected function commands(): array;

    protected static function key(
        ModuleType $module,
        LinuxCommand $command,
    ): string {
        return "{$module->value}.{$command->value}";
    }
}
