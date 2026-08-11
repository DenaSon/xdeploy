<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Application\Notifications\Actions\SendCloudServerExpiringSoonSmsAction;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendCloudServerExpiringSoonSms implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $uniqueFor = 1200;

    public function __construct(
        private readonly SendCloudServerExpiringSoonSmsAction $sendSms,
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

    public function uniqueId(
        CloudServerExpiringSoon $event,
    ): string {
        return $this->smsDedupeKey(
            $event,
        );
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

        try {
            $this->sendSms->execute(
                user: $user,
                dedupeKey: $this->smsDedupeKey(
                    $event,
                ),
            );
        } catch (SmsSendingException $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }

            logger()->warning(
                'notification.cloud_server_expiring_soon_sms_permanent_failure',
                [
                    'server_id' => $event->serverId,
                    'user_id' => $event->userId,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    public function failed(
        CloudServerExpiringSoon $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.cloud_server_expiring_soon_sms_failed',
            [
                'server_id' => $event->serverId,
                'user_id' => $event->userId,
                'message' => $exception instanceof SmsSendingException
                    ? $exception->getMessage()
                    : $exception::class,
            ],
        );
    }

    private function smsDedupeKey(
        CloudServerExpiringSoon $event,
    ): string {
        return sprintf(
            '%s:sms',
            $event->dedupeKey(),
        );
    }
}
