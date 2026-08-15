<?php

declare(strict_types=1);

namespace App\Application\Support;

use InvalidArgumentException;

final class SupportContent
{
    public const int SUBJECT_MAX_LENGTH = 160;

    public const int MESSAGE_MAX_LENGTH = 10_000;

    public static function subject(string $value): string
    {
        return self::normalizeRequired(
            value: $value,
            field: 'Support request subject',
            maxLength: self::SUBJECT_MAX_LENGTH,
        );
    }

    public static function message(string $value): string
    {
        return self::normalizeRequired(
            value: $value,
            field: 'Support message',
            maxLength: self::MESSAGE_MAX_LENGTH,
        );
    }

    private static function normalizeRequired(
        string $value,
        string $field,
        int $maxLength,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                sprintf('%s cannot be empty.', $field),
            );
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s cannot exceed %d characters.',
                    $field,
                    $maxLength,
                ),
            );
        }

        return $value;
    }
}
