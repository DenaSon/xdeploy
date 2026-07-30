<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationState: string
{
    case NotInstalled = 'not_installed';
    case Installed = 'installed';
    case Running = 'running';
}
