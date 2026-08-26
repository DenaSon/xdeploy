<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

final readonly class PaymentAnalyticsObserver
{
    public function __construct(
        private ProductAnalytics $analytics,
    ) {}

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $order = $payment->order;

        if (! $order instanceof Order || ! $order->isProvisioning()) {
            return;
        }

        $event = match ($payment->status) {
            PaymentStatus::Pending => ProductAnalyticsEvent::PaymentStarted,
            PaymentStatus::Paid => ProductAnalyticsEvent::PaymentSucceeded,
            default => null,
        };

        if (! $event instanceof ProductAnalyticsEvent) {
            return;
        }

        $this->analytics->capture(
            $event,
            $order->user_id,
            [
                'payment_id' => $payment->getKey(),
                'order_id' => $order->getKey(),
                'gateway' => $payment->gateway,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $order->cloud_provider,
                'duration_hours' => $order->duration_hours,
            ],
        );
    }
}
