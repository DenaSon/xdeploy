<?php

declare(strict_types=1);

namespace App\Notifications\Profile;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class ProfileCompletionRequiredNotification extends Notification implements SendsTelegramNotification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [
            'database',
            TelegramChannel::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::fromNotificationPayload(
            $this->payload(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kind' => 'profile_completion_required',
            'title' => 'پروفایل خود را تکمیل کنید',
            'message' => 'با افزودن نام، نام خانوادگی و ایمیل، اطلاعات حساب خود را تکمیل کنید.',
            'icon' => 'lucide.user-round-pen',
            'tone' => 'info',
            'action_label' => 'تکمیل پروفایل',
            'action_url' => route(
                'panel.profile',
                absolute: false,
            ),
        ];
    }
}
