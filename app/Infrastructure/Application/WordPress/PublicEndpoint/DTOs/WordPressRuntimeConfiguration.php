<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\WordPress\PublicEndpoint\DTOs;

use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;

final readonly class WordPressRuntimeConfiguration
{
    public function __construct(
        public ?string $publicUrl,
    ) {}

    public function domain(): ?string
    {
        if ($this->publicUrl === null) {
            return null;
        }

        $scheme = parse_url($this->publicUrl, PHP_URL_SCHEME);
        $host = parse_url($this->publicUrl, PHP_URL_HOST);
        $path = parse_url($this->publicUrl, PHP_URL_PATH);
        $port = parse_url($this->publicUrl, PHP_URL_PORT);
        $query = parse_url($this->publicUrl, PHP_URL_QUERY);
        $fragment = parse_url($this->publicUrl, PHP_URL_FRAGMENT);

        if (
            strtolower((string) $scheme) !== 'https'
            || ! is_string($host)
            || $host === ''
            || ($path !== null && $path !== '' && $path !== '/')
            || $port !== null
            || $query !== null
            || $fragment !== null
        ) {
            return null;
        }

        try {
            return PublicEndpointDomain::from($host)->value;
        } catch (InvalidPublicEndpointDomainException) {
            return null;
        }
    }

    public function hasPublicConfiguration(): bool
    {
        return $this->publicUrl !== null;
    }

    public function matches(string $domain): bool
    {
        return $this->publicUrl === "https://{$domain}"
            || $this->publicUrl === "https://{$domain}/";
    }
}
