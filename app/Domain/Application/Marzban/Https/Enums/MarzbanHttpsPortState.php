<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\Enums;

enum MarzbanHttpsPortState: string
{
    case Available = 'available';
    case Managed = 'managed';
    case Conflict = 'conflict';
    case Unknown = 'unknown';
}
