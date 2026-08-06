<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudReportMetric;

final readonly class CloudReportSeriesData
{
    /**
     * @param  list<int|float>  $values
     */
    public function __construct(
        public CloudReportMetric $metric,
        public array $values,
    ) {}

    public function pointCount(): int
    {
        return count(
            $this->values,
        );
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
