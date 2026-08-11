<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use InvalidArgumentException;
use LogicException;
use Throwable;

final readonly class SendNotificationOnceAction
{
    public function execute(
        User $user,
        string $dedupeKey,
        Notification $notification,
    ): bool {
        $dedupeKey = trim(
            $dedupeKey,
        );

        if (
            $dedupeKey === ''
            || mb_strlen($dedupeKey) > 191
        ) {
            throw new InvalidArgumentException(
                'Notification dedupe key must contain between 1 and 191 characters.',
            );
        }

        $notificationType =
            $notification::class;

        /*
         * Reserve the dedupe key atomically before entering the
         * delivery transaction. insertOrIgnore makes concurrent
         * callers converge on one delivery record.
         */
        DB::table(
            'notification_deliveries',
        )->insertOrIgnore([
            'user_id' => $user->getKey(),
            'dedupe_key' => $dedupeKey,
            'notification_type' => $notificationType,
            'channel' => 'database',
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'delivered_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var NotificationDelivery $delivery */
        $delivery = NotificationDelivery::query()
            ->where(
                'dedupe_key',
                $dedupeKey,
            )
            ->firstOrFail();

        if (
            $delivery->user_id
            !== $user->getKey()
            || $delivery->notification_type
            !== $notificationType
        ) {
            throw new LogicException(
                'Notification dedupe key is already reserved for a different delivery.',
            );
        }

        try {
            return DB::transaction(
                function () use (
                    $delivery,
                    $user,
                    $notification,
                ): bool {
                    /** @var NotificationDelivery $locked */
                    $locked =
                        NotificationDelivery::query()
                            ->whereKey(
                                $delivery->getKey(),
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if ($locked->isDelivered()) {
                        return false;
                    }

                    $locked->forceFill([
                        'status' => NotificationDelivery::STATUS_PENDING,
                        'attempts' => $locked->attempts + 1,
                        'last_error' => null,
                    ])->saveOrFail();

                    /*
                     * Database notifications are local DB writes.
                     * Keeping this send inside the same transaction
                     * prevents a visible notification from being
                     * committed without its dedupe delivery state.
                     */
                    NotificationFacade::sendNow(
                        $user,
                        $notification,
                        [
                            'database',
                        ],
                    );

                    $locked->forceFill([
                        'status' => NotificationDelivery::STATUS_DELIVERED,
                        'last_error' => null,
                        'delivered_at' => now(),
                    ])->saveOrFail();

                    return true;
                },
            );
        } catch (Throwable $exception) {
            $this->recordFailure(
                deliveryId: (int) $delivery->getKey(),
                exception: $exception,
            );

            throw $exception;
        }
    }

    private function recordFailure(
        int $deliveryId,
        Throwable $exception,
    ): void {
        try {
            $message = trim(
                $exception->getMessage(),
            );

            if ($message === '') {
                $message =
                    $exception::class;
            }

            /** @var NotificationDelivery|null $delivery */
            $delivery =
                NotificationDelivery::query()
                    ->whereKey(
                        $deliveryId,
                    )
                    ->first();

            if (
                ! $delivery instanceof NotificationDelivery
                || $delivery->isDelivered()
            ) {
                return;
            }

            $delivery->forceFill([
                'status' => NotificationDelivery::STATUS_FAILED,
                'attempts' => $delivery->attempts + 1,
                'last_error' => mb_substr(
                    $message,
                    0,
                    4000,
                ),
            ])->saveOrFail();
        } catch (Throwable $recordingFailure) {
            report(
                $recordingFailure,
            );
        }
    }
}
