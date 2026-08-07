<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudReportMetric;
use DateTimeImmutable;

final readonly class CloudReportChartData
{
    /**
     * @param  list<DateTimeImmutable>  $timestamps
     * @param  list<CloudReportSeriesData>  $series
     */
    public function __construct(
        public array $timestamps,
        public array $series,
    ) {}

    public function pointCount(): int
    {
        return count(
            $this->timestamps,
        );
    }

    public function isEmpty(): bool
    {
        return $this->timestamps === [];
    }

    public function seriesFor(
        CloudReportMetric $metric,
    ): ?CloudReportSeriesData {
        foreach ($this->series as $series) {
            if ($series->metric === $metric) {
                return $series;
            }
        }

        return null;
    }

    public function hasSeries(
        CloudReportMetric $metric,
    ): bool {
        return $this->seriesFor(
            $metric,
        ) !== null;
    }

    public function latestTimestamp(): ?DateTimeImmutable
    {
        if ($this->timestamps === []) {
            return null;
        }

        return $this->timestamps[
        array_key_last(
            $this->timestamps,
        )
        ];
    }
}
