<?php

declare(strict_types=1);

namespace App\Domain\Platform\Contracts;

use App\Domain\Platform\Enums\PlatformType;

interface PlatformRegistryInterface
{
    /**
     * Returns all registered platforms.
     *
     * @return list<PlatformInterface>
     */
    public function all(): array;

    /**
     * Find a platform by type.
     *
     * @throws \RuntimeException
     */
    public function find(
        PlatformType $type,
    ): PlatformInterface;
}
