<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites;

use App\Domain\Platform\Caddy\Sites\Exceptions\InvalidCaddySiteException;

final readonly class CaddySite
{
    private const int MAX_DOMAIN_LENGTH = 253;

    private const int MAX_DOMAIN_LABEL_LENGTH = 63;

    private function __construct(
        public CaddySiteKey $key,
        public string $domain,
        public string $upstream,
    ) {}

    public static function reverseProxy(
        CaddySiteKey $key,
        string $domain,
        string $upstream,
    ): self {
        return new self(
            key: $key,
            domain: self::normalizeDomain($domain),
            upstream: self::normalizeUpstream($upstream),
        );
    }

    private static function normalizeDomain(string $input): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $input) === 1) {
            throw InvalidCaddySiteException::domain();
        }

        $domain = strtolower(trim($input));

        if (str_ends_with($domain, '.')) {
            $domain = substr($domain, 0, -1);
        }

        if (! self::validDomain($domain)) {
            throw InvalidCaddySiteException::domain();
        }

        return $domain;
    }

    private static function validDomain(string $domain): bool
    {
        if (
            $domain === ''
            || strlen($domain) > self::MAX_DOMAIN_LENGTH
            || ! str_contains($domain, '.')
            || str_contains($domain, '://')
            || str_contains($domain, '/')
            || str_contains($domain, '?')
            || str_contains($domain, '#')
            || str_contains($domain, ':')
            || str_contains($domain, '*')
            || preg_match('/\s/', $domain) === 1
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return false;
        }

        $labels = explode('.', $domain);

        foreach ($labels as $label) {
            if (
                $label === ''
                || strlen($label) > self::MAX_DOMAIN_LABEL_LENGTH
                || preg_match(
                    '/^(?!-)[a-z0-9-]+(?<!-)$/',
                    $label,
                ) !== 1
            ) {
                return false;
            }
        }

        return preg_match(
            '/[a-z]/',
            $labels[array_key_last($labels)],
        ) === 1;
    }

    private static function normalizeUpstream(string $input): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $input) === 1) {
            throw InvalidCaddySiteException::upstream();
        }

        $upstream = trim($input);

        if (self::validUnixSocketUpstream($upstream)) {
            return $upstream;
        }

        if (self::validLoopbackTcpUpstream($upstream)) {
            return $upstream;
        }

        throw InvalidCaddySiteException::upstream();
    }

    private static function validUnixSocketUpstream(
        string $upstream,
    ): bool {
        if (
            preg_match(
                '/^unix\/\/[A-Za-z0-9._\/-]+$/',
                $upstream,
            ) !== 1
        ) {
            return false;
        }

        $path = substr($upstream, strlen('unix//'));

        if (
            $path === ''
            || str_contains($path, '//')
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                return false;
            }
        }

        return true;
    }

    private static function validLoopbackTcpUpstream(
        string $upstream,
    ): bool {
        if (
            preg_match(
                '/^(127\.0\.0\.1|localhost|\[::1\]):([0-9]{1,5})$/',
                $upstream,
                $matches,
            ) !== 1
        ) {
            return false;
        }

        $port = (int) $matches[2];

        return $port >= 1 && $port <= 65535;
    }
}
