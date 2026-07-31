<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationState: string
{
    case NotInstalled = 'not_installed';
    case Installed = 'installed';
    case Running = 'running';
    case Unknown = 'unknown';

    public function isInstalled(): bool
    {
        return $this === self::Installed
            || $this === self::Running;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isNotInstalled(): bool
    {
        return $this === self::NotInstalled;
    }

    public function isUnknown(): bool
    {
        return $this === self::Unknown;
    }
}
