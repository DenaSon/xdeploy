<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\DTOs;

use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;

final readonly class PublicEndpointRuntimeInfo
{
    public function __construct(
        public PublicEndpointRuntimeState $state,
        public ?string $domain = null,
    ) {}

    /** @return array{state: string, domain: string|null} */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'domain' => $this->domain,
        ];
    }
}
