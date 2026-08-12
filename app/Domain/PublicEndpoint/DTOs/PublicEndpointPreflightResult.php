<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\DTOs;

final readonly class PublicEndpointPreflightResult
{
    public function __construct(
        public PublicEndpointDnsPreflightResult $dns,
        public ?PublicEndpointServerPreflightResult $server = null,
    ) {}

    public function ready(): bool
    {
        return $this->dns->ready() && $this->server?->ready === true;
    }
}
