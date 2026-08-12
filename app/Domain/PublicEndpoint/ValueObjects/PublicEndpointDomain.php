<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\ValueObjects;

use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;

final readonly class PublicEndpointDomain
{
    private const int MAX_LENGTH = 253;

    private const int MAX_LABEL_LENGTH = 63;

    private function __construct(
        public string $value,
    ) {}

    public static function from(string $input): self
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $input) === 1) {
            throw InvalidPublicEndpointDomainException::make();
        }

        $domain = strtolower(
            trim($input),
        );

        if (str_ends_with($domain, '.')) {
            $domain = substr(
                $domain,
                0,
                -1,
            );
        }

        if (! self::isValid($domain)) {
            throw InvalidPublicEndpointDomainException::make();
        }

        return new self(
            $domain,
        );
    }

    private static function isValid(
        string $domain,
    ): bool {
        if (
            $domain === ''
            || strlen($domain) > self::MAX_LENGTH
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

        $labels = explode(
            '.',
            $domain,
        );

        foreach ($labels as $label) {
            if (
                $label === ''
                || strlen($label) > self::MAX_LABEL_LENGTH
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
}
