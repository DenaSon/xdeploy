<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\Server;
use App\Models\User;

final readonly class CreateServerAction
{
    public function handle(
        User $user,
        array $attributes,
    ): Server {
        return $user->servers()->create($attributes);
    }
}
