<?php

declare(strict_types=1);

namespace App\Application\Server\Enums;

enum ServerConnectionTestStatus: string
{
    case Ready = 'ready';

    case ConnectionFailed = 'connection_failed';

    case PasswordChangeRequired = 'password_change_required';

    case CommandUnavailable = 'command_unavailable';

    case UnsupportedOperatingSystem = 'unsupported_operating_system';
}
