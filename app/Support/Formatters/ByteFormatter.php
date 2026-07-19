<?php

declare(strict_types=1);

namespace App\Support\Formatters;

final class ByteFormatter
{
    private const UNITS = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB',
    ];

    public static function format(
        int $bytes,
        int $precision = 2,
    ): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $value = $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count(self::UNITS) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf(
            '%.'.$precision.'f %s',
            $value,
            self::UNITS[$unit],
        );
    }
}
