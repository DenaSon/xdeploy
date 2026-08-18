<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Telegram;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\Jobs\DeliverTelegramNotification;
use App\Models\TelegramConnection;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class TelegramChannel
{
    public function __construct(
        private TelegramBotClient $telegram,
    ) {
    }

    public function send(
        object $notifiable,
        Notification $notification,
    ): void {
        if (
            ! $notifiable instanceof User
            || ! $notification instanceof SendsTelegramNotification
            || ! $this->telegram->configured()
        ) {
            return;
        }

        try {
            $userId = (int) $notifiable->getKey();

            if (
                $userId < 1
                || ! TelegramConnection::query()
                    ->where('user_id', $userId)
                    ->exists()
            ) {
                return;
            }

            $message = $notification->toTelegram($notifiable);

            DeliverTelegramNotification::dispatch(
                $userId,
                $message->text,
            );
        } catch (Throwable $exception) {
            Log::warning(
                'telegram.notification.enqueue_failed',
                [
                    'user_id' => $notifiable->getKey(),
                    'notification' => $notification::class,
                    'exception_type' => $exception::class,
                ],
            );
        }
    }
}
