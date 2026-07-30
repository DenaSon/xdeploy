<?php

declare(strict_types=1);

namespace App\Domain\Application\ValueObjects;

use App\Domain\Application\Enums\SoftwareType;

final readonly class ProvidedSoftware
{
    public function __construct(
        public SoftwareType $type,
        public ?string $version = null,
    ) {}
}
