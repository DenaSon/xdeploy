<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\ValueObjects;

use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class ApplicationDependency
{
    public function __construct(
        public ApplicationType $type,
    ) {}
}
