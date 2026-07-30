<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\Server;

final readonly class UpdateServerAction
{
    public function handle(
        Server $server,
        array $attributes,
    ): Server {
        $server->update($attributes);

        return $server->refresh();
    }
}
