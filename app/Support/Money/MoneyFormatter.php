<?php

declare(strict_types=1);

namespace App\Support\Money;

final class MoneyFormatter
{
    private const array PERSIAN_NUMBER_MAP = [
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
        ',' => '،',
    ];

    public static function tomanFromRial(
        int $rialAmount,
    ): string {
        $formatted = number_format(
            intdiv(
                $rialAmount,
                10,
            ),
        );

        return strtr(
            $formatted,
            self::PERSIAN_NUMBER_MAP,
        );
    }
}
