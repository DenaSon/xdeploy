<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use InvalidArgumentException;

final readonly class CalculateCloudPurchasePriceAction
{
    public function __construct(
        private CloudProviderInterface $cloud,
        private CloudServerResizeCatalogInterface $pricing,
        private CloudPricingCalculator $calculator,
    ) {}

    public function execute(
        string $region,
        string $sizeId,
        int $selectedDiskGiB,
        string $period,
    ): PurchasePriceData {
        $sizes = $this->cloud->listSizes($region);

        $size = null;

        foreach ($sizes as $candidate) {
            if ($candidate->id === $sizeId) {
                $size = $candidate;

                break;
            }
        }

        if ($size === null) {
            throw new InvalidArgumentException(
                "Cloud size [{$sizeId}] was not found in region [{$region}]."
            );
        }

        $defaultDiskGiB = $size->diskGiB;

        if ($selectedDiskGiB < $defaultDiskGiB) {
            throw new InvalidArgumentException(
                "Selected disk cannot be smaller than the default {$defaultDiskGiB} GiB."
            );
        }

        /*
         * Default disk is already included in the catalog price.
         *
         * We only use calculateDiskPrice() to determine the delta
         * when the customer requests a larger root disk.
         */
        if ($selectedDiskGiB === $defaultDiskGiB) {
            $defaultDiskHourly = '0';
            $defaultDiskMonthly = '0';
            $selectedDiskHourly = '0';
            $selectedDiskMonthly = '0';
        } else {
            $defaultDiskPrice = $this->pricing->calculateDiskPrice(
                region: $region,
                sizeId: $sizeId,
                diskGiB: $defaultDiskGiB,
            );

            $selectedDiskPrice = $this->pricing->calculateDiskPrice(
                region: $region,
                sizeId: $sizeId,
                diskGiB: $selectedDiskGiB,
            );

            $defaultDiskHourly = $defaultDiskPrice->hourlyPrice->amount;
            $defaultDiskMonthly = $defaultDiskPrice->monthlyPrice->amount;

            $selectedDiskHourly = $selectedDiskPrice->hourlyPrice->amount;
            $selectedDiskMonthly = $selectedDiskPrice->monthlyPrice->amount;
        }

        $result = $this->calculator->calculate(
            baseHourlyPrice: $size->hourlyPrice->amount,
            baseMonthlyPrice: $size->monthlyPrice->amount,
            defaultDiskHourlyPrice: $defaultDiskHourly,
            defaultDiskMonthlyPrice: $defaultDiskMonthly,
            selectedDiskHourlyPrice: $selectedDiskHourly,
            selectedDiskMonthlyPrice: $selectedDiskMonthly,
            period: $period,
        );

        return new PurchasePriceData(
            regionId: $region,
            sizeId: $sizeId,
            defaultDiskGiB: $defaultDiskGiB,
            selectedDiskGiB: $selectedDiskGiB,
            period: $result['period'],
            durationHours: $result['duration_hours'],
            providerCost: $result['provider_cost'],
            markupPercent: $result['markup_percent'],
            finalAmount: $result['final_amount'],
            currency: $result['currency'],
        );
    }
}
