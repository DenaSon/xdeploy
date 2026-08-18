<?php

declare(strict_types=1);

namespace App\Notifications\Cloud;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class CloudServerExpiringSoonNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $serverId,
        public readonly string $serverName,
        public readonly string $expiresAt,
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
            'kind' => 'cloud_server_expiring_soon',
            'title' => 'پایان سرویس نزدیک است',
            'message' => sprintf(
                'کمتر از ۲۴ ساعت تا پایان سرویس VPS «%s» باقی مانده است.',
                $this->serverName,
            ),
            'icon' => 'lucide.clock-alert',
            'tone' => 'warning',
            'server_id' => $this->serverId,
            'server_name' => $this->serverName,
            'expires_at' => $this->expiresAt,
            'action_label' => 'تمدید سرویس',
            'action_url' => route(
                'panel.servers.renew',
                ['server' => $this->serverId],
                false,
            ),
        ];
    }
}
