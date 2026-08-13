<?php

declare(strict_types=1);

namespace App\Application\Applications\AmneziaWg;

use App\Application\Applications\AmneziaWg\Actions\CreateAmneziaWgPeerAction;
use App\Application\Applications\AmneziaWg\Actions\ListAmneziaWgPeersAction;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\AmneziaWgPeer;
use App\Models\Server;
use App\Models\User;

final readonly class AmneziaWgManager
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private CreateAmneziaWgPeerAction $createPeer,
        private ListAmneziaWgPeersAction $listPeers,
    ) {}

    public function createPeer(
        User $user,
        Server $server,
        string $name,
    ): AmneziaWgPeer {
        return $this->onServer(
            $user,
            $server,
            fn (Server $ownedServer): AmneziaWgPeer => $this->createPeer->execute(
                $ownedServer,
                $name,
            ),
        );
    }

    /**
     * @return list<array<string, bool|int|string|null>>
     */
    public function peers(
        User $user,
        Server $server,
    ): array {
        return $this->onServer(
            $user,
            $server,
            fn (Server $ownedServer): array => $this->listPeers->execute(
                $ownedServer,
            ),
        );
    }

    /**
     * @template TResult
     *
     * @param  callable(Server): TResult  $operation
     * @return TResult
     */
    private function onServer(
        User $user,
        Server $server,
        callable $operation,
    ): mixed {
        $ownedServer = $user->servers()
            ->findOrFail($server->getKey());

        $this->ssh->connect($ownedServer);

        try {
            return $operation($ownedServer);
        } finally {
            $this->ssh->disconnect();
        }
    }
}
