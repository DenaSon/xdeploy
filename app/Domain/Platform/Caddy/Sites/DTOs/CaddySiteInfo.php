<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\DTOs;

use App\Domain\Platform\Caddy\Sites\CaddySiteKey;

final readonly class CaddySiteInfo
{
    public function __construct(
        public CaddySiteKey $key,
        public bool $exists,
        public bool $managed,
    ) {}

    public function isMissing(): bool
    {
        return ! $this->exists;
    }

    public function isManaged(): bool
    {
        return $this->exists && $this->managed;
    }

    public function hasConflict(): bool
    {
        return $this->exists && ! $this->managed;
    }
}
