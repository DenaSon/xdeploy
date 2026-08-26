<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Localization\PersianDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PersianDateTest extends TestCase
{
    public function test_formats_jalali_date_and_time_with_persian_digits(): void
    {
        $date = new DateTimeImmutable('2026-08-27 01:42:00');

        $this->assertSame(
            '۱۴۰۵/۰۶/۰۵ — ۰۱:۴۲',
            PersianDate::dateTime($date),
        );
    }

    public function test_formats_nowruz_boundary_correctly(): void
    {
        $this->assertSame(
            '۱۴۰۵/۰۱/۰۱',
            PersianDate::date(
                new DateTimeImmutable('2026-03-21 12:00:00'),
            ),
        );
    }

    public function test_returns_null_for_missing_date(): void
    {
        $this->assertNull(
            PersianDate::dateTime(null),
        );
    }
}
