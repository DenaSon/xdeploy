<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Models\Order;
use App\Models\User;

final readonly class GetCustomerOrderAction
{
    public function execute(
        User $user,
        int $orderId,
    ): Order {
        return Order::query()
            ->whereKey($orderId)
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->with([
                'historicalServer',
                'latestPayment',
                'payments' => static fn ($query) => $query->latest('id'),
            ])
            ->firstOrFail();
    }
}
