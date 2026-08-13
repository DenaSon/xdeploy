<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationOperationType: string
{
    case Install = 'install';
    case Uninstall = 'uninstall';
}
