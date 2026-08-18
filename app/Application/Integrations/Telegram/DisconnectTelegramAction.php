<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisconnectTelegramAction
{
    public function execute(User $user): void
    {
        DB::transaction(
            static function () use ($user): void {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                TelegramConnection::query()
                    ->where('user_id', $user->getKey())
                    ->delete();

                TelegramLinkChallenge::query()
                    ->where('user_id', $user->getKey())
                    ->whereNull('consumed_at')
                    ->delete();
            },
            3,
        );
    }
}
