<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;

final readonly class OrderAnalyticsObserver
{
    public function __construct(
        private ProductAnalytics $analytics,
    ) {}

    public function created(Order $order): void
    {
        if (! $order->isProvisioning()) {
            return;
        }

        $this->analytics->capture(
            ProductAnalyticsEvent::OrderCreated,
            $order->user_id,
            $this->orderProperties($order),
        );
    }

    public function updated(Order $order): void
    {
        if (
            ! $order->isProvisioning()
            || ! $order->wasChanged('status')
        ) {
            return;
        }

        if ($order->status === OrderStatus::Provisioning) {
            $this->analytics->capture(
                ProductAnalyticsEvent::ProvisioningStarted,
                $order->user_id,
                $this->orderProperties($order),
            );

            return;
        }

        if ($order->status !== OrderStatus::Fulfilled) {
            return;
        }

        $this->analytics->capture(
            ProductAnalyticsEvent::ServerFulfilled,
            $order->user_id,
            [
                ...$this->orderProperties($order),
                'server_id' => $order->server_id,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function orderProperties(Order $order): array
    {
        return [
            'order_id' => $order->getKey(),
            'provider' => $order->cloud_provider,
            'region_id' => $order->region_id,
            'size_id' => $order->size_id,
            'image_distribution' => $order->image_distribution,
            'image_version' => $order->image_version,
            'selected_disk_gib' => $order->selected_disk_gib,
            'duration_hours' => $order->duration_hours,
            'provider_cost' => $order->provider_cost,
            'final_amount' => $order->final_amount,
            'currency' => $order->currency,
        ];
    }
}
