<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

/**
 * Runtime power state of a provisioned cloud server.
 *
 * CloudServerStatus represents the high-level resource lifecycle,
 * while this enum represents whether the existing server is currently
 * running, stopped, transitioning, or in an error state.
 */
enum CloudServerPowerState: string
{
    case Running = 'running';

    case Stopped = 'stopped';

    case Transitioning = 'transitioning';

    case Error = 'error';

    case Unknown = 'unknown';

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isStopped(): bool
    {
        return $this === self::Stopped;
    }

    public function isTransitioning(): bool
    {
        return $this === self::Transitioning;
    }

    public function isError(): bool
    {
        return $this === self::Error;
    }

    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }
}
