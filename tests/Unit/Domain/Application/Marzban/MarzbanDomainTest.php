<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\InvalidMarzbanDomainException;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarzbanDomainTest extends TestCase
{
    public function test_it_normalizes_a_valid_domain(): void
    {
        $domain = MarzbanDomain::from(
            '  Panel.Example.COM.  ',
        );

        self::assertSame(
            'panel.example.com',
            $domain->value,
        );
    }

    #[DataProvider('invalidDomains')]
    public function test_it_rejects_unsupported_domain_input(
        string $input,
    ): void {
        $this->expectException(
            InvalidMarzbanDomainException::class,
        );

        MarzbanDomain::from($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'empty' => [''];
        yield 'url' => ['https://panel.example.com'];
        yield 'path' => ['panel.example.com/dashboard'];
        yield 'query' => ['panel.example.com?test=1'];
        yield 'port' => ['panel.example.com:443'];
        yield 'ipv4' => ['203.0.113.10'];
        yield 'ipv6' => ['2001:db8::1'];
        yield 'wildcard' => ['*.example.com'];
        yield 'single label' => ['localhost'];
        yield 'internal whitespace' => ['panel .example.com'];
        yield 'control character' => ["panel.example.com\n"];
        yield 'empty label' => ['panel..example.com'];
        yield 'leading hyphen' => ['-panel.example.com'];
        yield 'trailing hyphen' => ['panel-.example.com'];
        yield 'numeric top level label' => ['panel.example.123'];
        yield 'multiple trailing dots' => ['panel.example.com..'];
    }
}
