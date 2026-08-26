<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Support\Money\MoneyFormatter;
use Illuminate\Notifications\Notification;

final class PaymentSucceededNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly int $amount,
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
            'kind' => 'payment_succeeded',
            'title' => 'پرداخت با موفقیت انجام شد',
            'message' => sprintf(
                'پرداخت سفارش #%d به مبلغ %s تومان تأیید شد. آماده‌سازی سرویس آغاز شده است.',
                $this->orderId,
                MoneyFormatter::tomanFromRial($this->amount),
            ),
            'icon' => 'lucide.circle-check',
            'tone' => 'success',
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
