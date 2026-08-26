<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use Illuminate\Notifications\Notification;

final class PaymentCancelledNotification extends Notification implements SendsTelegramNotification
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
            'kind' => 'payment_cancelled',
            'title' => 'پرداخت لغو شد',
            'message' => sprintf(
                'پرداخت سفارش #%d تکمیل نشد. سفارش شما حفظ شده است و در صورت معتبر بودن پیش‌فاکتور می‌توانید دوباره پرداخت کنید.',
                $this->orderId,
            ),
            'icon' => 'lucide.circle-x',
            'tone' => 'warning',
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
