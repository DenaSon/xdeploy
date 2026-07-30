<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetServersAction
{
    public function handle(User $user): Collection
    {
        return $user->servers()
            ->latest()
            ->get();
    }
}
