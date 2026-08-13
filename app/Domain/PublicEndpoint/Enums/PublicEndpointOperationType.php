<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\Enums;

enum PublicEndpointOperationType: string
{
    case Enable = 'enable';
    case Disable = 'disable';
}
