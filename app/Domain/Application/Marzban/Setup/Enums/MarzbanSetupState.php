<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Setup\Enums;

enum MarzbanSetupState: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Complete = 'complete';
    case Unknown = 'unknown';
}
