<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer;

use App\Models\AmneziaWgPeer;
use App\Models\Server;

final readonly class AmneziaWgPeerLifecycleService
{
    public function __construct(
        private AmneziaWgPeerGateway $gateway,
    ) {}

    public function deactivate(Server $server, int $peerId): void
    {
        $peer = AmneziaWgPeer::query()
            ->where('server_id', $server->getKey())
            ->whereNull('revoked_at')
            ->findOrFail($peerId);

        $publicKey = is_string($peer->public_key)
            ? $peer->public_key
            : '';

        if ($publicKey !== '') {
            $this->gateway->removePeer($publicKey);
        }

        $peer->forceFill([
            'client_config' => null,
            'revoked_at' => now(),
        ])->saveOrFail();
    }
}
