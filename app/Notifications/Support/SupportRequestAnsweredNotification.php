<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class SupportRequestAnsweredNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $supportRequestId,
        public readonly string $subject,
    ) {}

    /**
     * @return list<string>
     */
    public function via(
        object $notifiable,
    ): array {
        return [
            'database',
            TelegramChannel::class,
        ];
    }

    public function telegramTopic(): NotificationTopic
    {
        return NotificationTopic::Support;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(
        object $notifiable,
    ): array {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        object $notifiable,
    ): array {
        return $this->payload();
    }

    public function toTelegram(
        object $notifiable,
    ): TelegramMessage {
        $payload = $this->payload();
        $payload['action_label'] = 'مشاهده درخواست‌های پشتیبانی';
        $payload['action_url'] = route(
            'panel.support.index',
            absolute: false,
        );

        return TelegramMessage::fromNotificationPayload(
            $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'support_request_answered',
            'title' => 'پاسخ جدید پشتیبانی',
            'message' => sprintf(
                'برای درخواست «%s» پاسخ جدیدی ثبت شد.',
                $this->subject,
            ),
            'icon' => 'lucide.message-circle-reply',
            'tone' => 'info',
            'support_request_id' => $this->supportRequestId,
            'action_label' => 'مشاهده درخواست',
            'action_url' => route(
                'panel.support.show',
                ['supportRequestId' => $this->supportRequestId],
                absolute: false,
            ),
        ];
    }
}
