<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

final readonly class MarzbanHttpsPreflightResult
{
    public function __construct(
        public MarzbanHttpsDnsPreflightResult $dns,
        public ?MarzbanHttpsServerPreflightResult $server,
    ) {}

    public function ready(): bool
    {
        return $this->dns->ready()
            && $this->server?->ready() === true;
    }

    /**
     * @return array{
     *     dns: array<string, mixed>,
     *     server: array<string, mixed>|null,
     *     ready: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'dns' => $this->dns->toArray(),
            'server' => $this->server?->toArray(),
            'ready' => $this->ready(),
        ];
    }
}
