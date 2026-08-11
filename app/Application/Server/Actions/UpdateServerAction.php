<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Arr;
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
                $ownedServer = $user
                    ->servers()
                    ->whereKey(
                        $server->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Host is part of the Server identity and is immutable
                 * after registration.
                 */
                $mutableAttributes = Arr::only(
                    $attributes,
                    [
                        'name',
                        'port',
                        'username',
                        'credential',
                    ],
                );

                $this->guardAgainstDuplicateServer(
                    user: $user,
                    server: $ownedServer,
                    attributes: $mutableAttributes,
                );

                if (
                    array_key_exists(
                        'credential',
                        $mutableAttributes,
                    )
                    && $mutableAttributes['credential'] === ''
                ) {
                    unset(
                        $mutableAttributes['credential'],
                    );
                }

                $ownedServer->update(
                    $mutableAttributes,
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
        $port = $attributes['port']
            ?? $server->port;

        $exists = $user
            ->servers()
            ->whereKeyNot(
                $server->getKey(),
            )
            ->where(
                'host',
                $server->host,
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
            'port' => [
                'سروری با این آدرس و پورت قبلاً ثبت شده است.',
            ],
        ]);
    }
}
