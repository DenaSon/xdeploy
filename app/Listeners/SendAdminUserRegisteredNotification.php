<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\User\Events\UserRegistered;
use App\Models\User;
use App\Notifications\Admin\AdminUserRegisteredNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SendAdminUserRegisteredNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(UserRegistered $event): void
    {
        /** @var User|null $user */
        $user = User::query()->find($event->userId);

        if (
            ! $user instanceof User
            || $user->isAdmin()
        ) {
            return;
        }

        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new AdminUserRegisteredNotification(
                userId: (int) $user->getKey(),
                phone: (string) $user->phone,
            ),
        );
    }

    public function failed(
        UserRegistered $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.admin_user_registered_failed',
            [
                'user_id' => $event->userId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
