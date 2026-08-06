<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudReportPeriod: string
{
    case OneMinute = '1m';

    case OneHour = '1h';

    case OneDay = '1d';
}
