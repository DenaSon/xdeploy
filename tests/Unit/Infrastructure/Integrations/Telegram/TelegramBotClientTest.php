<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Telegram;

use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Infrastructure\Integrations\Telegram\TelegramBotException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramBotClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => '123456:ci-telegram-bot-token',
            'services.telegram.bot_username' => 'CoreflareTestBot',
            'services.telegram.webhook_secret' => 'ci_telegram_webhook_secret_123',
            'services.telegram.link_ttl_seconds' => 600,
            'services.telegram.api_base_url' => 'https://api.telegram.test',
            'services.telegram.connect_timeout' => 5,
            'services.telegram.timeout' => 10,
        ]);
    }

    public function test_set_webhook_sends_expected_secret_and_allowed_update_scope(): void
    {
        Http::fake([
            'https://api.telegram.test/*' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        app(TelegramBotClient::class)->setWebhook(
            'https://coreflare.test/api/integrations/telegram/webhook',
        );

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://api.telegram.test/bot123456:ci-telegram-bot-token/setWebhook'
                && $request['url']
                    === 'https://coreflare.test/api/integrations/telegram/webhook'
                && $request['secret_token']
                    === 'ci_telegram_webhook_secret_123'
                && $request['allowed_updates'] === ['message'],
        );
    }

    public function test_transport_failure_never_exposes_bot_token_or_webhook_secret(): void
    {
        Http::fake(
            static function (): never {
                throw new ConnectionException(
                    'failed https://api.telegram.test/bot123456:ci-telegram-bot-token/setWebhook?secret=ci_telegram_webhook_secret_123',
                );
            },
        );

        try {
            app(TelegramBotClient::class)->setWebhook(
                'https://coreflare.test/api/integrations/telegram/webhook',
            );
            self::fail('Expected TelegramBotException was not thrown.');
        } catch (TelegramBotException $exception) {
            self::assertSame(
                'Telegram API connection failed.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                'ci-telegram-bot-token',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                'ci_telegram_webhook_secret_123',
                $exception->getMessage(),
            );
        }
    }

    public function test_invalid_configuration_fails_closed(): void
    {
        config([
            'services.telegram.webhook_secret' => 'too short',
        ]);

        $client = app(TelegramBotClient::class);

        self::assertFalse($client->configured());
        self::assertFalse(
            $client->webhookAuthorized('too short'),
        );
    }
}
