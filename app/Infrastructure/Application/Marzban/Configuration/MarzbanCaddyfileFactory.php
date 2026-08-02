<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Configuration;

use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;

final readonly class MarzbanCaddyfileFactory
{
    public function make(MarzbanDomain $domain): string
    {
        return <<<CADDY
# xDeploy: marzban-https
{$domain->value} {
    reverse_proxy unix//var/lib/marzban/marzban.socket
}
CADDY;
    }
}
