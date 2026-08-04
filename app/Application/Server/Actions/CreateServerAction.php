<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateServerAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $user,
        array $attributes,
        ?ServerStatus $explicitStatus = null,
    ): Server {
        return DB::transaction(
            function () use (
                $user,
                $attributes,
                $explicitStatus,
            ): Server {
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $user->getKey(),
                    );

                $hasRegisteredServer =
                    $lockedUser
                        ->servers()
                        ->exists();

                $server = new Server(
                    $attributes,
                );

                $server->status =
                    $explicitStatus
                    ?? (
                        $hasRegisteredServer
                            ? ServerStatus::Inactive
                            : ServerStatus::Active
                    );

                $lockedUser
                    ->servers()
                    ->save($server);

                return $server->refresh();
            },
        );
    }
}
