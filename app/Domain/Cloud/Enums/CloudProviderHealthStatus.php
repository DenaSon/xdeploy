<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudProviderHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
}
