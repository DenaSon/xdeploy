<?php

declare(strict_types=1);

namespace App\Notifications\Cloud;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class CloudServerTerminatedNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $serverId,
        public readonly string $serverName,
        public readonly string $expiresAt,
        public readonly string $terminatedAt,
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
        return NotificationTopic::Servers;
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
        return TelegramMessage::fromNotificationPayload(
            $this->payload(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'cloud_server_terminated',
            'title' => 'سرویس VPS پایان یافت',
            'message' => sprintf(
                'مدت سرویس VPS «%s» به پایان رسید و سرور از زیرساخت ابری حذف شد.',
                $this->serverName,
            ),
            'icon' => 'lucide.server-off',
            'tone' => 'neutral',
            'server_id' => $this->serverId,
            'server_name' => $this->serverName,
            'expires_at' => $this->expiresAt,
            'terminated_at' => $this->terminatedAt,
            'action_label' => 'مشاهده سرورها',
            'action_url' => route(
                'panel.servers.index',
                absolute: false,
            ),
        ];
    }
}
