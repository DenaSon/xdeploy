<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PublicEndpoint;

use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicEndpointDomainTest extends TestCase
{
    public function test_it_normalizes_a_valid_domain(): void
    {
        self::assertSame(
            'panel.example.com',
            PublicEndpointDomain::from(
                ' PANEL.Example.COM. ',
            )->value,
        );
    }

    #[DataProvider('invalidDomains')]
    public function test_it_rejects_an_invalid_domain(
        string $domain,
    ): void {
        $this->expectException(
            InvalidPublicEndpointDomainException::class,
        );

        PublicEndpointDomain::from(
            $domain,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'empty' => [''];
        yield 'url' => ['https://panel.example.com'];
        yield 'path' => ['panel.example.com/dashboard'];
        yield 'port' => ['panel.example.com:443'];
        yield 'wildcard' => ['*.example.com'];
        yield 'single label' => ['localhost'];
        yield 'ipv4' => ['192.0.2.10'];
        yield 'empty label' => ['panel..example.com'];
        yield 'leading hyphen' => ['-panel.example.com'];
        yield 'numeric tld' => ['panel.example.123'];
    }
}
