<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Infrastructure\Integrations\Telegram\TelegramBotException;
use Illuminate\Console\Command;

final class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook:set';

    protected $description = 'Register the Coreflare Telegram webhook';

    public function handle(TelegramBotClient $telegram): int
    {
        if (! $telegram->configured()) {
            $this->error(
                'Telegram integration is not configured.',
            );

            return self::FAILURE;
        }

        $webhookUrl = route('integrations.telegram.webhook');

        if (! str_starts_with($webhookUrl, 'https://')) {
            $this->error(
                'Telegram webhook URL must use HTTPS.',
            );

            return self::FAILURE;
        }

        try {
            $telegram->setWebhook($webhookUrl);
        } catch (TelegramBotException) {
            $this->error(
                'Telegram webhook registration failed.',
            );

            return self::FAILURE;
        }

        $this->info(
            'Telegram webhook configured successfully.',
        );

        return self::SUCCESS;
    }
}
