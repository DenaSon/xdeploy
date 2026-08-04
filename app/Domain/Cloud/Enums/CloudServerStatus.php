<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudServerStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function isReady(): bool
    {
        return $this === self::Active;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function isPending(): bool
    {
        return $this === self::Provisioning;
    }
}
