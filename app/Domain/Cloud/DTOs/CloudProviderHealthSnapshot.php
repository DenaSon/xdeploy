<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use Carbon\CarbonImmutable;

final readonly class CloudProviderHealthSnapshot
{
    public function __construct(
        public CloudProviderType $provider,
        public ?CloudProviderHealthStatus $status,
        public int $consecutiveAvailabilityFailures,
        public int $consecutiveSuccesses,
        public ?CarbonImmutable $lastSuccessAt,
        public ?CarbonImmutable $lastFailureAt,
        public ?CloudProviderHealthFailureCategory $lastErrorCategory,
        public ?int $lastErrorHttpStatus,
        public ?float $lastLatencyMs,
        public ?string $lastOperation,
        public CarbonImmutable $lastObservedAt,
        public ?CarbonImmutable $statusChangedAt,
    ) {}
}
