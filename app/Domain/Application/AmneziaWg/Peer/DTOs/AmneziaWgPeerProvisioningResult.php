<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer\DTOs;

final readonly class AmneziaWgPeerProvisioningResult
{
    public function __construct(
        public string $publicKey,
        public string $clientConfig,
    ) {}
}
