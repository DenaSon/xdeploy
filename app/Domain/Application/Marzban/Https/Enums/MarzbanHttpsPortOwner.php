<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\Enums;

enum MarzbanHttpsPortOwner: string
{
    case None = 'none';
    case XDeployCaddy = 'xdeploy_caddy';
    case Nginx = 'nginx';
    case Apache = 'apache';
    case HaProxy = 'haproxy';
    case Caddy = 'caddy';
    case Docker = 'docker';
    case Other = 'other';
    case Unknown = 'unknown';
}
