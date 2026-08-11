<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Cloud\Events\CloudServerTerminated;
use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Models\User;
use App\Notifications\Cloud\CloudServerTerminatedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendCloudServerTerminatedNotification implements ShouldQueueAfterCommit
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
        CloudServerTerminated $event,
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
            notification: new CloudServerTerminatedNotification(
                serverId: $event->serverId,
                serverName: $event->serverName,
                expiresAt: $event->expiresAt,
                terminatedAt: $event->terminatedAt,
            ),
        );
    }

    public function failed(
        CloudServerTerminated $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.cloud_server_terminated_failed',
            [
                'server_id' => $event->serverId,
                'user_id' => $event->userId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
