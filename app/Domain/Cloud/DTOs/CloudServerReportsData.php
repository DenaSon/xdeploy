<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudReportPeriod;

final readonly class CloudServerReportsData
{
    public function __construct(
        public string $regionId,
        public string $serverId,
        public CloudReportPeriod $period,
        public CloudReportChartData $cpu,
        public CloudReportChartData $ram,
        public CloudReportChartData $network,
        public CloudReportChartData $disk,
    ) {}

    public function hasData(): bool
    {
        return ! $this->cpu->isEmpty()
            || ! $this->ram->isEmpty()
            || ! $this->network->isEmpty()
            || ! $this->disk->isEmpty();
    }
}
