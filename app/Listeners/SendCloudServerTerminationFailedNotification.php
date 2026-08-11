<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Cloud\Events\CloudServerTerminationFailed;
use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Models\User;
use App\Notifications\Cloud\CloudServerTerminationFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendCloudServerTerminationFailedNotification implements ShouldQueueAfterCommit
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
        CloudServerTerminationFailed $event,
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
            notification: new CloudServerTerminationFailedNotification(
                serverId: $event->serverId,
                serverName: $event->serverName,
                expiresAt: $event->expiresAt,
                attempts: $event->attempts,
            ),
        );
    }

    public function failed(
        CloudServerTerminationFailed $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.cloud_server_termination_failed_notification_failed',
            [
                'server_id' => $event->serverId,
                'user_id' => $event->userId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
