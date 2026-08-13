<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer;

interface AmneziaWgPeerStateGateway
{
    public function deactivate(string $publicKey): void;
}
