<?php

declare(strict_types=1);

namespace App\Application\Server\Data;

use App\Domain\Server\Enums\AuthenticationType;

final readonly class TestServerConnectionData
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $credential,
        public AuthenticationType $authenticationType = AuthenticationType::Password,
    ) {}

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     username: string,
     *     credential: string,
     *     authentication_type?: string,
     * } $data
     */
    public static function from(array $data): self
    {
        return new self(
            host: $data['host'],
            port: $data['port'],
            username: $data['username'],
            credential: $data['credential'],
            authenticationType: AuthenticationType::from(
                $data['authentication_type']
                    ?? AuthenticationType::Password->value,
            ),
        );
    }
}
