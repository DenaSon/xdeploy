<?php

declare(strict_types=1);

namespace App\Application\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NotificationPreferenceService
{
    private const string TELEGRAM_CHANNEL = 'telegram';

    public function telegramEnabled(
        User $user,
        NotificationTopic $topic,
    ): bool {
        return $this->telegramEnabledForUserId(
            (int) $user->getKey(),
            $topic,
        );
    }

    public function telegramEnabledForUserId(
        int $userId,
        NotificationTopic $topic,
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $enabled = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('channel', self::TELEGRAM_CHANNEL)
            ->where('topic', $topic->value)
            ->value('enabled');

        return $enabled === null
            ? true
            : (bool) $enabled;
    }

    /**
     * @return array<string, bool>
     */
    public function telegramPreferences(User $user): array
    {
        $preferences = [];

        foreach (NotificationTopic::cases() as $topic) {
            $preferences[$topic->value] = true;
        }

        $stored = NotificationPreference::query()
            ->ownedBy($user)
            ->where('channel', self::TELEGRAM_CHANNEL)
            ->get(['topic', 'enabled']);

        foreach ($stored as $preference) {
            if (NotificationTopic::tryFrom($preference->topic) === null) {
                continue;
            }

            $preferences[$preference->topic] = $preference->enabled;
        }

        return $preferences;
    }

    public function setTelegramPreference(
        User $user,
        NotificationTopic $topic,
        bool $enabled,
    ): void {
        DB::transaction(
            static function () use ($user, $topic, $enabled): void {
                User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'channel' => self::TELEGRAM_CHANNEL,
                        'topic' => $topic->value,
                    ],
                    [
                        'enabled' => $enabled,
                    ],
                );
            },
            3,
        );
    }
}
