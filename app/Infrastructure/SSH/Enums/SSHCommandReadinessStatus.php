<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Enums;

enum SSHCommandReadinessStatus: string
{
    case Ready = 'ready';

    case PasswordChangeRequired = 'password_change_required';

    case CommandUnavailable = 'command_unavailable';

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    public function requiresPasswordChange(): bool
    {
        return $this === self::PasswordChangeRequired;
    }
}
