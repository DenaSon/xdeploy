<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderPurchaseReadinessStatus;
use App\Domain\Cloud\Enums\CloudProviderType;

final readonly class CloudProviderPurchaseReadiness
{
    public function __construct(
        public CloudProviderType $provider,
        public CloudProviderPurchaseReadinessStatus $status,
        public ?CloudProviderHealthStatus $healthStatus = null,
        public ?CloudProviderHealthFailureCategory $blockingCategory = null,
    ) {}

    public function allowsPurchase(): bool
    {
        return $this->status->allowsPurchase();
    }

    public function isDegraded(): bool
    {
        return $this->healthStatus === CloudProviderHealthStatus::Degraded;
    }
}
