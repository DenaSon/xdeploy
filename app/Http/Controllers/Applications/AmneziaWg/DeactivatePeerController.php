<?php

declare(strict_types=1);

namespace App\Http\Controllers\Applications\AmneziaWg;

use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerLifecycleService;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\AmneziaWgPeer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class DeactivatePeerController
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private AmneziaWgPeerLifecycleService $lifecycle,
    ) {}

    public function __invoke(
        Request $request,
        Server $server,
        AmneziaWgPeer $peer,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless((int) $server->user_id === (int) $user->getKey(), 404);
        abort_unless((int) $peer->server_id === (int) $server->getKey(), 404);
        abort_if($peer->isRevoked(), 404);

        $this->ssh->connect($server);

        try {
            $this->lifecycle->deactivate(
                $server,
                (int) $peer->getKey(),
            );
        } finally {
            $this->ssh->disconnect();
        }

        return back()->with(
            'status',
            'دسترسی دستگاه AmneziaWG با موفقیت لغو شد.',
        );
    }
}
