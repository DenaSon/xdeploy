<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\Server;

final readonly class DeleteServerAction
{
    public function handle(Server $server): void
    {
        $server->delete();
    }
}
