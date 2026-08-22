<?php

declare(strict_types=1);

namespace App\Application\Cloud\Services;

use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\DTOs\CloudProviderPurchaseReadiness;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderPurchaseReadinessStatus;
use App\Domain\Cloud\Enums\CloudProviderType;

final readonly class CloudProviderPurchaseReadinessService
{
    public function __construct(
        private CloudProviderHealthEngine $health,
    ) {}

    public function evaluate(
        CloudProviderType $provider,
    ): CloudProviderPurchaseReadiness {
        $snapshot = $this->health->snapshot($provider);

        if (! $snapshot instanceof CloudProviderHealthSnapshot) {
            return $this->ready($provider);
        }

        $blockingCategory = $this->unresolvedBlockingCategory($snapshot);

        if ($blockingCategory instanceof CloudProviderHealthFailureCategory) {
            return new CloudProviderPurchaseReadiness(
                provider: $provider,
                status: $this->statusForBlockingCategory($blockingCategory),
                healthStatus: $snapshot->status,
                blockingCategory: $blockingCategory,
            );
        }

        if ($snapshot->status === CloudProviderHealthStatus::Unavailable) {
            return new CloudProviderPurchaseReadiness(
                provider: $provider,
                status: CloudProviderPurchaseReadinessStatus::TemporarilyUnavailable,
                healthStatus: $snapshot->status,
            );
        }

        return $this->ready(
            provider: $provider,
            healthStatus: $snapshot->status,
        );
    }

    private function unresolvedBlockingCategory(
        CloudProviderHealthSnapshot $snapshot,
    ): ?CloudProviderHealthFailureCategory {
        $category = $snapshot->lastErrorCategory;

        if (
            ! $category instanceof CloudProviderHealthFailureCategory
            || ! in_array(
                $category,
                [
                    CloudProviderHealthFailureCategory::Authentication,
                    CloudProviderHealthFailureCategory::Authorization,
                    CloudProviderHealthFailureCategory::Configuration,
                    CloudProviderHealthFailureCategory::InsufficientBalance,
                ],
                true,
            )
            || $snapshot->lastFailureAt === null
        ) {
            return null;
        }

        if ($snapshot->lastSuccessAt === null) {
            return $category;
        }

        return $snapshot->lastFailureAt->greaterThanOrEqualTo(
            $snapshot->lastSuccessAt,
        )
            ? $category
            : null;
    }

    private function statusForBlockingCategory(
        CloudProviderHealthFailureCategory $category,
    ): CloudProviderPurchaseReadinessStatus {
        return match ($category) {
            CloudProviderHealthFailureCategory::Authentication,
            CloudProviderHealthFailureCategory::Authorization => CloudProviderPurchaseReadinessStatus::BlockedCredentials,
            CloudProviderHealthFailureCategory::Configuration => CloudProviderPurchaseReadinessStatus::BlockedConfiguration,
            CloudProviderHealthFailureCategory::InsufficientBalance => CloudProviderPurchaseReadinessStatus::BlockedBalance,
            default => CloudProviderPurchaseReadinessStatus::Ready,
        };
    }

    private function ready(
        CloudProviderType $provider,
        ?CloudProviderHealthStatus $healthStatus = null,
    ): CloudProviderPurchaseReadiness {
        return new CloudProviderPurchaseReadiness(
            provider: $provider,
            status: CloudProviderPurchaseReadinessStatus::Ready,
            healthStatus: $healthStatus,
        );
    }
}
