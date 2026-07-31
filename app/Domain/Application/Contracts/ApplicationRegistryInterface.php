<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\Shared\Enums\ApplicationType;

interface ApplicationRegistryInterface
{
    /**
     * @return array<int, ApplicationInterface>
     */
    public function all(): array;

    public function find(ApplicationType $type): ApplicationInterface;
}
