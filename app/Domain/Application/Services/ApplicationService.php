<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class ApplicationService
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
    ) {}

    /**
     * @return array<int, ApplicationInterface>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    public function inspect(
        ApplicationType $type,
    ): ApplicationInfo {
        return $this->registry
            ->find($type)
            ->inspect();
    }
}
