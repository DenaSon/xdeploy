<?php

declare(strict_types=1);

namespace App\Http\Controllers\Applications\AmneziaWg;

use App\Models\AmneziaWgPeer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadPeerConfigController
{
    public function __invoke(
        Request $request,
        Server $server,
        AmneziaWgPeer $peer,
    ): Response {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless((int) $server->user_id === (int) $user->getKey(), 404);
        abort_unless((int) $peer->server_id === (int) $server->getKey(), 404);
        abort_if($peer->isRevoked(), 404);

        $config = $peer->client_config;

        abort_unless(
            is_string($config) && trim($config) !== '',
            404,
        );

        return response(
            content: $config,
            status: 200,
            headers: [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => sprintf(
                    'attachment; filename="amneziawg-device-%d.conf"',
                    (int) $peer->getKey(),
                ),
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
