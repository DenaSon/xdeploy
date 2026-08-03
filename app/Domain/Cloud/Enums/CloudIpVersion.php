<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudIpVersion: string
{
    case IPv4 = 'ipv4';
    case IPv6 = 'ipv6';
}
