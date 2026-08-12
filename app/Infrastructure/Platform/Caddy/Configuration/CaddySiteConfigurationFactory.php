<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Caddy\Configuration;

use App\Domain\Platform\Caddy\Sites\CaddySite;

final readonly class CaddySiteConfigurationFactory
{
    public function make(
        CaddySite $site,
    ): string {
        return <<<CADDY
# xDeploy: caddy-site:{$site->key->value}
{$site->domain} {
    reverse_proxy {$site->upstream}
}

CADDY;
    }
}
