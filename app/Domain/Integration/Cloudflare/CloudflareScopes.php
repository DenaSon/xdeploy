<?php

declare(strict_types=1);

namespace App\Domain\Integration\Cloudflare;

final class CloudflareScopes
{
    public const ACCOUNT_SETTINGS_READ = 'account-settings.read';

    public const ZONE_READ = 'zone.read';

    public const DNS_READ = 'dns.read';

    public const OFFLINE_ACCESS = 'offline_access';

    /** @return list<string> */
    public static function read(): array
    {
        return [
            self::ACCOUNT_SETTINGS_READ,
            self::ZONE_READ,
            self::DNS_READ,
        ];
    }

    /** @return list<string> */
    public static function oauth(): array
    {
        return [
            ...self::read(),
            self::OFFLINE_ACCESS,
        ];
    }

    /**
     * @param array<int, mixed> $granted
     * @param list<string>|null $required
     * @return list<string>
     */
    public static function missing(
        array $granted,
        ?array $required = null,
    ): array {
        $granted = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $scope): string => is_string($scope)
                            ? trim($scope)
                            : '',
                        $granted,
                    ),
                    static fn (string $scope): bool => $scope !== '',
                ),
            ),
        );

        return array_values(
            array_diff(
                $required ?? self::read(),
                $granted,
            ),
        );
    }
}
