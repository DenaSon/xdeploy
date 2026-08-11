<?php

declare(strict_types=1);

namespace App\Notifications\Cloud;

use Illuminate\Notifications\Notification;

final class CloudServerTerminationFailedNotification extends Notification
{
    public function __construct(
        public readonly int $serverId,
        public readonly string $serverName,
        public readonly string $expiresAt,
        public readonly int $attempts,
    ) {}

    /**
     * @return list<string>
     */
    public function via(
        object $notifiable,
    ): array {
        return [
            'database',
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'cloud_server_termination_failed',

            'title' => 'حذف نهایی VPS با تأخیر مواجه شد',

            'message' => sprintf(
                'سرویس VPS «%s» پایان یافته است، اما حذف نهایی زیرساخت با تأخیر مواجه شده است. نیازی به اقدام شما نیست.',
                $this->serverName,
            ),

            'icon' => 'lucide.triangle-alert',

            'tone' => 'warning',

            'server_id' => $this->serverId,

            'server_name' => $this->serverName,

            'expires_at' => $this->expiresAt,

            'attempts' => $this->attempts,

            'action_label' => 'مشاهده سرورها',

            'action_url' => route(
                'panel.servers.index',
                absolute: false,
            ),
        ];
    }
}
