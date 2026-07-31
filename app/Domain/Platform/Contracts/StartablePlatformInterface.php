<?php

declare(strict_types=1);

namespace App\Domain\Platform\Contracts;

interface StartablePlatformInterface
{
    /**
     * Start the platform service.
     */
    public function start(): void;

    /**
     * Stop the platform service.
     */
    public function stop(): void;

    /**
     * Restart the platform service.
     */
    public function restart(): void;
}
