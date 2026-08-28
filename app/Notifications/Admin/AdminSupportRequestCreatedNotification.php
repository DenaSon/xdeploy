<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class AdminSupportRequestCreatedNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $supportRequestId,
        public readonly int $userId,
        public readonly string $subject,
        public readonly SupportRequestCategory $category,
        public readonly ?int $serverId = null,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function telegramTopic(): NotificationTopic
    {
        return NotificationTopic::Support;
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $serverContext = $this->serverId !== null
            ? sprintf(' برای سرور #%d', $this->serverId)
            : '';

        return TelegramMessage::fromNotificationPayload([
            'kind' => 'admin_support_request_created',
            'title' => 'درخواست پشتیبانی جدید در Coreflare',
            'message' => sprintf(
                'درخواست #%d از کاربر #%d%s در دسته «%s» ثبت شد. عنوان: %s',
                $this->supportRequestId,
                $this->userId,
                $serverContext,
                $this->categoryLabel(),
                $this->subject,
            ),
            'action_label' => 'مشاهده درخواست',
            'action_url' => route(
                'admin.support.show',
                ['supportRequestId' => $this->supportRequestId],
                false,
            ),
        ]);
    }

    private function categoryLabel(): string
    {
        return match ($this->category) {
            SupportRequestCategory::Technical => 'فنی',
            SupportRequestCategory::Billing => 'مالی',
            SupportRequestCategory::Account => 'حساب کاربری',
            SupportRequestCategory::Other => 'سایر',
        };
    }
}
