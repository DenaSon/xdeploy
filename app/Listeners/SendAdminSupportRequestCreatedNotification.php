<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Notifications\Actions\SendTelegramNotificationOnceAction;
use App\Application\Support\Events\SupportRequestCreated;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\Admin\AdminSupportRequestCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendAdminSupportRequestCreatedNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly SendTelegramNotificationOnceAction $sendOnce,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(SupportRequestCreated $event): void
    {
        /** @var SupportRequest|null $supportRequest */
        $supportRequest = SupportRequest::query()
            ->whereKey($event->supportRequestId)
            ->first();

        if (! $supportRequest instanceof SupportRequest) {
            return;
        }

        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $notification = new AdminSupportRequestCreatedNotification(
            supportRequestId: (int) $supportRequest->getKey(),
            userId: (int) $supportRequest->user_id,
            subject: (string) $supportRequest->subject,
            category: $supportRequest->category,
            serverId: $supportRequest->server_id !== null
                ? (int) $supportRequest->server_id
                : null,
        );

        foreach ($admins as $admin) {
            $this->sendOnce->execute(
                user: $admin,
                dedupeKey: sprintf(
                    'admin:support-request:%d:created:recipient:%d',
                    $supportRequest->getKey(),
                    $admin->getKey(),
                ),
                notification: $notification,
            );
        }
    }

    public function failed(
        SupportRequestCreated $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.admin_support_request_created_failed',
            [
                'support_request_id' => $event->supportRequestId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
