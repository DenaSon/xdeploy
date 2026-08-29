<?php

declare(strict_types=1);

namespace App\Application\Analytics\Contracts;

use App\Application\Analytics\ProductAnalyticsReport;

interface ProductAnalyticsReporting
{
    public function report(int $days): ProductAnalyticsReport;
}
