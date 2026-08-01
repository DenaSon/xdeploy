<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\User;

final readonly class DeleteServerAction
{
    public function handle(
        User $user,
        int $serverId,
    ): void {
        $server = $user->servers()
            ->whereKey($serverId)
            ->firstOrFail();

        $server->delete();
    }
}
