<?php

declare(strict_types=1);

namespace App\Domain\Platform\Registry;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformNotRegisteredException;

final readonly class PlatformRegistry implements PlatformRegistryInterface
{
    /**
     * @param  list<PlatformInterface>  $platforms
     */
    public function __construct(
        private array $platforms,
    ) {}

    /**
     * @return list<PlatformInterface>
     */
    public function all(): array
    {
        return $this->platforms;
    }

    public function find(
        PlatformType $type,
    ): PlatformInterface {
        foreach ($this->platforms as $platform) {
            if ($platform->type() === $type) {
                return $platform;
            }
        }

        throw new PlatformNotRegisteredException($type);
    }
}
