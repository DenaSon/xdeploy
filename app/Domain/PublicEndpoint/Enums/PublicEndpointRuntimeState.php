<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\Enums;

enum PublicEndpointRuntimeState: string
{
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case ManagedIncomplete = 'managed_incomplete';
    case ManagedExternally = 'managed_externally';
    case Misconfigured = 'misconfigured';
    case Unknown = 'unknown';
}
