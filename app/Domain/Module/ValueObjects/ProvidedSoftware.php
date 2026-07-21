<?php

declare(strict_types=1);

namespace App\Domain\Module\ValueObjects;

use App\Domain\Module\Enums\SoftwareType;

final readonly class ProvidedSoftware
{
    public function __construct(
        public SoftwareType $type,
        public ?string $version = null,
    ) {}
}
