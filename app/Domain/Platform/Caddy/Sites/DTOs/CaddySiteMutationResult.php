<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\DTOs;

use App\Domain\Platform\Caddy\Sites\CaddySiteKey;

final readonly class CaddySiteMutationResult
{
    public function __construct(
        public CaddySiteKey $key,
        public bool $changed,
    ) {}
}
