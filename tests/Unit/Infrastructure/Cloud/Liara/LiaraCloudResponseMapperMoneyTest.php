<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Liara;

use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;
use PHPUnit\Framework\TestCase;

final class LiaraCloudResponseMapperMoneyTest extends TestCase
{
    public function test_it_normalizes_decimal_toman_prices_without_losing_expected_precision(): void
    {
        $mapper = new LiaraCloudResponseMapper();

        $method = new \ReflectionMethod(
            LiaraCloudResponseMapper::class,
            'tomanToRial',
        );

        $method->setAccessible(true);

        self::assertSame(
            '14583',
            $method->invoke($mapper, '1458.33'),
        );

        self::assertSame(
            '1000000',
            $method->invoke($mapper, '100000'),
        );

        self::assertSame(
            '100001',
            $method->invoke($mapper, '10000.1'),
        );
    }
}
