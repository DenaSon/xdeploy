<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Billing\PaymentCancelledNotification;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Billing\PaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendPaymentStatusNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly SendNotificationOnceAction $sendOnce,
        private readonly TelegramChannel $telegram,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function handle(PaymentStatusChanged $event): void
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->whereKey($event->paymentId)
            ->where('order_id', $event->orderId)
            ->first();

        if (
            ! $payment instanceof Payment
            || $payment->status !== $event->status
        ) {
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey($event->orderId)
            ->first();

        if (! $order instanceof Order) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()->find($order->user_id);

        if (! $user instanceof User) {
            return;
        }

        $notification = $this->notificationFor(
            payment: $payment,
            status: $event->status,
        );

        if (! $notification instanceof Notification) {
            return;
        }

        $delivered = $this->sendOnce->execute(
            user: $user,
            dedupeKey: $event->dedupeKey(),
            notification: $notification,
        );

        if ($delivered) {
            $this->telegram->send(
                $user,
                $notification,
            );
        }
    }

    public function failed(
        PaymentStatusChanged $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.payment_status_failed',
            [
                'payment_id' => $event->paymentId,
                'order_id' => $event->orderId,
                'status' => $event->status->value,
                'message' => $exception->getMessage(),
            ],
        );
    }

    private function notificationFor(
        Payment $payment,
        PaymentStatus $status,
    ): ?Notification {
        return match ($status) {
            PaymentStatus::Paid => new PaymentSucceededNotification(
                paymentId: (int) $payment->getKey(),
                orderId: $payment->order_id,
                amount: $payment->amount,
            ),
            PaymentStatus::Cancelled => new PaymentCancelledNotification(
                paymentId: (int) $payment->getKey(),
                orderId: $payment->order_id,
            ),
            PaymentStatus::Failed => new PaymentFailedNotification(
                paymentId: (int) $payment->getKey(),
                orderId: $payment->order_id,
            ),
            default => null,
        };
    }
}
