<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCustomerOrdersAction
{
    private const int DEFAULT_PER_PAGE = 15;

    private const int MAX_PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function execute(
        User $user,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): LengthAwarePaginator {
        $perPage = max(
            1,
            min($perPage, self::MAX_PER_PAGE),
        );

        return Order::query()
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->with([
                'latestPayment',
                'historicalServer',
            ])
            ->latest('id')
            ->paginate($perPage);
    }
}
