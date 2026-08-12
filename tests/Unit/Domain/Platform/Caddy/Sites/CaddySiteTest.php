<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Caddy\Sites;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Exceptions\InvalidCaddySiteException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CaddySiteTest extends TestCase
{
    public function test_it_normalizes_a_reverse_proxy_site(): void
    {
        $site = CaddySite::reverseProxy(
            key: CaddySiteKey::from('Marzban'),
            domain: ' Panel.Example.COM. ',
            upstream: ' unix//var/lib/marzban/marzban.socket ',
        );

        $this->assertSame(
            'marzban',
            $site->key->value,
        );

        $this->assertSame(
            'marzban.caddy',
            $site->key->filename(),
        );

        $this->assertSame(
            'panel.example.com',
            $site->domain,
        );

        $this->assertSame(
            'unix//var/lib/marzban/marzban.socket',
            $site->upstream,
        );
    }

    #[DataProvider('validLoopbackUpstreams')]
    public function test_it_accepts_local_tcp_upstreams(
        string $upstream,
    ): void {
        $site = CaddySite::reverseProxy(
            key: CaddySiteKey::from('n8n'),
            domain: 'automation.example.com',
            upstream: $upstream,
        );

        $this->assertSame(
            $upstream,
            $site->upstream,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validLoopbackUpstreams(): iterable
    {
        yield 'ipv4 loopback' => [
            '127.0.0.1:5678',
        ];

        yield 'localhost' => [
            'localhost:5678',
        ];

        yield 'ipv6 loopback' => [
            '[::1]:5678',
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_it_rejects_invalid_site_keys(
        string $key,
    ): void {
        $this->expectException(
            InvalidCaddySiteException::class,
        );

        CaddySiteKey::from($key);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'path traversal' => [
            '../marzban',
        ];

        yield 'slash' => [
            'app/site',
        ];

        yield 'underscore' => [
            'my_app',
        ];

        yield 'empty' => [
            '',
        ];
    }

    #[DataProvider('invalidDomains')]
    public function test_it_rejects_invalid_domains(
        string $domain,
    ): void {
        $this->expectException(
            InvalidCaddySiteException::class,
        );

        CaddySite::reverseProxy(
            key: CaddySiteKey::from('app'),
            domain: $domain,
            upstream: '127.0.0.1:8080',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'scheme' => [
            'https://example.com',
        ];

        yield 'path' => [
            'example.com/admin',
        ];

        yield 'port' => [
            'example.com:443',
        ];

        yield 'wildcard' => [
            '*.example.com',
        ];

        yield 'ip' => [
            '203.0.113.10',
        ];
    }

    #[DataProvider('invalidUpstreams')]
    public function test_it_rejects_non_local_or_unsafe_upstreams(
        string $upstream,
    ): void {
        $this->expectException(
            InvalidCaddySiteException::class,
        );

        CaddySite::reverseProxy(
            key: CaddySiteKey::from('app'),
            domain: 'app.example.com',
            upstream: $upstream,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUpstreams(): iterable
    {
        yield 'remote host' => [
            '10.0.0.8:8080',
        ];

        yield 'scheme' => [
            'http://127.0.0.1:8080',
        ];

        yield 'invalid port' => [
            '127.0.0.1:70000',
        ];

        yield 'unix traversal' => [
            'unix//var/lib/../secret.sock',
        ];

        yield 'newline injection' => [
            "127.0.0.1:8080\nrespond hacked",
        ];
    }
}
