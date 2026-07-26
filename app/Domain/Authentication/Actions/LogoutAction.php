<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Actions;

use App\Models\User;

final class LogoutAction
{
    public function handle(
        User $user,
    ): void {
        $user
            ->currentAccessToken()
            ?->delete();
    }
}
