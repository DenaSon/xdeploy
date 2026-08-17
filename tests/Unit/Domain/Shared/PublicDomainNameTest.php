<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidPublicDomainNameException;
use App\Domain\Shared\ValueObjects\PublicDomainName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicDomainNameTest extends TestCase
{
    public function test_it_normalizes_a_public_domain_name(): void
    {
        self::assertSame(
            'example.com',
            PublicDomainName::from(' Example.COM. ')->value,
        );
    }

    #[DataProvider('invalidDomains')]
    public function test_it_rejects_invalid_public_domain_names(
        string $domain,
    ): void {
        $this->expectException(
            InvalidPublicDomainNameException::class,
        );

        PublicDomainName::from($domain);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDomains(): iterable
    {
        yield 'empty' => [''];
        yield 'url' => ['https://example.com'];
        yield 'path' => ['example.com/path'];
        yield 'port' => ['example.com:443'];
        yield 'wildcard' => ['*.example.com'];
        yield 'single label' => ['localhost'];
        yield 'ipv4' => ['192.0.2.10'];
        yield 'leading hyphen' => ['-example.com'];
        yield 'numeric tld' => ['example.123'];
        yield 'unicode literal' => ['مثال.com'];
    }
}
