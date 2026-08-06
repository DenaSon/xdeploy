<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerReportsData;
use App\Domain\Cloud\Enums\CloudReportPeriod;

interface CloudServerReportsInterface
{
    public function getServerReports(
        string $region,
        string $serverId,
        CloudReportPeriod $period,
    ): CloudServerReportsData;
}
