<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

final readonly class CloudflareOAuthTokenSet
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public array $scopes,
        public ?int $expiresIn,
    ) {}
}
