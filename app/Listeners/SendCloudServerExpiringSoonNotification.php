<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Models\User;
use App\Notifications\Cloud\CloudServerExpiringSoonNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendCloudServerExpiringSoonNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly SendNotificationOnceAction $sendOnce,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            60,
            300,
        ];
    }

    public function viaQueue(): string
    {
        return 'provisioning';
    }

    public function handle(
        CloudServerExpiringSoon $event,
    ): void {
        $user = User::query()
            ->find(
                $event->userId,
            );

        if (! $user instanceof User) {
            return;
        }

        $this->sendOnce->execute(
            user: $user,
            dedupeKey: $event->dedupeKey(),
            notification: new CloudServerExpiringSoonNotification(
                serverId: $event->serverId,
                serverName: $event->serverName,
                expiresAt: $event->expiresAt,
            ),
        );
    }

    public function failed(
        CloudServerExpiringSoon $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.cloud_server_expiring_soon_failed',
            [
                'server_id' => $event->serverId,
                'user_id' => $event->userId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
