<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram\Contracts;

use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;

interface SendsTelegramNotification
{
    public function telegramTopic(): NotificationTopic;

    public function toTelegram(object $notifiable): TelegramMessage;
}
