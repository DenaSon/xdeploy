<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\ValueObjects;

use App\Domain\Application\Shared\Enums\SoftwareType;

final readonly class ProvidedSoftware
{
    public function __construct(
        public SoftwareType $type,
        public ?string $version = null,
    ) {}
}
