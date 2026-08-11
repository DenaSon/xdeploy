<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Infrastructure\Sms\Services\SmsService;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

final readonly class SendCloudServerExpiringSoonSmsAction
{
    private const int MAX_ATTEMPTS = 3;

    private const string CHANNEL = 'sms';

    private const string NOTIFICATION_TYPE = CloudServerExpiringSoon::class;

    public function __construct(
        private SmsService $smsService,
    ) {}

    public function execute(
        User $user,
        string $dedupeKey,
    ): bool {
        $dedupeKey = trim(
            $dedupeKey,
        );

        if (
            $dedupeKey === ''
            || mb_strlen($dedupeKey) > 191
        ) {
            throw new InvalidArgumentException(
                'SMS dedupe key must contain between 1 and 191 characters.',
            );
        }

        DB::table(
            'notification_deliveries',
        )->insertOrIgnore([
            'user_id' => $user->getKey(),
            'dedupe_key' => $dedupeKey,
            'notification_type' => self::NOTIFICATION_TYPE,
            'channel' => self::CHANNEL,
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

        $this->assertDeliveryOwnership(
            delivery: $delivery,
            user: $user,
        );

        if (! $this->claimDelivery($delivery)) {
            return false;
        }

        try {
            $phone = PhoneNumber::from(
                (string) $user->phone,
            );

            $this->smsService
                ->sendCloudServerExpirationWarning(
                    $phone,
                );
        } catch (InvalidArgumentException $exception) {
            $sendingException =
                SmsSendingException::permanent(
                    message: 'User phone number is invalid.',
                    previous: $exception,
                );

            $this->recordFailure(
                deliveryId: (int) $delivery->getKey(),
                exception: $sendingException,
            );

            throw $sendingException;
        } catch (Throwable $exception) {
            $this->recordFailure(
                deliveryId: (int) $delivery->getKey(),
                exception: $exception,
            );

            throw $exception;
        }

        $this->markDelivered(
            (int) $delivery->getKey(),
        );

        return true;
    }

    private function assertDeliveryOwnership(
        NotificationDelivery $delivery,
        User $user,
    ): void {
        if (
            $delivery->user_id !== $user->getKey()
            || $delivery->notification_type !== self::NOTIFICATION_TYPE
            || $delivery->channel !== self::CHANNEL
        ) {
            throw new LogicException(
                'SMS dedupe key is already reserved for a different delivery.',
            );
        }
    }

    private function claimDelivery(
        NotificationDelivery $delivery,
    ): bool {
        return DB::transaction(
            static function () use ($delivery): bool {
                /** @var NotificationDelivery $locked */
                $locked = NotificationDelivery::query()
                    ->whereKey(
                        $delivery->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->isDelivered()
                    || $locked->status === NotificationDelivery::STATUS_SENDING
                    || $locked->status === NotificationDelivery::STATUS_FAILED_PERMANENT
                    || $locked->attempts >= self::MAX_ATTEMPTS
                ) {
                    return false;
                }

                $locked->forceFill([
                    'status' => NotificationDelivery::STATUS_SENDING,
                    'attempts' => $locked->attempts + 1,
                    'last_error' => null,
                ])->saveOrFail();

                return true;
            },
        );
    }

    private function markDelivered(
        int $deliveryId,
    ): void {
        DB::transaction(
            static function () use ($deliveryId): void {
                /** @var NotificationDelivery $delivery */
                $delivery = NotificationDelivery::query()
                    ->whereKey(
                        $deliveryId,
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($delivery->isDelivered()) {
                    return;
                }

                $delivery->forceFill([
                    'status' => NotificationDelivery::STATUS_DELIVERED,
                    'last_error' => null,
                    'delivered_at' => now(),
                ])->saveOrFail();
            },
        );
    }

    private function recordFailure(
        int $deliveryId,
        Throwable $exception,
    ): void {
        try {
            /** @var NotificationDelivery|null $delivery */
            $delivery = NotificationDelivery::query()
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

            $permanent = $exception instanceof SmsSendingException
                && ! $exception->isRetryable();

            $delivery->forceFill([
                'status' => $permanent
                    ? NotificationDelivery::STATUS_FAILED_PERMANENT
                    : NotificationDelivery::STATUS_FAILED,
                'last_error' => mb_substr(
                    $this->failureMessage($exception),
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

    private function failureMessage(
        Throwable $exception,
    ): string {
        if ($exception instanceof SmsSendingException) {
            return $exception->getMessage();
        }

        return $exception::class;
    }
}
