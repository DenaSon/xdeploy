<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\DTOs;

use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;

final readonly class PublicEndpointApplicationStatus
{
    public function __construct(
        public ApplicationInfo $application,
        public PublicEndpointRuntimeInfo $endpoint,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'application' => [
                'state' => $this->application->state->value,
                'version' => $this->application->version(),
                'is_installed' => $this->application->isInstalled(),
                'is_running' => $this->application->isRunning(),
                'is_not_installed' => $this->application->isNotInstalled(),
                'is_unknown' => $this->application->isUnknown(),
            ],
            'endpoint' => $this->endpoint->toArray(),
        ];
    }
}
