<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer;

use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerProvisioningResult;
use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerRuntimeState;

interface AmneziaWgPeerGateway
{
    public function createPeer(string $ipAddress, string $endpointHost): AmneziaWgPeerProvisioningResult;

    public function removePeer(string $publicKey): void;

    public function runtimeStates(): array;
}
