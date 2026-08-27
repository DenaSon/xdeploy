<?php

declare(strict_types=1);

namespace App\Support\Date;

use DateTimeInterface;

final class JalaliDateFormatter
{
    private const array PERSIAN_DIGITS = [
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
    ];

    public static function dateTime(
        DateTimeInterface $dateTime,
        string $separator = ' ',
        bool $persianDigits = false,
    ): string {
        [$year, $month, $day] = self::gregorianToJalali(
            year: (int) $dateTime->format('Y'),
            month: (int) $dateTime->format('n'),
            day: (int) $dateTime->format('j'),
        );

        $formatted = sprintf(
            '%04d/%02d/%02d%s%s',
            $year,
            $month,
            $day,
            $separator,
            $dateTime->format('H:i'),
        );

        return $persianDigits
            ? strtr($formatted, self::PERSIAN_DIGITS)
            : $formatted;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function gregorianToJalali(
        int $year,
        int $month,
        int $day,
    ): array {
        $daysBeforeMonth = [
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

        $leapYearBase = $month > 2
            ? $year + 1
            : $year;

        $days = 355_666
            + (365 * $year)
            + intdiv($leapYearBase + 3, 4)
            - intdiv($leapYearBase + 99, 100)
            + intdiv($leapYearBase + 399, 400)
            + $day
            + $daysBeforeMonth[$month - 1];

        $jalaliYear = -1595 + (33 * intdiv($days, 12_053));
        $days %= 12_053;

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
