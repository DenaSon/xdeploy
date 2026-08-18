<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Infrastructure\Integrations\Telegram\TelegramBotException;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateTelegramLinkAction
{
    public function __construct(
        private TelegramBotClient $telegram,
    ) {
    }

    public function execute(User $user): string
    {
        if (! $this->telegram->configured()) {
            throw new TelegramBotException(
                'Telegram integration is not configured.',
            );
        }

        $token = $this->token();
        $deepLink = $this->telegram->deepLink($token);
        $tokenHash = hash('sha256', $token);
        $expiresAt = now()->addSeconds(
            $this->telegram->linkTtlSeconds(),
        );

        DB::transaction(
            static function () use (
                $user,
                $tokenHash,
                $expiresAt,
            ): void {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                TelegramLinkChallenge::query()
                    ->where('user_id', $user->getKey())
                    ->delete();

                TelegramLinkChallenge::query()->create([
                    'user_id' => $user->getKey(),
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                    'consumed_at' => null,
                ]);
            },
            3,
        );

        return $deepLink;
    }

    private function token(): string
    {
        return rtrim(
            strtr(
                base64_encode(random_bytes(32)),
                '+/',
                '-_',
            ),
            '=',
        );
    }
}
