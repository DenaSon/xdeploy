<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisconnectTelegramAction
{
    public function execute(User $user): ?string
    {
        return DB::transaction(
            static function () use ($user): ?string {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $chatId = TelegramConnection::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->value('chat_id');

                TelegramConnection::query()
                    ->where('user_id', $user->getKey())
                    ->delete();

                TelegramLinkChallenge::query()
                    ->where('user_id', $user->getKey())
                    ->delete();

                return is_string($chatId) ? $chatId : null;
            },
            3,
        );
    }
}
