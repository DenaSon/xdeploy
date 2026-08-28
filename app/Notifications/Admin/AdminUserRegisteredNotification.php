<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class AdminUserRegisteredNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $userId,
        public readonly string $phone,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function telegramTopic(): NotificationTopic
    {
        return NotificationTopic::Account;
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::fromNotificationPayload([
            'kind' => 'admin_user_registered',
            'title' => 'ثبت‌نام جدید در Coreflare',
            'message' => sprintf(
                'کاربر جدید #%d با شماره %s ثبت‌نام کرد.',
                $this->userId,
                $this->maskedPhone(),
            ),
            'action_label' => 'مشاهده کاربر',
            'action_url' => route(
                'admin.users.show',
                ['user' => $this->userId],
                false,
            ),
        ]);
    }

    private function maskedPhone(): string
    {
        $phone = trim($this->phone);

        if (mb_strlen($phone) < 7) {
            return '—';
        }

        return mb_substr($phone, 0, 3)
            .'•••••'
            .mb_substr($phone, -3);
    }
}
