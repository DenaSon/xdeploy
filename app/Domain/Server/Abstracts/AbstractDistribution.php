<?php

declare(strict_types=1);

namespace App\Domain\Server\Abstracts;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Contracts\Distribution;
use App\Domain\Server\Enums\LinuxCommand;
use App\Domain\Server\Exceptions\UnsupportedCommandException;

abstract class AbstractDistribution implements Distribution
{
    public function command(
        ApplicationType $application,
        LinuxCommand $command,
    ): string {
        $commands = $this->commands();

        $key = self::key($application, $command);

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
        ApplicationType $application,
        LinuxCommand $command,
    ): string {
        return "{$application->value}.{$command->value}";
    }
}
