<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\ServerStatus;
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
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $ownedServer = $lockedUser
                ->servers()
                ->whereKey($serverId)
                ->lockForUpdate()
                ->firstOrFail();

            $wasActive = $ownedServer->isActive();

            $ownedServer->delete();

            if (! $wasActive) {
                return;
            }

            $replacementId = $lockedUser
                ->servers()
                ->latest('id')
                ->value('id');

            if ($replacementId === null) {
                return;
            }

            // Normalize any legacy duplicate Active servers.
            $lockedUser
                ->servers()
                ->where(
                    'status',
                    ServerStatus::Active->value,
                )
                ->update([
                    'status' => ServerStatus::Inactive->value,
                ]);

            $lockedUser
                ->servers()
                ->whereKey($replacementId)
                ->update([
                    'status' => ServerStatus::Active->value,
                ]);
        });
    }
}
