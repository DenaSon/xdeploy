<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram\Contracts;

use App\Application\Integrations\Telegram\TelegramMessage;

interface SendsTelegramNotification
{
    public function toTelegram(object $notifiable): TelegramMessage;
}
