<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Application\Integrations\Telegram\ConsumeTelegramLinkAction;
use App\Application\Integrations\Telegram\Jobs\SendTelegramBotMessage;
use App\Application\Integrations\Telegram\TelegramLinkStatus;
use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TelegramWebhookController
{
    public function __invoke(
        Request $request,
        TelegramBotClient $telegram,
        ConsumeTelegramLinkAction $consumeLink,
    ): JsonResponse {
        if (! $telegram->webhookAuthorized($request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => false], 403);
        }

        $payload = $request->json()->all();

        if (is_array($payload)) {
            $outcome = $consumeLink->execute($payload);

            if ($outcome->status === TelegramLinkStatus::Ignored) {
                $chatId = $this->bareStartChatId($payload);

                if ($chatId !== null) {
                    SendTelegramBotMessage::dispatch(
                        $chatId,
                        "👋 به Coreflare Bot خوش آمدید.\n\nاین Bot اعلان‌های مهم Coreflare را برای شما ارسال می‌کند.\n\nبرای اتصال حساب، از بخش یکپارچه‌سازی‌ها → Telegram در Coreflare شروع کنید.",
                        'باز کردن Coreflare',
                        route('panel.integrations.telegram.overview'),
                    );
                }
            } elseif ($outcome->chatId !== null) {
                [$text, $buttonText, $buttonUrl] = match ($outcome->status) {
                    TelegramLinkStatus::Connected, TelegramLinkStatus::Relinked => [
                        'به Coreflare bot خوش آمدید | فعالسازی با موفقیت انجام شد.',
                        'مشاهده Coreflare',
                        route('panel.integrations.telegram.overview'),
                    ],
                    TelegramLinkStatus::InvalidOrExpired => [
                        "⚠️ این لینک اتصال معتبر نیست یا منقضی شده است.\n\nاز بخش Telegram در Coreflare یک لینک جدید دریافت کنید.",
                        'دریافت لینک جدید',
                        route('panel.integrations.telegram.overview'),
                    ],
                    TelegramLinkStatus::Conflict => [
                        '⚠️ این حساب Telegram قبلاً به یک حساب Coreflare دیگر متصل شده است.',
                        null,
                        null,
                    ],
                    TelegramLinkStatus::Ignored => [null, null, null],
                };

                if ($text !== null) {
                    SendTelegramBotMessage::dispatch(
                        $outcome->chatId,
                        $text,
                        $buttonText,
                        $buttonUrl,
                    );
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function bareStartChatId(array $payload): ?string
    {
        $message = $payload['message'] ?? null;

        if (! is_array($message) || trim((string) ($message['text'] ?? '')) !== '/start') {
            return null;
        }

        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;

        if (! is_array($chat) || ! is_array($from) || ($chat['type'] ?? null) !== 'private') {
            return null;
        }

        $chatId = (string) ($chat['id'] ?? '');
        $fromId = (string) ($from['id'] ?? '');

        if (preg_match('/\A[1-9][0-9]{0,19}\z/D', $chatId) !== 1 || ! hash_equals($chatId, $fromId)) {
            return null;
        }

        return $chatId;
    }
}
