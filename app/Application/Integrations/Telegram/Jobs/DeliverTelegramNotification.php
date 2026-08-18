<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram\Jobs;

use App\Application\Notifications\NotificationPreferenceService;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Models\TelegramConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

final class DeliverTelegramNotification implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $userId,
        public readonly NotificationTopic $topic,
        #[SensitiveParameter]
        public readonly string $text,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(
        TelegramBotClient $telegram,
        NotificationPreferenceService $preferences,
    ): void {
        if (
            ! $telegram->configured()
            || ! $preferences->telegramEnabledForUserId(
                $this->userId,
                $this->topic,
            )
        ) {
            return;
        }

        $connection = TelegramConnection::query()
            ->where('user_id', $this->userId)
            ->first();

        if (! $connection instanceof TelegramConnection) {
            return;
        }

        $telegram->sendMessage(
            $connection->chat_id,
            $this->text,
        );
    }
}
