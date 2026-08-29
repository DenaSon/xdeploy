<?php

declare(strict_types=1);

namespace App\Application\Analytics;

final readonly class ProductAnalyticsReport
{
    /**
     * @param  array<string, int|float>  $overview
     * @param  array<string, list<array<string, int|float|string|null>>>  $funnels
     * @param  list<array{label: string, value: int}>  $acquisition
     * @param  list<array{label: string, value: int}>  $payments
     * @param  list<array{label: string, value: int}>  $providers
     * @param  list<array{label: string, value: int}>  $applications
     */
    public function __construct(
        public bool $available,
        public int $days,
        public array $overview = [],
        public array $funnels = [],
        public array $acquisition = [],
        public array $payments = [],
        public array $providers = [],
        public array $applications = [],
        public ?string $unavailableReason = null,
    ) {}

    public static function unavailable(
        int $days,
        string $reason,
    ): self {
        return new self(
            available: false,
            days: $days,
            unavailableReason: $reason,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'days' => $this->days,
            'overview' => $this->overview,
            'funnels' => $this->funnels,
            'acquisition' => $this->acquisition,
            'payments' => $this->payments,
            'providers' => $this->providers,
            'applications' => $this->applications,
            'unavailable_reason' => $this->unavailableReason,
        ];
    }
}
