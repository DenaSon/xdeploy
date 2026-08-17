<?php

declare(strict_types=1);

namespace App\Support\Cloud;

use App\Domain\Cloud\Enums\CloudProviderType;

final class CloudProviderPublicIdentity
{
    /**
     * Public provider codes are stable presentation identifiers.
     *
     * They must never be derived from provider ordering or reused for a
     * different provider, because customer-facing state may outlive a
     * provider's purchase availability.
     *
     * @var array<string, string>
     */
    private const array CODES = [
        'arvan' => 'core-1',
        'liara' => 'core-2',
    ];

    /**
     * @var array<string, string>
     */
    private const array LABELS = [
        'arvan' => 'Core-1',
        'liara' => 'Core-2',
    ];

    public static function code(
        CloudProviderType $provider,
    ): string {
        return self::CODES[$provider->value];
    }

    public static function label(
        CloudProviderType $provider,
    ): string {
        return self::LABELS[$provider->value];
    }

    public static function description(
        CloudProviderType $provider,
    ): string {
        return sprintf(
            'زیرساخت ابری %s',
            self::label($provider),
        );
    }

    public static function provider(
        string $publicCode,
    ): ?CloudProviderType {
        $publicCode = strtolower(
            trim($publicCode),
        );

        $provider = array_search(
            $publicCode,
            self::CODES,
            true,
        );

        if (! is_string($provider)) {
            return null;
        }

        return CloudProviderType::tryFrom(
            $provider,
        );
    }
}
