<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Application\Notifications\Actions\SendTelegramNotificationOnceAction;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Admin\AdminPaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class SendAdminPaymentSucceededNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly SendTelegramNotificationOnceAction $sendOnce,
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
        if ($event->status !== PaymentStatus::Paid) {
            return;
        }

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->whereKey($event->paymentId)
            ->where('order_id', $event->orderId)
            ->first();

        if (
            ! $payment instanceof Payment
            || $payment->status !== PaymentStatus::Paid
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

        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $notification = new AdminPaymentSucceededNotification(
            paymentId: (int) $payment->getKey(),
            orderId: (int) $order->getKey(),
            userId: (int) $order->user_id,
            amount: (int) $payment->amount,
        );

        foreach ($admins as $admin) {
            $this->sendOnce->execute(
                user: $admin,
                dedupeKey: sprintf(
                    'admin:payment:%d:paid:recipient:%d',
                    $payment->getKey(),
                    $admin->getKey(),
                ),
                notification: $notification,
            );
        }
    }

    public function failed(
        PaymentStatusChanged $event,
        Throwable $exception,
    ): void {
        logger()->error(
            'notification.admin_payment_succeeded_failed',
            [
                'payment_id' => $event->paymentId,
                'order_id' => $event->orderId,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
