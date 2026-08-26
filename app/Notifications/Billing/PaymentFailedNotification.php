<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class PaymentFailedNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', TelegramChannel::class];
    }

    public function telegramTopic(): NotificationTopic
    {
        return NotificationTopic::Billing;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::fromNotificationPayload($this->payload());
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'kind' => 'payment_failed',
            'title' => 'پرداخت ناموفق بود',
            'message' => sprintf(
                'شروع پرداخت سفارش #%d ناموفق بود. سفارش شما حفظ شده است و می‌توانید دوباره تلاش کنید.',
                $this->orderId,
            ),
            'icon' => 'lucide.triangle-alert',
            'tone' => 'error',
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'action_label' => 'مشاهده سفارش',
            'action_url' => route(
                'panel.orders.show',
                ['order' => $this->orderId],
                false,
            ),
        ];
    }
}
