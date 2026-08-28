<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Application\Integrations\Telegram\Contracts\SendsTelegramNotification;
use App\Application\Integrations\Telegram\TelegramMessage;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Support\Money\MoneyFormatter;
use Illuminate\Notifications\Notification;

final class AdminPaymentSucceededNotification extends Notification implements SendsTelegramNotification
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly int $userId,
        public readonly int $amount,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function telegramTopic(): NotificationTopic
    {
        return NotificationTopic::Billing;
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::fromNotificationPayload([
            'kind' => 'admin_payment_succeeded',
            'title' => 'پرداخت جدید در Coreflare',
            'message' => sprintf(
                'پرداخت سفارش #%d به مبلغ %s تومان برای کاربر #%d تأیید شد.',
                $this->orderId,
                MoneyFormatter::tomanFromRial($this->amount),
                $this->userId,
            ),
            'action_label' => 'مشاهده پرداخت',
            'action_url' => route(
                'admin.payments.show',
                ['payment' => $this->paymentId],
                false,
            ),
        ]);
    }
}
