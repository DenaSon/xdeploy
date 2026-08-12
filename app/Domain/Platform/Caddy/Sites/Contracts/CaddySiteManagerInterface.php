<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Contracts;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\DTOs\CaddySiteMutationResult;

interface CaddySiteManagerInterface
{
    public function upsert(
        CaddySite $site,
    ): CaddySiteMutationResult;

    public function remove(
        CaddySiteKey $key,
    ): CaddySiteMutationResult;
}
