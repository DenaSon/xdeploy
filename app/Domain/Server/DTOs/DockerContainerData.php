<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class DockerContainerData
{
    public function __construct(
        public string $name,
        public string $image,
        public string $state,
        public string $status,
        public string $ports,
    ) {}

    public function isRunning(): bool
    {
        return $this->state === 'running';
    }

    /**
     * @return array{
     *     name: string,
     *     image: string,
     *     state: string,
     *     status: string,
     *     ports: string,
     *     is_running: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'image' => $this->image,
            'state' => $this->state,
            'status' => $this->status,
            'ports' => $this->ports,
            'is_running' => $this->isRunning(),
        ];
    }
}
