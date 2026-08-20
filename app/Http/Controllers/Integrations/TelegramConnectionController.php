<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Application\Integrations\Telegram\CreateTelegramLinkAction;
use App\Application\Integrations\Telegram\DisconnectTelegramAction;
use App\Application\Integrations\Telegram\Jobs\SendTelegramBotMessage;
use App\Infrastructure\Integrations\Telegram\TelegramBotException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TelegramConnectionController
{
    public function connect(
        Request $request,
        CreateTelegramLinkAction $createLink,
    ): RedirectResponse {
        try {
            $deepLink = $createLink->execute(
                $this->user($request),
            );
        } catch (TelegramBotException) {
            return to_route('panel.integrations.index')
                ->with(
                    'integration_error',
                    'اتصال Telegram هنوز در این محیط پیکربندی نشده است.',
                );
        }

        return redirect()->away($deepLink);
    }

    public function disconnect(
        Request $request,
        DisconnectTelegramAction $disconnect,
    ): RedirectResponse {
        $chatId = $disconnect->execute(
            $this->user($request),
        );

        if ($chatId !== null) {
            SendTelegramBotMessage::dispatch(
                $chatId,
                "🔕 Telegram از Coreflare جدا شد\n\nارسال اعلان‌های Coreflare به این حساب متوقف شد.",
            );
        }

        return to_route('panel.integrations.index')
            ->with(
                'integration_status',
                'اتصال Telegram با موفقیت قطع شد.',
            );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
