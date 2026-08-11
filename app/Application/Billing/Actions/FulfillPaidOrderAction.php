<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Models\Order;

final readonly class FulfillPaidOrderAction
{
    public function __construct(
        private FulfillCloudRenewalAction $fulfillRenewal,
    ) {}

    public function execute(Order $order): void
    {
        $order = $order->fresh();

        if (
            ! $order instanceof Order
            || $order->status !== OrderStatus::Paid
        ) {
            return;
        }

        match ($order->type) {
            OrderType::CloudPurchase =>
                ProvisionPaidOrderJob::dispatch(
                    $order->getKey(),
                ),

            OrderType::CloudRenewal =>
                $this->fulfillRenewal->execute(
                    $order->getKey(),
                ),
        };
    }
}
