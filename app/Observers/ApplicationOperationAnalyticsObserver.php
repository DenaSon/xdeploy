<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Models\ApplicationOperation;

final readonly class ApplicationOperationAnalyticsObserver
{
    public function __construct(
        private ProductAnalytics $analytics,
    ) {}

    public function updated(ApplicationOperation $operation): void
    {
        if (
            ! $operation->wasChanged('status')
            || $operation->operation !== ApplicationOperationType::Install
            || $operation->status !== ApplicationOperationStatus::Succeeded
        ) {
            return;
        }

        $properties = [
            'operation_id' => $operation->getKey(),
            'server_id' => $operation->server_id,
            'application_type' => $operation->application_type,
            'duration_seconds' => $operation->started_at !== null
                && $operation->finished_at !== null
                    ? $operation->started_at->diffInSeconds(
                        $operation->finished_at,
                    )
                    : null,
        ];

        $this->analytics->capture(
            ProductAnalyticsEvent::ApplicationInstallCompleted,
            $operation->user_id,
            $properties,
        );

        $this->analytics->capture(
            ProductAnalyticsEvent::ApplicationRunning,
            $operation->user_id,
            $properties,
        );
    }
}
