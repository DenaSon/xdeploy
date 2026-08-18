<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Application\Integrations\Telegram\ConsumeTelegramLinkAction;
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
        if (
            ! $telegram->webhookAuthorized(
                $request->header(
                    'X-Telegram-Bot-Api-Secret-Token',
                ),
            )
        ) {
            return response()->json(
                ['ok' => false],
                403,
            );
        }

        $payload = $request->json()->all();

        if (is_array($payload)) {
            $consumeLink->execute($payload);
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}
