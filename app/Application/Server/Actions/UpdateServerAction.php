<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateServerAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $user,
        Server $server,
        array $attributes,
    ): Server {
        return DB::transaction(
            function () use (
                $user,
                $server,
                $attributes,
            ): Server {
                /*
                 * Re-resolve through the authenticated user.
                 *
                 * Even if UI authorization is accidentally removed
                 * later, this action cannot update another user's server.
                 */
                $ownedServer = $user
                    ->servers()
                    ->whereKey(
                        $server->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->guardAgainstDuplicateServer(
                    user: $user,
                    server: $ownedServer,
                    attributes: $attributes,
                );

                /*
                 * Empty credential means:
                 * keep the currently stored credential.
                 */
                if (
                    array_key_exists(
                        'credential',
                        $attributes,
                    )
                    && $attributes['credential'] === ''
                ) {
                    unset(
                        $attributes['credential'],
                    );
                }

                $ownedServer->update(
                    $attributes,
                );

                return $ownedServer->refresh();
            },
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guardAgainstDuplicateServer(
        User $user,
        Server $server,
        array $attributes,
    ): void {
        $host = $attributes['host']
            ?? $server->host;

        $port = $attributes['port']
            ?? $server->port;

        $exists = $user
            ->servers()
            ->whereKeyNot(
                $server->getKey(),
            )
            ->where(
                'host',
                $host,
            )
            ->where(
                'port',
                $port,
            )
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'host' => [
                'سروری با این آدرس و پورت قبلاً ثبت شده است.',
            ],
        ]);
    }
}
