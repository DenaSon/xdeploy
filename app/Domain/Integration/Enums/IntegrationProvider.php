<?php

declare(strict_types=1);

namespace App\Domain\Integration\Enums;

enum IntegrationProvider: string
{
    case Cloudflare = 'cloudflare';

    public function label(): string
    {
        return match ($this) {
            self::Cloudflare => 'Cloudflare',
        };
    }
}
