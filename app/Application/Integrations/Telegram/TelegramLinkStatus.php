<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

enum TelegramLinkStatus: string
{
    case Connected = 'connected';
    case Relinked = 'relinked';
    case InvalidOrExpired = 'invalid_or_expired';
    case Conflict = 'conflict';
    case Ignored = 'ignored';
}
