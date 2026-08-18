<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramWebhookCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://coreflare.test',
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

    public function test_command_registers_only_the_message_webhook_without_printing_secrets(): void
    {
        Http::fake([
            'https://api.telegram.test/*' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        $this->artisan('telegram:webhook:set')
            ->expectsOutput('Telegram webhook configured successfully.')
            ->assertExitCode(0);

        Http::assertSent(
            static fn ($request): bool => $request['url']
                === 'https://coreflare.test/api/integrations/telegram/webhook'
                && $request['secret_token']
                    === 'ci_telegram_webhook_secret_123'
                && $request['allowed_updates'] === ['message'],
        );
    }

    public function test_command_fails_closed_when_public_app_url_is_not_https(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
        ]);

        Http::fake();

        $this->artisan('telegram:webhook:set')
            ->expectsOutput('Telegram webhook URL must use HTTPS.')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }
}
