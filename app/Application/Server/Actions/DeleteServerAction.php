<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteServerAction
{
    public function handle(
        User $user,
        int $serverId,
    ): void {
        DB::transaction(function () use (
            $user,
            $serverId,
        ): void {
            $ownedServer = $user
                ->servers()
                ->whereKey($serverId)
                ->lockForUpdate()
                ->firstOrFail();

            $ownedServer->delete();
        });
    }
}
