<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationOperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Pending
            || $this === self::Running;
    }
}
