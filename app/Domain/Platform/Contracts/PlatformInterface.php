<?php

declare(strict_types=1);

namespace App\Domain\Platform\Contracts;

use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformType;

interface PlatformInterface
{
    /**
     * Returns the unique platform type.
     */
    public function type(): PlatformType;

    /**
     * Returns the platform display name.
     */
    public function name(): string;

    /**
     * Inspect the current platform state.
     */
    public function inspect(): PlatformInfo;

    /**
     * Install the platform.
     */
    public function install(): void;

    /**
     * Returns required platform dependencies.
     *
     * @return list<PlatformType>
     */
    public function dependencies(): array;

    /**
     * Returns required operating-system packages.
     *
     * @return list<string>
     */
    public function systemPackages(): array;
}
