<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Integrations\Telegram;

use App\Application\Integrations\Telegram\TelegramMessage;
use Tests\TestCase;

final class TelegramMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://coreflare.test',
        ]);
    }

    public function test_message_uses_only_public_presentation_fields(): void
    {
        $message = TelegramMessage::fromNotificationPayload([
            'title' => 'عنوان اعلان',
            'message' => 'متن اعلان',
            'action_label' => 'مشاهده',
            'action_url' => '/panel/notifications/1',
            'server_id' => 42,
            'internal_secret' => 'must-never-be-delivered',
        ]);

        self::assertSame(
            "عنوان اعلان\n\nمتن اعلان\n\nمشاهده: https://coreflare.test/panel/notifications/1",
            $message->text,
        );
        self::assertStringNotContainsString(
            'must-never-be-delivered',
            $message->text,
        );
        self::assertStringNotContainsString(
            '42',
            $message->text,
        );
    }

    public function test_message_keeps_action_link_while_clamping_to_telegram_limit(): void
    {
        $message = TelegramMessage::fromNotificationPayload([
            'title' => 'اعلان',
            'message' => str_repeat('الف', 5000),
            'action_label' => 'مشاهده',
            'action_url' => '/panel',
        ]);

        self::assertLessThanOrEqual(
            4096,
            mb_strlen($message->text),
        );
        self::assertStringEndsWith(
            'مشاهده: https://coreflare.test/panel',
            $message->text,
        );
        self::assertStringContainsString('…', $message->text);
    }

    public function test_external_action_urls_are_not_forwarded(): void
    {
        $message = TelegramMessage::fromNotificationPayload([
            'title' => 'اعلان',
            'message' => 'متن',
            'action_label' => 'مشاهده',
            'action_url' => 'https://evil.example/path',
        ]);

        self::assertSame(
            "اعلان\n\nمتن",
            $message->text,
        );
    }
}
