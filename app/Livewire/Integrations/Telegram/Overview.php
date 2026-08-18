<?php

declare(strict_types=1);

namespace App\Livewire\Integrations\Telegram;

use App\Application\Integrations\Telegram\DisconnectTelegramAction;
use App\Application\Notifications\NotificationPreferenceService;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('Telegram')]
final class Overview extends Component
{
    public ?string $statusMessage = null;

    public function togglePreference(
        string $topic,
        NotificationPreferenceService $preferences,
        TelegramBotClient $telegram,
    ): void {
        $notificationTopic = NotificationTopic::tryFrom($topic);

        abort_unless($notificationTopic instanceof NotificationTopic, 422);

        $user = $this->user();

        if (
            ! $telegram->configured()
            || ! TelegramConnection::query()
                ->ownedBy($user)
                ->exists()
        ) {
            return;
        }

        $preferences->setTelegramPreference(
            $user,
            $notificationTopic,
            ! $preferences->telegramEnabled(
                $user,
                $notificationTopic,
            ),
        );

        $this->statusMessage = 'تنظیم اعلان‌های Telegram ذخیره شد.';
    }

    public function disconnect(
        DisconnectTelegramAction $disconnect,
    ): void {
        $disconnect->execute($this->user());

        $this->statusMessage = 'اتصال Telegram با موفقیت قطع شد.';
    }

    public function render(
        TelegramBotClient $telegram,
        NotificationPreferenceService $preferences,
    ): View {
        $user = $this->user();

        $connection = TelegramConnection::query()
            ->ownedBy($user)
            ->first();

        $linkPending = false;

        if (
            $telegram->configured()
            && ! $connection instanceof TelegramConnection
        ) {
            $linkPending = TelegramLinkChallenge::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->exists();
        }

        return view(
            'livewire.integrations.telegram.overview',
            [
                'telegramConfigured' => $telegram->configured(),
                'connection' => $connection,
                'linkPending' => $linkPending,
                'preferences' => $preferences->telegramPreferences($user),
            ],
        );
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
