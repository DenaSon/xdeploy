<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations;

use App\Application\Applications\Operations\Contracts\ApplicationOperationProgressReporter;
use App\Domain\Application\Shared\Enums\ApplicationOperationStage;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Models\ApplicationOperation;

final readonly class DatabaseApplicationOperationProgressReporter implements ApplicationOperationProgressReporter
{
    public function __construct(
        private int $operationId,
    ) {}

    public function report(
        ApplicationOperationStage $stage,
    ): void {
        ApplicationOperation::query()
            ->whereKey($this->operationId)
            ->whereIn(
                'status',
                [
                    ApplicationOperationStatus::Pending->value,
                    ApplicationOperationStatus::Running->value,
                ],
            )
            ->update([
                'stage' => $stage->value,
                'stage_updated_at' => now(),
            ]);
    }
}
