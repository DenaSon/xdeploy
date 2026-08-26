<?php

declare(strict_types=1);

namespace App\Support\Localization;

use DateTimeInterface;

final class PersianDate
{
    /**
     * Format a Gregorian date as a Persian (Jalali) date.
     */
    public static function format(
        ?DateTimeInterface $date,
        bool $withTime = true,
    ): ?string {
        if ($date === null) {
            return null;
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        $formatted = sprintf(
            '%04d/%02d/%02d',
            $year,
            $month,
            $day,
        );

        if ($withTime) {
            $formatted .= ' — '.$date->format('H:i');
        }

        return self::toPersianDigits($formatted);
    }

    public static function date(?DateTimeInterface $date): ?string
    {
        return self::format($date, withTime: false);
    }

    public static function dateTime(?DateTimeInterface $date): ?string
    {
        return self::format($date);
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr(
            $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            ],
        );
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private static function gregorianToJalali(
        int $gregorianYear,
        int $gregorianMonth,
        int $gregorianDay,
    ): array {
        $gregorianMonthDays = [
            0,
            31,
            59,
            90,
            120,
            151,
            181,
            212,
            243,
            273,
            304,
            334,
        ];

        if ($gregorianYear > 1600) {
            $jalaliYear = 979;
            $gregorianYear -= 1600;
        } else {
            $jalaliYear = 0;
            $gregorianYear -= 621;
        }

        $adjustedYear = $gregorianMonth > 2
            ? $gregorianYear + 1
            : $gregorianYear;

        $days = 365 * $gregorianYear
            + intdiv($adjustedYear + 3, 4)
            - intdiv($adjustedYear + 99, 100)
            + intdiv($adjustedYear + 399, 400)
            - 80
            + $gregorianDay
            + $gregorianMonthDays[$gregorianMonth - 1];

        $jalaliYear += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jalaliYear += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jalaliYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jalaliMonth = 1 + intdiv($days, 31);
            $jalaliDay = 1 + ($days % 31);
        } else {
            $jalaliMonth = 7 + intdiv($days - 186, 30);
            $jalaliDay = 1 + (($days - 186) % 30);
        }

        return [
            $jalaliYear,
            $jalaliMonth,
            $jalaliDay,
        ];
    }
}
