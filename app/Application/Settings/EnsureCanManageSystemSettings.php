<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class EnsureCanManageSystemSettings
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        throw new AuthorizationException(
            'Only administrators may manage system settings.',
        );
    }
}
