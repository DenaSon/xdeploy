<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\Enums;

enum MarzbanHttpsState: string
{
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case ManagedExternally = 'managed_externally';
    case Misconfigured = 'misconfigured';
    case Unknown = 'unknown';
}
