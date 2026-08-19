<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Money;

use App\Support\Money\MoneyFormatter;
use PHPUnit\Framework\TestCase;

final class MoneyFormatterTest extends TestCase
{
    public function test_it_converts_rial_to_persian_formatted_toman(): void
    {
        $this->assertSame(
            '۲۱۶،۵۷۶',
            MoneyFormatter::tomanFromRial(
                2_165_760,
            ),
        );
    }

    public function test_zero_is_formatted_as_persian_zero_toman(): void
    {
        $this->assertSame(
            '۰',
            MoneyFormatter::tomanFromRial(
                0,
            ),
        );
    }
}
