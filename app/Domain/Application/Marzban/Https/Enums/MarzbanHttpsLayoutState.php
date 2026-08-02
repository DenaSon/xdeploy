<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\Enums;

enum MarzbanHttpsLayoutState: string
{
    case Supported = 'supported';
    case Missing = 'missing';
    case Unreadable = 'unreadable';
    case InvalidCompose = 'invalid_compose';
    case UnsupportedCompose = 'unsupported_compose';
}
