<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

enum PlatformState: string
{
    /**
     * Platform is not installed on the server.
     */
    case NotInstalled = 'not_installed';

    /**
     * Platform is installed but not active.
     */
    case Installed = 'installed';

    /**
     * Platform is installed and ready to use.
     */
    case Running = 'running';

    /**
     * Platform state cannot be determined.
     */
    case Unknown = 'unknown';

    public function isInstalled(): bool
    {
        return match ($this) {
            self::Installed,
            self::Running => true,

            self::NotInstalled,
            self::Unknown => false,
        };
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
