<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Cloud\Actions\ResolveCloudImageForOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrderAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
        private ResolveCloudImageForOrderAction $resolveImage,
    ) {}

    public function execute(
        User $user,
        string $region,
        string $sizeId,
        string $imageId,
        int $selectedDiskGiB,
        string $period,
    ): Order {
        $region = trim($region);
        $sizeId = trim($sizeId);
        $imageId = trim($imageId);

        /*
         * Resolve the image again from the provider catalog.
         * Never trust image metadata coming from Presentation.
         */
        $image = $this->resolveImage->execute(
            region: $region,
            sizeId: $sizeId,
            imageId: $imageId,
            selectedDiskGiB: $selectedDiskGiB,
        );

        /*
         * Calculate the authoritative price from the current
         * provider catalog.
         */
        $price = $this->calculatePrice->execute(
            region: $region,
            sizeId: $sizeId,
            selectedDiskGiB: $selectedDiskGiB,
            period: $period,
        );

        $quoteTtlMinutes = max(
            1,
            (int) config(
                'money.quote_ttl_minutes',
                15,
            ),
        );

        return DB::transaction(
            static fn (): Order => Order::query()->create([
                'user_id' => $user->getKey(),
                'type' => OrderType::Provisioning,

                'region_id' => $price->regionId,
                'size_id' => $price->sizeId,

                'image_id' => $image->id,
                'image_name' => $image->name,
                'image_distribution' => $image->distribution,
                'image_version' => $image->version,

                'default_disk_gib' => $price->defaultDiskGiB,
                'selected_disk_gib' => $price->selectedDiskGiB,

                'period' => $price->period,
                'duration_hours' => $price->durationHours,

                'provider_cost' => (int) $price->providerCost,
                'markup_percent' => $price->markupPercent,
                'final_amount' => (int) $price->finalAmount,
                'currency' => $price->currency,

                'status' => OrderStatus::PendingPayment,

                'quote_expires_at' => now()->addMinutes(
                    $quoteTtlMinutes,
                ),

                'paid_at' => null,
            ]),
        );
    }
}
