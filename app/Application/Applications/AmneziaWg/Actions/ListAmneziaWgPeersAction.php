<?php

declare(strict_types=1);

namespace App\Application\Applications\AmneziaWg\Actions;

use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerGateway;
use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerRuntimeState;
use App\Models\AmneziaWgPeer;
use App\Models\Server;

final readonly class ListAmneziaWgPeersAction
{
    public function __construct(
        private AmneziaWgPeerGateway $gateway,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     ip_address: string,
     *     public_key: string,
     *     runtime_configured: bool,
     *     latest_handshake_at: int|null,
     *     received_bytes: int,
     *     sent_bytes: int,
     *     created_at: string
     * }>
     */
    public function execute(Server $server): array
    {
        $runtime = [];

        foreach ($this->gateway->runtimeStates() as $state) {
            $runtime[$state->publicKey] = $state;
        }

        return AmneziaWgPeer::query()
            ->where('server_id', $server->getKey())
            ->whereNull('revoked_at')
            ->whereNotNull('public_key')
            ->orderBy('id')
            ->get()
            ->map(function (AmneziaWgPeer $peer) use ($runtime): array {
                $publicKey = (string) $peer->public_key;
                $state = $runtime[$publicKey] ?? null;

                return [
                    'id' => (int) $peer->getKey(),
                    'name' => (string) $peer->name,
                    'ip_address' => (string) $peer->ip_address,
                    'public_key' => $publicKey,
                    'runtime_configured' => $state instanceof AmneziaWgPeerRuntimeState,
                    'latest_handshake_at' => $state?->latestHandshakeAt,
                    'received_bytes' => $state?->receivedBytes ?? 0,
                    'sent_bytes' => $state?->sentBytes ?? 0,
                    'created_at' => $peer->created_at?->toIso8601String() ?? '',
                ];
            })
            ->all();
    }
}
