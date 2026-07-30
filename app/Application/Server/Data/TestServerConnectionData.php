<?php

namespace App\Application\Server\Data;

final readonly class TestServerConnectionData
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $credential,
    ) {}

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     username: string,
     *     credential: string,
     * } $data
     */
    public static function from(array $data): self
    {
        return new self(
            host: $data['host'],
            port: $data['port'],
            username: $data['username'],
            credential: $data['credential'],
        );
    }
}
