<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum ServerStatus: string
{
    /**
     * Server is enabled and can be used.
     */
    case Active = 'active';

    /**
     * Server is disabled and unavailable.
     */
    case Inactive = 'inactive';
}
