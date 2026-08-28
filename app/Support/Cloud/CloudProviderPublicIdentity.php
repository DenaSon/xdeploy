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
        'parspack' => 'core-3',
    ];

    /**
     * Customer-facing provider names. Canonical provider names stay internal
     * and stable public codes continue to be used for Livewire routing.
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        'arvan' => 'Core-1',
        'liara' => 'دماوند',
        'parspack' => 'زاگرس',
    ];

    /**
     * @var array<string, string>
     */
    private const array DESCRIPTIONS = [
        'arvan' => 'زیرساخت ابری Core-1',
        'liara' => 'زیرساخت ایران',
        'parspack' => 'انتخاب موقعیت‌های بیشتر',
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
        return self::DESCRIPTIONS[$provider->value];
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
