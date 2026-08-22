<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Application\Integrations\Telegram\Jobs\SendTelegramBotMessage;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TelegramActivationMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_activation_uses_requested_product_copy(): void
    {
        Queue::fake();

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

        $user = User::factory()->create();
        $token = str_repeat('A', 43);

        TelegramLinkChallenge::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson(
            route('integrations.telegram.webhook'),
            [
                'update_id' => 1000,
                'message' => [
                    'text' => "/start {$token}",
                    'chat' => [
                        'id' => 123456789,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 123456789,
                        'first_name' => 'Telegram User',
                    ],
                ],
            ],
            [
                'X-Telegram-Bot-Api-Secret-Token' => 'ci_telegram_webhook_secret_123',
            ],
        )->assertOk()->assertJson(['ok' => true]);

        Queue::assertPushed(
            SendTelegramBotMessage::class,
            static fn (SendTelegramBotMessage $job): bool => $job->chatId === '123456789'
                && $job->text === 'به Coreflare bot خوش آمدید | فعالسازی با موفقیت انجام شد.'
                && $job->buttonText === 'مشاهده Coreflare'
                && $job->buttonUrl === route('panel.integrations.telegram.overview'),
        );
    }
}
