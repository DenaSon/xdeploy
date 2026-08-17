<?php

declare(strict_types=1);

namespace App\Domain\Integration\Cloudflare;

final class CloudflareDnsRecordTypes
{
    public const A = 'A';

    public const AAAA = 'AAAA';

    public const CNAME = 'CNAME';

    public const TXT = 'TXT';

    public const MX = 'MX';

    /** @return list<string> */
    public static function manageable(): array
    {
        return [
            self::A,
            self::AAAA,
            self::CNAME,
            self::TXT,
            self::MX,
        ];
    }

    public static function supports(string $type): bool
    {
        return in_array(
            strtoupper(trim($type)),
            self::manageable(),
            true,
        );
    }

    public static function proxiable(string $type): bool
    {
        return in_array(
            strtoupper(trim($type)),
            [self::A, self::AAAA, self::CNAME],
            true,
        );
    }

    public static function requiresPriority(string $type): bool
    {
        return strtoupper(trim($type)) === self::MX;
    }
}
