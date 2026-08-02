<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ActivateServerAction
{
    public function handle(
        User $user,
        Server $server,
    ): Server {
        return DB::transaction(function () use (
            $user,
            $server,
        ): Server {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $selectedServer = $lockedUser
                ->servers()
                ->whereKey($server->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser
                ->servers()
                ->whereKeyNot($selectedServer)
                ->where(
                    'status',
                    ServerStatus::Active->value,
                )
                ->update([
                    'status' => ServerStatus::Inactive->value,
                ]);

            if (! $selectedServer->isActive()) {
                $selectedServer->status = ServerStatus::Active;
                $selectedServer->save();
            }

            return $selectedServer->refresh();
        });
    }
}
