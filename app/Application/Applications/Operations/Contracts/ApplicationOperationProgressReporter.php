<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations\Contracts;

use App\Domain\Application\Shared\Enums\ApplicationOperationStage;

interface ApplicationOperationProgressReporter
{
    public function report(
        ApplicationOperationStage $stage,
    ): void;
}
