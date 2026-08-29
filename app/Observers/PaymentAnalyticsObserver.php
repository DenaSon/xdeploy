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
            PaymentStatus::Failed => ProductAnalyticsEvent::PaymentFailed,
            PaymentStatus::Cancelled => ProductAnalyticsEvent::PaymentCancelled,
            default => null,
        };

        if (! $event instanceof ProductAnalyticsEvent) {
            return;
        }

        $properties = [
            'payment_id' => $payment->getKey(),
            'order_id' => $order->getKey(),
            'source' => 'purchase',
            'resource_kind' => 'vps',
            'gateway' => $payment->gateway,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $order->cloud_provider,
            'duration_hours' => $order->duration_hours,
            'duration_days' => intdiv($order->duration_hours, 24),
            'attempt_number' => $order->payments()
                ->where('id', '<=', $payment->getKey())
                ->count(),
        ];

        if ($payment->status === PaymentStatus::Failed) {
            $failureCode = $this->analyticsFailureCode(
                $payment->failure_code,
            );

            if ($failureCode !== null) {
                $properties['failure_code'] = $failureCode;
            }
        }

        $this->analytics->capture(
            $event,
            $order->user_id,
            $properties,
        );
    }

    private function analyticsFailureCode(?string $failureCode): ?string
    {
        $normalized = strtolower(trim((string) $failureCode));

        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^[a-z0-9._:-]{1,64}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
