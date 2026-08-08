<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrderAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
    ) {}

    public function execute(
        User $user,
        string $region,
        string $sizeId,
        int $selectedDiskGiB,
        string $period,
    ): Order {
        $price = $this->calculatePrice->execute(
            region: $region,
            sizeId: $sizeId,
            selectedDiskGiB: $selectedDiskGiB,
            period: $period,
        );

        $quoteTtlMinutes = max(
            1,
            (int) config('money.quote_ttl_minutes', 15),
        );

        return DB::transaction(
            static fn (): Order => Order::query()->create([
                'user_id' => $user->getKey(),
                'region_id' => $price->regionId,
                'size_id' => $price->sizeId,
                'default_disk_gib' => $price->defaultDiskGiB,
                'selected_disk_gib' => $price->selectedDiskGiB,
                'period' => $price->period,
                'duration_hours' => $price->durationHours,
                'provider_cost' => (int) $price->providerCost,
                'markup_percent' => $price->markupPercent,
                'final_amount' => (int) $price->finalAmount,
                'currency' => $price->currency,
                'status' => OrderStatus::PendingPayment,
                'quote_expires_at' => now()->addMinutes($quoteTtlMinutes),
                'paid_at' => null,
            ]),
        );
    }
}
