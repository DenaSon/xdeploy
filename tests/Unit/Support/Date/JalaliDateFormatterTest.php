<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Date;

use App\Support\Date\JalaliDateFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JalaliDateFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function dateTimeProvider(): iterable
    {
        yield 'Nowruz 1403' => [
            '2024-03-20 08:15:00',
            '1403/01/01 08:15',
        ];

        yield 'Leap Esfand' => [
            '2025-03-20 23:59:00',
            '1403/12/30 23:59',
        ];

        yield 'Renewal current expiry example' => [
            '2026-08-13 20:25:00',
            '1405/05/22 20:25',
        ];

        yield 'Renewal projected expiry example' => [
            '2026-08-27 20:25:00',
            '1405/06/05 20:25',
        ];
    }

    #[DataProvider('dateTimeProvider')]
    public function test_it_formats_gregorian_date_time_as_jalali(
        string $dateTime,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            JalaliDateFormatter::dateTime(
                new DateTimeImmutable($dateTime),
            ),
        );
    }

    public function test_it_supports_a_custom_date_time_separator(): void
    {
        $this->assertSame(
            '1405/05/22 - 20:25',
            JalaliDateFormatter::dateTime(
                new DateTimeImmutable('2026-08-13 20:25:00'),
                ' - ',
            ),
        );
    }
}
