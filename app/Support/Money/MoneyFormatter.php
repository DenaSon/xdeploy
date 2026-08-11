<?php

declare(strict_types=1);

namespace App\Support\Money;

final class MoneyFormatter
{
    public static function tomanFromRial(
        int $rialAmount,
    ): string {
        return number_format(
            intdiv(
                $rialAmount,
                10,
            ),
        );
    }
}
