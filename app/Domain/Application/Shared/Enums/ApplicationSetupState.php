<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationSetupState: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Complete = 'complete';
    case Unknown = 'unknown';
}
