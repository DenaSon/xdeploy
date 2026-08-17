<?php

declare(strict_types=1);

namespace App\Domain\Integration\Cloudflare;

enum CloudflareZoneStatus: string
{
    case Initializing = 'initializing';
    case Pending = 'pending';
    case Active = 'active';
    case Moved = 'moved';
    case Unknown = 'unknown';

    public static function fromRemote(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unknown;
        }

        return self::tryFrom(
            strtolower(trim($value)),
        ) ?? self::Unknown;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
