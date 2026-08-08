<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use InvalidArgumentException;

final readonly class CloudPricingCalculator
{
    public function calculate(
        string $baseHourlyPrice,
        string $baseMonthlyPrice,
        string $defaultDiskHourlyPrice,
        string $defaultDiskMonthlyPrice,
        string $selectedDiskHourlyPrice,
        string $selectedDiskMonthlyPrice,
        string $period,
    ): array {
        $markupPercent = (int) config('money.markup_percent');

        $periodConfig = config("money.periods.{$period}");

        if (! is_array($periodConfig)) {
            throw new InvalidArgumentException(
                "Unsupported purchase period [{$period}]."
            );
        }

        if ($periodConfig['pricing'] === 'monthly') {
            $extraDisk = max(
                0,
                (int) $selectedDiskMonthlyPrice
                - (int) $defaultDiskMonthlyPrice,
            );

            $providerCost = (int) $baseMonthlyPrice + $extraDisk;
        } else {
            $extraDiskHourly = max(
                0,
                (int) $selectedDiskHourlyPrice
                - (int) $defaultDiskHourlyPrice,
            );

            $providerHourly = (int) $baseHourlyPrice
                + $extraDiskHourly;

            $providerCost = $providerHourly
                * (int) $periodConfig['hours'];
        }

        $customerPrice = intdiv(
            $providerCost * (100 + $markupPercent),
            100,
        );

        return [
            'period' => $period,
            'duration_hours' => (int) $periodConfig['hours'],
            'provider_cost' => (string) $providerCost,
            'markup_percent' => $markupPercent,
            'final_amount' => (string) $customerPrice,
            'currency' => config('money.currency'),
        ];
    }
}
