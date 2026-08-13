<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer\DTOs;

final readonly class AmneziaWgPeerRuntimeState
{
    public function __construct(
        public string $publicKey,
        public ?int $latestHandshakeAt,
        public int $receivedBytes,
        public int $sentBytes,
    ) {}
}
