<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use InvalidArgumentException;

final readonly class CloudPricingCalculator
{
    private const int MAX_DECIMAL_PLACES = 6;

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
            $providerCost = $this->providerCost(
                basePrice: $baseMonthlyPrice,
                defaultDiskPrice: $defaultDiskMonthlyPrice,
                selectedDiskPrice: $selectedDiskMonthlyPrice,
            );
        } else {
            $providerCost = $this->providerCost(
                basePrice: $baseHourlyPrice,
                defaultDiskPrice: $defaultDiskHourlyPrice,
                selectedDiskPrice: $selectedDiskHourlyPrice,
                multiplier: (int) $periodConfig['hours'],
            );
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

    private function providerCost(
        string $basePrice,
        string $defaultDiskPrice,
        string $selectedDiskPrice,
        int $multiplier = 1,
    ): int {
        $decimalPlaces = max(
            $this->decimalPlaces($basePrice),
            $this->decimalPlaces($defaultDiskPrice),
            $this->decimalPlaces($selectedDiskPrice),
        );

        $base = $this->scaledAmount($basePrice, $decimalPlaces);
        $defaultDisk = $this->scaledAmount(
            $defaultDiskPrice,
            $decimalPlaces,
        );
        $selectedDisk = $this->scaledAmount(
            $selectedDiskPrice,
            $decimalPlaces,
        );

        $providerCost = (
            $base
            + max(0, $selectedDisk - $defaultDisk)
        ) * $multiplier;

        return $this->roundScaledAmount(
            amount: $providerCost,
            decimalPlaces: $decimalPlaces,
        );
    }

    private function decimalPlaces(string $amount): int
    {
        $amount = trim($amount);

        if (
            preg_match(
                '/\A[0-9]+(?:\.([0-9]+))?\z/',
                $amount,
                $matches,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "Cloud price amount [{$amount}] is invalid."
            );
        }

        $decimalPlaces = strlen($matches[1] ?? '');

        if ($decimalPlaces > self::MAX_DECIMAL_PLACES) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cloud price amount [%s] exceeds the supported [%d] decimal places.',
                    $amount,
                    self::MAX_DECIMAL_PLACES,
                ),
            );
        }

        return $decimalPlaces;
    }

    private function scaledAmount(
        string $amount,
        int $decimalPlaces,
    ): int {
        $amount = trim($amount);
        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '',
        );

        $factor = 10 ** $decimalPlaces;
        $fraction = str_pad(
            $fraction,
            $decimalPlaces,
            '0',
        );

        return ((int) $whole * $factor)
            + ($fraction === '' ? 0 : (int) $fraction);
    }

    private function roundScaledAmount(
        int $amount,
        int $decimalPlaces,
    ): int {
        if ($decimalPlaces === 0) {
            return $amount;
        }

        $factor = 10 ** $decimalPlaces;

        return intdiv(
            $amount + intdiv($factor, 2),
            $factor,
        );
    }
}
