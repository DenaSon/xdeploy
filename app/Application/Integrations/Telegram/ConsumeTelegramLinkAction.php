<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConsumeTelegramLinkAction
{
    /**
     * @param  array<string, mixed>  $update
     */
    public function execute(array $update): TelegramLinkOutcome
    {
        $link = $this->linkFromUpdate($update);

        if ($link === null) {
            return new TelegramLinkOutcome(TelegramLinkStatus::Ignored);
        }

        $tokenHash = hash('sha256', $link['token']);
        $challengeOwnerId = $this->positiveInteger(
            TelegramLinkChallenge::query()
                ->where('token_hash', $tokenHash)
                ->value('user_id'),
        );

        if ($challengeOwnerId === null) {
            return new TelegramLinkOutcome(
                TelegramLinkStatus::InvalidOrExpired,
                $link['chat_id'],
            );
        }

        return DB::transaction(
            function () use (
                $link,
                $tokenHash,
                $challengeOwnerId,
            ): TelegramLinkOutcome {
                User::query()
                    ->whereKey($challengeOwnerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $challenge = TelegramLinkChallenge::query()
                    ->where('token_hash', $tokenHash)
                    ->where('user_id', $challengeOwnerId)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $challenge instanceof TelegramLinkChallenge
                    || $challenge->consumed_at !== null
                    || $challenge->expires_at->lessThanOrEqualTo(now())
                ) {
                    return new TelegramLinkOutcome(
                        TelegramLinkStatus::InvalidOrExpired,
                        $link['chat_id'],
                    );
                }

                $conflictingConnection = TelegramConnection::query()
                    ->where(function ($query) use ($link): void {
                        $query
                            ->where('chat_id', $link['chat_id'])
                            ->orWhere(
                                'telegram_user_id',
                                $link['telegram_user_id'],
                            );
                    })
                    ->where('user_id', '!=', $challengeOwnerId)
                    ->lockForUpdate()
                    ->first();

                if ($conflictingConnection instanceof TelegramConnection) {
                    $challenge->forceFill([
                        'consumed_at' => now(),
                    ])->save();

                    return new TelegramLinkOutcome(
                        TelegramLinkStatus::Conflict,
                        $link['chat_id'],
                    );
                }

                $connection = TelegramConnection::query()
                    ->where('user_id', $challengeOwnerId)
                    ->lockForUpdate()
                    ->first();

                $status = TelegramLinkStatus::Connected;

                if (! $connection instanceof TelegramConnection) {
                    TelegramConnection::query()->create([
                        'user_id' => $challengeOwnerId,
                        'chat_id' => $link['chat_id'],
                        'telegram_user_id' => $link['telegram_user_id'],
                        'username' => $link['username'],
                        'first_name' => $link['first_name'],
                        'connected_at' => now(),
                    ]);
                } else {
                    $sameIdentity = $connection->chat_id === $link['chat_id']
                        && $connection->telegram_user_id
                            === $link['telegram_user_id'];

                    $connection->forceFill([
                        'chat_id' => $link['chat_id'],
                        'telegram_user_id' => $link['telegram_user_id'],
                        'username' => $link['username'],
                        'first_name' => $link['first_name'],
                        'connected_at' => $sameIdentity
                            ? $connection->connected_at
                            : now(),
                    ])->save();

                    if (! $sameIdentity) {
                        $status = TelegramLinkStatus::Relinked;
                    }
                }

                $challenge->forceFill([
                    'consumed_at' => now(),
                ])->save();

                TelegramLinkChallenge::query()
                    ->where('user_id', $challengeOwnerId)
                    ->whereKeyNot($challenge->getKey())
                    ->delete();

                return new TelegramLinkOutcome(
                    $status,
                    $link['chat_id'],
                );
            },
            3,
        );
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array{
     *     token: string,
     *     chat_id: string,
     *     telegram_user_id: string,
     *     username: ?string,
     *     first_name: ?string
     * }|null
     */
    private function linkFromUpdate(array $update): ?array
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $text = $message['text'] ?? null;
        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;

        if (
            ! is_string($text)
            || ! is_array($chat)
            || ! is_array($from)
            || ($chat['type'] ?? null) !== 'private'
        ) {
            return null;
        }

        if (
            preg_match(
                '/\A\/start\s+([A-Za-z0-9_-]{43})\s*\z/D',
                $text,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $chatId = $this->telegramId($chat['id'] ?? null);
        $telegramUserId = $this->telegramId($from['id'] ?? null);

        if (
            $chatId === null
            || $telegramUserId === null
            || ! hash_equals($chatId, $telegramUserId)
        ) {
            return null;
        }

        return [
            'token' => $matches[1],
            'chat_id' => $chatId,
            'telegram_user_id' => $telegramUserId,
            'username' => $this->username($from['username'] ?? null),
            'first_name' => $this->firstName($from['first_name'] ?? null),
        ];
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0
                ? $value
                : null;
        }

        if (
            ! is_string($value)
            || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1
        ) {
            return null;
        }

        $normalized = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        return is_int($normalized)
            ? $normalized
            : null;
    }

    private function telegramId(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (
            ! is_string($value)
            || preg_match('/\A[1-9][0-9]{0,19}\z/D', $value) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function username(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            $value === ''
            || preg_match(
                '/\A[A-Za-z0-9_]{1,64}\z/D',
                $value,
            ) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function firstName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }
}
