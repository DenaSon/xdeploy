<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerReports\Actions;

use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\DTOs\CloudServerReportsData;
use App\Domain\Cloud\Enums\CloudReportPeriod;

final readonly class GetCloudServerReportsAction
{
    public function __construct(
        private CloudServerReportsInterface $reports,
    ) {}

    public function execute(
        string $region,
        string $serverId,
        CloudReportPeriod $period,
    ): CloudServerReportsData {
        return $this->reports->getServerReports(
            region: $region,
            serverId: $serverId,
            period: $period,
        );
    }
}
