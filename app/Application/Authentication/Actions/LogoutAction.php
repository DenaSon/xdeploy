<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use Illuminate\Support\Facades\Auth;

final readonly class LogoutAction
{
    public function handle(): void
    {
        Auth::logout();

        request()->session()->invalidate();

        request()->session()->regenerateToken();
    }
}
