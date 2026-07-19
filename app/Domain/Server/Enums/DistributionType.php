<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum DistributionType: string
{
    case Ubuntu = 'ubuntu';
    case Debian = 'debian';
    case CentOS = 'centos';
}
