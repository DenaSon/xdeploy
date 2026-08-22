<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Cloud\Actions\ResolveCloudImageForOrderAction;
use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\DTOs\PurchaseQuoteExpectationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\PurchaseQuoteChangedException;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudProviderPurchaseUnavailableException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrderAction
{
    public function __construct(
        private CalculateCloudPurchasePriceAction $calculatePrice,
        private ResolveCloudImageForOrderAction $resolveImage,
        private CloudProviderRegistryInterface $providers,
    ) {}

    public function execute(
        User $user,
        string $region,
        string $sizeId,
        string $imageId,
        int $selectedDiskGiB,
        string $period,
        CloudProviderType $provider = CloudProviderType::Arvan,
        ?PurchaseQuoteExpectationData $expectedQuote = null,
    ): Order {
        if (! in_array(
            $provider,
            $this->providers->purchasableProviders(),
            true,
        )) {
            throw new CloudProviderPurchaseUnavailableException(
                sprintf(
                    'The cloud provider [%s] is not available for new purchases.',
                    $provider->value,
                ),
            );
        }

        $region = trim($region);
        $sizeId = trim($sizeId);
        $imageId = trim($imageId);

        /*
         * Resolve the image again from the selected provider catalog.
         * Never trust image metadata coming from Presentation.
         */
        $image = $this->resolveImage->execute(
            region: $region,
            sizeId: $sizeId,
            imageId: $imageId,
            selectedDiskGiB: $selectedDiskGiB,
            provider: $provider,
        );

        /*
         * Calculate the authoritative price from the same provider that
         * becomes immutable ownership metadata on the Order.
         */
        $price = $this->calculatePrice->execute(
            region: $region,
            sizeId: $sizeId,
            selectedDiskGiB: $selectedDiskGiB,
            period: $period,
            provider: $provider,
        );

        /*
         * The customer may have seen a catalog/price snapshot moments before
         * this authoritative read. Never create a payable Order for a changed
         * amount without making the customer explicitly confirm the fresh
         * quote first.
         */
        $this->assertExpectedQuote(
            price: $price,
            expectedQuote: $expectedQuote,
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
                'cloud_provider' => $provider->value,

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

    private function assertExpectedQuote(
        PurchasePriceData $price,
        ?PurchaseQuoteExpectationData $expectedQuote,
    ): void {
        if ($expectedQuote === null) {
            return;
        }

        $sameQuote = $expectedQuote->finalAmount === (int) $price->finalAmount
            && strtoupper(trim($expectedQuote->currency))
                === strtoupper(trim($price->currency))
            && $expectedQuote->durationHours === $price->durationHours
            && $expectedQuote->selectedDiskGiB === $price->selectedDiskGiB;

        if ($sameQuote) {
            return;
        }

        throw new PurchaseQuoteChangedException(
            currentQuote: $price,
        );
    }
}
