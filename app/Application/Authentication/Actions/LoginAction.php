<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final readonly class LoginAction
{
    public function handle(
        User $user,
    ): void {
        Auth::login($user);
    }
}
