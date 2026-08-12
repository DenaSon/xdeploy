<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Contracts;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;

interface CaddySiteReaderInterface
{
    public function environmentReady(): bool;

    public function exists(
        CaddySiteKey $key,
    ): bool;

    public function matches(
        CaddySite $site,
    ): bool;
}
