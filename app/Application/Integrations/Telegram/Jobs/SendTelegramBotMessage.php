<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram\Jobs;

use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

final class SendTelegramBotMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(
        #[SensitiveParameter]
        public readonly string $chatId,
        #[SensitiveParameter]
        public readonly string $text,
        public readonly ?string $buttonText = null,
        public readonly ?string $buttonUrl = null,
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

    public function handle(TelegramBotClient $telegram): void
    {
        if (! $telegram->configured()) {
            return;
        }

        $telegram->sendMessage(
            $this->chatId,
            $this->text,
            $this->buttonText,
            $this->buttonUrl,
        );
    }
}
