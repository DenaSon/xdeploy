<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

final readonly class LoginAction
{
    public function handle(
        User $user,
        string $tokenName = 'xdeploy',
    ): NewAccessToken {
        return $user->createToken(
            name: $tokenName,
        );
    }
}
