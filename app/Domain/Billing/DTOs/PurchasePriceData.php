<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PurchasePriceData
{
    public function __construct(
        public string $regionId,
        public string $sizeId,
        public int $defaultDiskGiB,
        public int $selectedDiskGiB,
        public string $period,
        public int $durationHours,
        public string $providerCost,
        public int $markupPercent,
        public string $finalAmount,
        public string $currency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'region_id' => $this->regionId,
            'size_id' => $this->sizeId,
            'default_disk_gib' => $this->defaultDiskGiB,
            'selected_disk_gib' => $this->selectedDiskGiB,
            'period' => $this->period,
            'duration_hours' => $this->durationHours,
            'provider_cost' => $this->providerCost,
            'markup_percent' => $this->markupPercent,
            'final_amount' => $this->finalAmount,
            'currency' => $this->currency,
        ];
    }
}
