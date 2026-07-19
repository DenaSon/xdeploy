<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum LinuxCommand: string
{
    case Exists = 'exists';
    case Version = 'version';
    case Install = 'install';
    case Remove = 'remove';
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';
}
